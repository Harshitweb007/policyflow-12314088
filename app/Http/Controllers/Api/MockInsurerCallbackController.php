<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MockInsurerCallbackRequest;
use App\Http\Resources\PolicyRequestResource;
use App\Services\Callbacks\CallbackProcessor;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MockInsurerCallbackController extends Controller
{
    public function __invoke(MockInsurerCallbackRequest $request, CallbackProcessor $processor): JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            return ApiResponse::error('Invalid mock insurer signature. This check is a local simulation, not a production-grade webhook control.', 401);
        }

        $result = $processor->process($request->validated());

        if ($result['event']->processing_outcome === 'UNKNOWN_REFERENCE' && ! $result['duplicate']) {
            return ApiResponse::error('Unknown policy request or provider reference', 404, [
                'provider_event_id' => $result['event']->provider_event_id,
            ]);
        }

        if ($result['event']->processing_outcome === 'INVALID_STATE') {
            return ApiResponse::error('Callback cannot be applied to policy request in its current state', 409);
        }

        if ($result['event']->processing_outcome === 'INVALID') {
            return ApiResponse::error('Callback was stored but could not be applied', 422);
        }

        $data = [
            'provider_event_id' => $result['event']->provider_event_id,
            'processing_outcome' => $result['event']->processing_outcome,
            'duplicate' => $result['duplicate'],
            'applied' => $result['applied'],
            'policy_request' => $result['request']
                ? (new PolicyRequestResource($result['request']))->resolve()
                : null,
        ];

        $message = $result['duplicate']
            ? 'Duplicate provider event ignored'
            : 'Provider event processed';

        return ApiResponse::success($message, $data);
    }

    private function signatureIsValid(MockInsurerCallbackRequest $request): bool
    {
        $secret = (string) config('policyflow.mock_insurer.shared_secret');
        $provided = (string) $request->header('X-Mock-Insurer-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return $provided !== '' && hash_equals($expected, $provided);
    }
}
