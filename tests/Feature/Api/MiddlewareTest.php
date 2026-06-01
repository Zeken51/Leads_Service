<?php

namespace Tests\Feature\Api;

use App\Domain\Auth\Models\TenantApiClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ruta de prueba protegida: requiere auth + tenant context
        Route::middleware(['api', 'auth:sanctum', 'set.tenant.context'])
            ->get('/api/test-protected', fn () => response()->json(['ok' => true]));
    }

    // -------------------------------------------------------------------------
    // request_id
    // -------------------------------------------------------------------------

    public function test_all_responses_include_x_request_id_header(): void
    {
        $this->getJson('/api/health')->assertHeader('X-Request-ID');

        $this->postJson('/api/v1/auth/login', [])
            ->assertHeader('X-Request-ID');
    }

    public function test_client_supplied_x_request_id_is_echoed_back(): void
    {
        $response = $this->withHeaders(['X-Request-ID' => 'req_custom01'])
            ->getJson('/api/health');

        $response->assertHeader('X-Request-ID', 'req_custom01');
    }

    public function test_error_responses_include_request_id_in_body(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized()
            ->assertJsonStructure(['request_id']);

        $this->assertStringStartsWith('req_', $response->json('request_id'));
    }

    // -------------------------------------------------------------------------
    // Content-Type
    // -------------------------------------------------------------------------

    public function test_json_content_type_with_charset_is_accepted(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);

        $response = $this->call('POST', '/api/v1/auth/login', [], [], [], [
            'HTTP_ACCEPT'       => 'application/json',
            'CONTENT_TYPE'      => 'application/json; charset=utf-8',
        ], json_encode(['email' => $user->email, 'password' => 'secret']));

        // No debe rechazar por Content-Type
        $this->assertNotEquals(415, $response->getStatusCode());
    }

    public function test_form_urlencoded_content_type_is_rejected(): void
    {
        $response = $this->call('POST', '/api/v1/auth/login', [], [], [], [
            'HTTP_ACCEPT'  => 'application/json',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ], 'email=a%40b.com&password=secret');

        $this->assertEquals(415, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('request_id', $data);
        $this->assertArrayHasKey('message', $data);
    }

    public function test_get_requests_are_not_blocked_by_content_type_check(): void
    {
        // GET nunca debe fallar por Content-Type
        $this->getJson('/api/health')->assertOk();
    }

    // -------------------------------------------------------------------------
    // TenantApiClient is_active
    // -------------------------------------------------------------------------

    public function test_inactive_api_client_token_returns_403_on_protected_routes(): void
    {
        $client = TenantApiClient::create([
            'tenant_id'     => 'aaaaaaaa-0000-0000-0000-000000000001',
            'name'          => 'Inactive Client',
            'token_name'    => 'inactive-test',
            'source_system' => 'web_form',
            'is_active'     => false,
        ]);
        $token = $client->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/test-protected');

        $response->assertForbidden()
            ->assertJsonPath('message', 'API client is inactive.');
    }

    public function test_active_api_client_token_passes_through(): void
    {
        $client = TenantApiClient::create([
            'tenant_id'     => 'aaaaaaaa-0000-0000-0000-000000000001',
            'name'          => 'Active Client',
            'token_name'    => 'active-test',
            'source_system' => 'web_form',
            'is_active'     => true,
        ]);
        $token = $client->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/test-protected');

        $response->assertOk();
    }

    // -------------------------------------------------------------------------
    // User sin tenant_id
    // -------------------------------------------------------------------------

    public function test_user_without_tenant_id_returns_403_on_protected_routes(): void
    {
        $user  = User::factory()->create(['tenant_id' => null]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/test-protected');

        $response->assertForbidden()
            ->assertJsonPath('message', 'No tenant associated with this token.');
    }

    public function test_user_with_tenant_id_passes_protected_routes(): void
    {
        $user  = User::factory()->create(['tenant_id' => 'aaaaaaaa-0000-0000-0000-000000000001']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/test-protected');

        $response->assertOk();
    }
}
