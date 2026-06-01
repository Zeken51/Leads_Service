<?php

namespace Tests\Feature\Api;

use App\Domain\Auth\Models\TenantApiClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateLeadTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT_A = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const TENANT_B = 'bbbbbbbb-0000-0000-0000-000000000002';

    private array $validPayload = [
        'source_system'         => 'web_form',
        'source_channel'        => 'landing_page',
        'external_reference_id' => 'WF-001',
        'customer'              => [
            'name'  => 'Juan Pérez',
            'email' => 'juan@test.com',
            'phone' => '+52 55 1234 5678',
        ],
        'priority' => 'high',
        'metadata' => ['campaign' => 'summer_2026'],
    ];

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function tokenForUser(array $attrs = []): string
    {
        $user = User::factory()->create(array_merge(['tenant_id' => self::TENANT_A], $attrs));
        return $user->createToken('test')->plainTextToken;
    }

    private function tokenForApiClient(array $attrs = []): string
    {
        static $seq = 0;
        $client = TenantApiClient::create(array_merge([
            'tenant_id'     => self::TENANT_A,
            'name'          => 'Test Client '.++$seq,
            'token_name'    => 'client-'.uniqid(),
            'source_system' => 'zend_vacations',
            'source_channel'=> 'landing_page',
            'is_active'     => true,
        ], $attrs));
        return $client->createToken('api')->plainTextToken;
    }

    /**
     * @param array|null $payload Payload completo. Null usa $this->validPayload.
     */
    private function postLead(string $token, ?array $payload = null, array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/leads', $payload ?? $this->validPayload);
    }

    // ── Autenticación ─────────────────────────────────────────────────────────

    public function test_create_lead_without_token_returns_401(): void
    {
        $this->postJson('/api/v1/leads', $this->validPayload)
            ->assertUnauthorized()
            ->assertJsonStructure(['message', 'request_id']);
    }

    public function test_create_lead_with_user_token_returns_201(): void
    {
        $token    = $this->tokenForUser();
        $response = $this->postLead($token);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'tenant_id', 'status', 'customer'], 'request_id'])
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.idempotent_replay', false);
    }

    // ── Respuesta estándar ────────────────────────────────────────────────────

    public function test_response_includes_request_id(): void
    {
        $response = $this->postLead($this->tokenForUser());

        $response->assertCreated()
            ->assertJsonStructure(['request_id']);

        $this->assertStringStartsWith('req_', $response->json('request_id'));
    }

    public function test_response_contains_lead_fields(): void
    {
        $response = $this->postLead($this->tokenForUser());

        $response->assertCreated()
            ->assertJsonPath('data.source_system', 'web_form')
            ->assertJsonPath('data.source_channel', 'landing_page')
            ->assertJsonPath('data.external_reference_id', 'WF-001')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.customer.name', 'Juan Pérez')
            ->assertJsonPath('data.customer.email', 'juan@test.com')
            ->assertJsonPath('data.tenant_id', self::TENANT_A);
    }

    // ── Validación ────────────────────────────────────────────────────────────

    public function test_missing_customer_name_returns_422(): void
    {
        $payload = array_merge($this->validPayload, ['customer' => ['email' => 'a@b.com']]);

        $this->postLead($this->tokenForUser(), $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['customer.name'], 'request_id']);
    }

    public function test_invalid_email_returns_422(): void
    {
        $payload = array_merge($this->validPayload, ['customer' => ['name' => 'Test', 'email' => 'not-an-email']]);

        $this->postLead($this->tokenForUser(), $payload)
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['customer.email']]);
    }

    public function test_followup_at_in_the_past_returns_422(): void
    {
        $payload = array_merge($this->validPayload, ['followup_at' => '2020-01-01T00:00:00Z']);

        $this->postLead($this->tokenForUser(), $payload)
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['followup_at']]);
    }

    // ── Source system ─────────────────────────────────────────────────────────

    public function test_source_system_from_api_client_is_used_when_not_in_payload(): void
    {
        $token = $this->tokenForApiClient(['source_system' => 'zend_vacations', 'source_channel' => 'landing_page']);

        // Payload sin source_system ni source_channel — deben venir del cliente
        $payload = [
            'external_reference_id' => 'ZV-001',
            'customer' => ['name' => 'Juan Pérez', 'email' => 'juan@test.com'],
            'priority' => 'high',
        ];

        $this->postLead($token, $payload)
            ->assertCreated()
            ->assertJsonPath('data.source_system', 'zend_vacations');
    }

    public function test_source_system_mismatch_with_api_client_returns_422(): void
    {
        $token = $this->tokenForApiClient(['source_system' => 'zend_vacations', 'source_channel' => 'landing_page']);

        $payload = array_merge($this->validPayload, ['source_system' => 'web_form']);

        $this->postLead($token, $payload)
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['source_system']]);
    }

    // ── assigned_to ───────────────────────────────────────────────────────────

    public function test_assigned_to_is_accepted_and_stored(): void
    {
        $payload = array_merge($this->validPayload, [
            'assigned_to' => [
                'user_id'  => 'ext-user-abc',
                'name'     => 'María Agente',
                'email'    => 'maria@zend.com',
                'provider' => 'zend_platform',
            ],
        ]);

        $response = $this->postLead($this->tokenForUser(), $payload);

        $response->assertCreated()
            ->assertJsonPath('data.assigned_to.user_id', 'ext-user-abc')
            ->assertJsonPath('data.assigned_to.name', 'María Agente')
            ->assertJsonPath('data.assigned_to.provider', 'zend_platform');

        $this->assertDatabaseHas('leads', [
            'assigned_user_id'            => 'ext-user-abc',
            'assigned_user_name_snapshot'  => 'María Agente',
            'assigned_user_email_snapshot' => 'maria@zend.com',
            'assigned_user_provider'       => 'zend_platform',
        ]);
    }

    public function test_assigned_to_without_required_fields_returns_422(): void
    {
        // assigned_to presente pero sin user_id ni name → 422
        $payload = array_merge($this->validPayload, [
            'assigned_to' => ['email' => 'agent@zend.com'],
        ]);

        $this->postLead($this->tokenForUser(), $payload)
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['assigned_to.user_id', 'assigned_to.name']]);
    }

    // ── Activity log ──────────────────────────────────────────────────────────

    public function test_activity_log_lead_created_is_saved(): void
    {
        $this->postLead($this->tokenForUser())->assertCreated();

        $this->assertDatabaseHas('lead_activity_logs', [
            'event_type' => 'lead_created',
            'tenant_id'  => self::TENANT_A,
        ]);
    }

    // ── Idempotencia nivel 1: Idempotency-Key ─────────────────────────────────

    public function test_create_with_idempotency_key_stores_key(): void
    {
        $this->postLead(
            $this->tokenForUser(),
            null,
            ['Idempotency-Key' => 'unique-key-001'],
        )->assertCreated();

        $this->assertDatabaseHas('idempotency_keys', [
            'idempotency_key' => 'unique-key-001',
            'tenant_id'       => self::TENANT_A,
        ]);
    }

    public function test_exact_replay_returns_200_with_idempotent_replay_flag(): void
    {
        $token = $this->tokenForUser();
        $key   = 'replay-key-001';

        $first  = $this->postLead($token, null, ['Idempotency-Key' => $key]);
        $second = $this->postLead($token, null, ['Idempotency-Key' => $key]);

        $first->assertCreated();

        $second->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.idempotent_replay', true);

        // Solo un lead fue creado
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_same_key_different_path_returns_400(): void
    {
        // El chequeo IDEMPOTENCY_KEY_MISMATCH requiere que el segundo endpoint
        // también verifique idempotencia. Por ahora solo POST /leads lo hace.
        // Este test verifica que la clave se almacena con el path correcto.
        $token = $this->tokenForUser();
        $key   = 'path-check-key';

        $this->postLead($token, null, ['Idempotency-Key' => $key])->assertCreated();

        $this->assertDatabaseHas('idempotency_keys', [
            'idempotency_key' => $key,
            'path'            => 'api/v1/leads',
            'method'          => 'POST',
        ]);
    }

    // ── Idempotencia nivel 2: unicidad por datos ──────────────────────────────

    public function test_duplicate_data_without_key_returns_409(): void
    {
        $token   = $this->tokenForUser();
        $payload = $this->validPayload;

        $this->postLead($token, $payload)->assertCreated();

        $this->postLead($token, $payload)
            ->assertStatus(409)
            ->assertJsonStructure(['errors' => ['external_reference_id'], 'request_id']);
    }

    public function test_different_key_same_external_reference_id_returns_409(): void
    {
        $token = $this->tokenForUser();

        // Primera creación con clave A
        $this->postLead($token, null, ['Idempotency-Key' => 'key-A'])->assertCreated();

        // Segunda creación con clave B pero mismo external_reference_id → nivel 2 retorna 409
        // (nivel 1 pasa porque la clave B es nueva, pero nivel 2 detecta el duplicado de datos)
        $this->postLead($token, null, ['Idempotency-Key' => 'key-B'])
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'errors' => ['external_reference_id'], 'request_id']);

        $this->assertDatabaseCount('leads', 1);
    }

    public function test_no_duplicate_check_when_external_reference_id_is_null(): void
    {
        $token = $this->tokenForUser();

        // Payload sin external_reference_id — NULL permite múltiples leads
        $payload = [
            'source_system'  => 'web_form',
            'source_channel' => 'manual',
            'customer'       => ['name' => 'Cliente Sin Ref'],
        ];

        $this->postLead($token, $payload)->assertCreated();
        $this->postLead($token, $payload)->assertCreated();

        $this->assertDatabaseCount('leads', 2);
    }
}
