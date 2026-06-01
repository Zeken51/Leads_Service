<?php

namespace Tests\Feature\Api;

class LeadLostTest extends LeadApiTestCase
{
    private function patchLost(string $token, string $leadId, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token)
            ->patchJson("/api/v1/leads/{$leadId}/lost", $body);
    }

    // ── Autenticación y permisos ──────────────────────────────────────────────

    public function test_requires_authentication(): void
    {
        $lead = $this->createLead(self::TENANT_A);

        $this->patchJson("/api/v1/leads/{$lead->id}/lost", ['lost_reason' => 'Test'])
            ->assertUnauthorized();
    }

    public function test_requires_leads_lost_ability(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchLost($token, $lead->id, ['lost_reason' => 'Sin presupuesto'])
            ->assertForbidden();
    }

    // ── Validación ────────────────────────────────────────────────────────────

    public function test_lost_reason_is_required(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchLost($token, $lead->id, [])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['lost_reason']]);
    }

    // ── Marcar como perdido ───────────────────────────────────────────────────

    public function test_marks_lead_as_lost(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchLost($token, $lead->id, ['lost_reason' => 'Cliente eligió a la competencia.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'lost')
            ->assertJsonPath('data.lost_reason', 'Cliente eligió a la competencia.');

        $this->assertDatabaseHas('leads', [
            'id'          => $lead->id,
            'status'      => 'lost',
            'lost_reason' => 'Cliente eligió a la competencia.',
        ]);
        $this->assertNotNull($lead->fresh()->lost_at);
    }

    public function test_accepts_custom_lost_at(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchLost($token, $lead->id, [
            'lost_reason' => 'Sin interés',
            'lost_at'     => '2026-06-01T10:00:00Z',
        ])->assertOk();

        $this->assertNotNull($lead->fresh()->lost_at);
    }

    public function test_creates_lead_lost_activity_log(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchLost($token, $lead->id, ['lost_reason' => 'Sin budget'])
            ->assertOk();

        $this->assertDatabaseHas('lead_activity_logs', [
            'lead_id'    => $lead->id,
            'event_type' => 'lead_lost',
        ]);
    }

    public function test_moves_to_terminal_lost_stage_if_exists(): void
    {
        $token     = $this->tokenForUser();
        $lead      = $this->createLead(self::TENANT_A);
        $lostStage = $this->createLostStage(self::TENANT_A);

        $this->patchLost($token, $lead->id, ['lost_reason' => 'Sin presupuesto'])
            ->assertOk()
            ->assertJsonPath('data.stage.id', $lostStage->id);
    }

    public function test_already_lost_lead_returns_422(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A, [
            'status'      => 'lost',
            'lost_at'     => now(),
            'lost_reason' => 'Sin presupuesto',
        ]);

        $this->patchLost($token, $lead->id, ['lost_reason' => 'Otro motivo'])
            ->assertUnprocessable();
    }

    public function test_won_lead_cannot_be_marked_lost(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A, ['status' => 'won', 'won_at' => now()]);

        $this->patchLost($token, $lead->id, ['lost_reason' => 'Test'])
            ->assertUnprocessable();
    }

    // ── Tenant isolation ──────────────────────────────────────────────────────

    public function test_cannot_mark_lost_lead_from_other_tenant(): void
    {
        $tokenA = $this->tokenForUser(self::TENANT_A);
        $leadB  = $this->createLead(self::TENANT_B);

        $this->patchLost($tokenA, $leadB->id, ['lost_reason' => 'Test'])
            ->assertNotFound();
    }
}
