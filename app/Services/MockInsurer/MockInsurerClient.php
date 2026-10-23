<?php

declare(strict_types=1);

namespace App\Services\MockInsurer;

use App\Enums\MockScenario;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

final class MockInsurerClient
{
    public function issue(array $payload, string $idempotencyKey, MockScenario $scenario): ProviderIssueResult
    {
        try {
            $response = $this->http($idempotencyKey, $scenario)
                ->post($this->url('/issue'), $payload + [
                    'idempotency_key' => $idempotencyKey,
                    'scenario' => $scenario->value,
                ]);
        } catch (ConnectionException $exception) {
            return $this->unknownOutcome('GATEWAY_TIMEOUT', 'Provider request timed out or was disconnected.', 504);
        }

        return $this->map($response->status(), $response->json() ?? []);
    }

    public function lookupByIdempotencyKey(string $idempotencyKey): ?array
    {
        try {
            $response = $this->http($idempotencyKey, MockScenario::Success)
                ->get($this->url('/policies/by-key/'.rawurlencode($idempotencyKey)));
        } catch (ConnectionException) {
            return null;
        }

        if ($response->status() === 404) {
            return null;
        }

        return $response->json();
    }

    public function lookupByProviderReference(string $providerReference): ?array
    {
        try {
            $response = $this->http($providerReference, MockScenario::Success)
                ->get($this->url('/policies/by-reference/'.rawurlencode($providerReference)));
        } catch (ConnectionException) {
            return null;
        }

        if ($response->status() === 404) {
            return null;
        }

        return $response->json();
    }

    private function http(string $idempotencyKey, MockScenario $scenario): PendingRequest
    {
        $secret = (string) config('policyflow.mock_insurer.shared_secret');
        $timeout = (int) config('policyflow.mock_insurer.timeout_seconds', 5);

        $pending = Http::acceptJson()
            ->asJson()
            ->connectTimeout($timeout)
            ->timeout($timeout)
            ->withHeaders([
                'X-Idempotency-Key' => $idempotencyKey,
                'X-Mock-Scenario' => $scenario->value,
                'X-Mock-Signature' => hash_hmac('sha256', $idempotencyKey, $secret),
            ]);

        if (config('policyflow.mock_insurer.driver') === 'in_process') {
            $stack = HandlerStack::create(function (RequestInterface $request, array $options) {
                return Create::promiseFor(app(MockInsurerHttpGateway::class)->handle($request));
            });

            $pending->setClient(new Client([
                'handler' => $stack,
                'http_errors' => false,
                'timeout' => $timeout,
                'connect_timeout' => $timeout,
            ]));
        }

        return $pending;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('policyflow.mock_insurer.base_url'), '/').$path;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function map(int $status, array $body): ProviderIssueResult
    {
        $outcome = (string) ($body['outcome'] ?? match (true) {
            $status === 200 => 'SUCCESS',
            $status === 202 => 'ACCEPTED',
            $status === 503 => 'TEMPORARY_FAILURE',
            default => 'UNKNOWN',
        });

        if (in_array($status, [408, 504], true)) {
            $outcome = 'UNKNOWN';
        }

        return new ProviderIssueResult(
            outcome: $outcome,
            providerReference: $body['provider_reference'] ?? null,
            policyNumber: $body['policy_number'] ?? null,
            errorCode: $body['error_code'] ?? null,
            errorMessage: $body['error_message'] ?? null,
            httpStatus: $status,
            body: $body,
        );
    }

    private function unknownOutcome(string $code, string $message, int $status): ProviderIssueResult
    {
        return new ProviderIssueResult(
            outcome: 'UNKNOWN',
            providerReference: null,
            policyNumber: null,
            errorCode: $code,
            errorMessage: $message,
            httpStatus: $status,
            body: [],
        );
    }
}
