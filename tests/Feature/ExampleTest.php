<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('PolicyFlow Overview');
    }

    public function test_the_intake_page_returns_a_successful_response(): void
    {
        $response = $this->get('/intake');

        $response->assertStatus(200);
        $response->assertSee('New Policy Intake');
        $response->assertSee('Live Premium Breakdown');
    }

    public function test_the_requests_list_page_returns_a_successful_response(): void
    {
        PolicyRequest::factory()->count(2)->create();

        $response = $this->get('/requests');

        $response->assertStatus(200);
        $response->assertSee('Policy Requests');
    }

    public function test_the_request_detail_page_returns_a_successful_response(): void
    {
        $request = PolicyRequest::factory()->create();

        $response = $this->get('/requests/'.$request->uuid);

        $response->assertStatus(200);
        $response->assertSee('Policy Request:');
        $response->assertSee($request->customer_name);
    }
}
