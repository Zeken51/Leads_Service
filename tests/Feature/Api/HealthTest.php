<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_public(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonStructure(['status', 'service', 'version', 'timestamp'])
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', 'leads-service');
    }

    public function test_health_does_not_require_auth_header(): void
    {
        $response = $this->get('/api/health', ['Accept' => 'application/json']);

        $response->assertOk();
    }
}
