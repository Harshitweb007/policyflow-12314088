<?php

declare(strict_types=1);

namespace App\Services\MockInsurer;

use App\Enums\MockScenario;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

final class MockInsurerHttpGateway
{
    public function __construct(private readonly MockInsurerService $service) {}

    public function handle(RequestInterface $request): Response
    {
        $path = rawurldecode($request->getUri()->getPath());
        $method = strtoupper($request->getMethod());
        $payload = json_decode((string) $request->getBody(), true) ?? [];

        if ($method === 'POST' && str_ends_with($path, '/issue')) {
            $key = $request->getHeaderLine('X-Idempotency-Key') ?: (string) ($payload['idempotency_key'] ?? '');
            $scenario = MockScenario::tryFrom($request->getHeaderLine('X-Mock-Scenario'))
                ?? MockScenario::tryFrom((string) ($payload['scenario'] ?? ''))
                ?? MockScenario::Success;

            $result = $this->service->issue($payload, $key, $scenario);

            return $this->json($result['http_status'], $result);
        }

        if ($method === 'GET' && preg_match('#/policies/by-key/([^/]+)$#', $path, $matches) === 1) {
            $result = $this->service->lookupByIdempotencyKey($matches[1]);

            return $result === null
                ? $this->json(404, ['message' => 'Unknown idempotency key'])
                : $this->json(200, $result);
        }

        if ($method === 'GET' && preg_match('#/policies/by-reference/([^/]+)$#', $path, $matches) === 1) {
            $result = $this->service->lookupByProviderReference($matches[1]);

            return $result === null
                ? $this->json(404, ['message' => 'Unknown provider reference'])
                : $this->json(200, $result);
        }

        return $this->json(404, ['message' => 'Unknown mock insurer route']);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }
}
