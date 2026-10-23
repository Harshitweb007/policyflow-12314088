<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SystemMigrateTest extends TestCase
{
    public function test_rejects_unauthorized_migration_request(): void
    {
        Config::set('app.key', 'base64:correct-secret-test-key-1234567890123456');

        $response = $this->getJson('/api/system/migrate');
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
        ]);

        $invalidResponse = $this->getJson('/api/system/migrate?secret=wrong-secret');
        $invalidResponse->assertStatus(403);
    }

    public function test_executes_migration_with_valid_secret(): void
    {
        $secret = 'base64:valid-secret-test-key-1234567890123456';
        Config::set('app.key', $secret);

        $response = $this->getJson('/api/system/migrate?secret='.$secret);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Database migration executed successfully.',
        ]);
        $this->assertArrayHasKey('output', $response->json());
    }
}
