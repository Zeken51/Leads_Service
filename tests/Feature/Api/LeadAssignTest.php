<?php

namespace Tests\Feature\Api;

class LeadAssignTest extends LeadApiTestCase
{
    private function patchAssign(string $token, string $leadId, array $body): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token)
            ->patchJson("/api/v1/leads/{$leadId}/assign", $body);
    }

    private array $validAssign = [
        'user_id'  => 'ext-agent-abc',
        'name'     => 'Carlos Agente',
        'email'    => 'carlos@zend.com',
        'provider' => 'zend_platform',
    ];

    // ── Autenticación y permisos ──────────────────────────────────────────────

    public function test_requires_authentication(): void
    {
        $lead = $this->createLead(self::TENANT_A);

        $this->patchJson("/api/v1/leads/{$lead->id}/assign", $this->validAssign)
            ->assertUnauthorized();
    }

    public function test_requires_leads_assign_ability(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchAssign($token, $lead->id, $this->validAssign)
            ->assertForbidden();
    }

    // ── Validación ────────────────────────────────────────────────────────────

    public function test_user_id_is_required(): void
    {
        $token   = $this->tokenForUser();
        $lead    = $this->createLead(self::TENANT_A);
        $payload = array_merge($this->validAssign, ['user_id' => null]);

        $this->patchAssign($token, $lead->id, $payload)
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['user_id']]);
    }

    public function test_name_is_required(): void
    {
        $token   = $this->tokenForUser();
        $lead    = $this->createLead(self::TENANT_A);
        $payload = ['user_id' => 'agent-001'];

        $this->patchAssign($token, $lead->id, $payload)
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['name']]);
    }

    // ── Asignación exitosa ────────────────────────────────────────────────────

    public function test_assigns_user_snapshots_to_lead(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchAssign($token, $lead->id, $this->validAssign)
            ->assertOk()
            ->assertJsonPath('data.assigned_to.user_id', 'ext-agent-abc')
            ->assertJsonPath('data.assigned_to.name', 'Carlos Agente')
            ->assertJsonPath('data.assigned_to.provider', 'zend_platform');

        $this->assertDatabaseHas('leads', [
            'id'                           => $lead->id,
            'assigned_user_id'             => 'ext-agent-abc',
            'assigned_user_name_snapshot'  => 'Carlos Agente',
            'assigned_user_email_snapshot' => 'carlos@zend.com',
            'assigned_user_provider'       => 'zend_platform',
        ]);
    }

    public function test_assign_creates_activity_log(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchAssign($token, $lead->id, $this->validAssign)
            ->assertOk();

        $this->assertDatabaseHas('lead_activity_logs', [
            'lead_id'    => $lead->id,
            'event_type' => 'lead_assigned',
        ]);
    }

    public function test_assign_works_without_optional_fields(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchAssign($token, $lead->id, [
            'user_id' => 'agent-minimal',
            'name'    => 'Agente Mínimo',
        ])->assertOk()
            ->assertJsonPath('data.assigned_to.user_id', 'agent-minimal');
    }

    // ── Tenant isolation ──────────────────────────────────────────────────────

    public function test_cannot_assign_lead_from_another_tenant(): void
    {
        $tokenA = $this->tokenForUser(self::TENANT_A);
        $leadB  = $this->createLead(self::TENANT_B);

        $this->patchAssign($tokenA, $leadB->id, $this->validAssign)
            ->assertNotFound();
    }
}
