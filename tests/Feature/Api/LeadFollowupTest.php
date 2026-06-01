<?php

namespace Tests\Feature\Api;

class LeadFollowupTest extends LeadApiTestCase
{
    private function patchFollowup(string $token, string $leadId, array $body): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token)
            ->patchJson("/api/v1/leads/{$leadId}/followup", $body);
    }

    // ── Validación ────────────────────────────────────────────────────────────

    public function test_requires_at_least_one_field(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchFollowup($token, $lead->id, [])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['next_action']]);
    }

    public function test_requires_authentication(): void
    {
        $lead = $this->createLead(self::TENANT_A);

        $this->patchJson("/api/v1/leads/{$lead->id}/followup", ['next_action' => 'Llamar'])
            ->assertUnauthorized();
    }

    public function test_requires_leads_followup_ability(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchFollowup($token, $lead->id, ['next_action' => 'Llamar'])
            ->assertForbidden();
    }

    // ── Actualización exitosa ─────────────────────────────────────────────────

    public function test_updates_next_action(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchFollowup($token, $lead->id, ['next_action' => 'Enviar propuesta'])
            ->assertOk()
            ->assertJsonPath('data.next_action', 'Enviar propuesta');
    }

    public function test_updates_followup_at(): void
    {
        // followup_at requiere next_action para describir qué se hará en esa fecha
        $token     = $this->tokenForUser();
        $lead      = $this->createLead(self::TENANT_A);
        $futureDate = now()->addDays(3)->toISOString();

        $this->patchFollowup($token, $lead->id, [
            'next_action' => 'Llamar para confirmar',
            'followup_at' => $futureDate,
        ])->assertOk();

        $this->assertNotNull($lead->fresh()->followup_at);
    }

    public function test_followup_at_without_next_action_returns_422(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchFollowup($token, $lead->id, ['followup_at' => now()->addDay()->toISOString()])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['next_action']]);
    }

    public function test_updates_both_fields(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchFollowup($token, $lead->id, [
            'next_action' => 'Llamar',
            'followup_at' => now()->addDay()->toISOString(),
        ])->assertOk()
            ->assertJsonPath('data.next_action', 'Llamar');
    }

    public function test_creates_activity_log(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchFollowup($token, $lead->id, ['next_action' => 'Enviar email'])
            ->assertOk();

        $this->assertDatabaseHas('lead_activity_logs', [
            'lead_id'    => $lead->id,
            'event_type' => 'followup_scheduled',
        ]);
    }

    // ── Tenant isolation ──────────────────────────────────────────────────────

    public function test_cannot_update_followup_of_lead_from_another_tenant(): void
    {
        $tokenA = $this->tokenForUser(self::TENANT_A);
        $leadB  = $this->createLead(self::TENANT_B);

        $this->patchFollowup($tokenA, $leadB->id, ['next_action' => 'Test'])
            ->assertNotFound();
    }
}
