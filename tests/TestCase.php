<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function policyPayload(array $overrides = []): array
    {
        return array_merge([
            'client_request_id' => 'client-req-001',
            'agent_id' => 'agent-001',
            'customer_name' => 'Asha Rao',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function callbackHeaders(array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return [
            'X-Mock-Insurer-Signature' => hash_hmac(
                'sha256',
                $body,
                (string) config('policyflow.mock_insurer.shared_secret'),
            ),
        ];
    }
}
