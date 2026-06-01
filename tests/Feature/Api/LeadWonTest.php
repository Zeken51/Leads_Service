<?php

namespace Tests\Feature\Api;

class LeadWonTest extends LeadApiTestCase
{
    private function patchWon(string $token, string $leadId, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token)
            ->patchJson("/api/v1/leads/{$leadId}/won", $body);
    }

    // ── Autenticación y permisos ──────────────────────────────────────────────

    public function test_requires_authentication(): void
    {
        $lead = $this->createLead(self::TENANT_A);

        $this->patchJson("/api/v1/leads/{$lead->id}/won")
            ->assertUnauthorized();
    }

    public function test_requires_leads_won_ability(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchWon($token, $lead->id)
            ->assertForbidden();
    }

    // ── Marcar como ganado ────────────────────────────────────────────────────

    public function test_marks_lead_as_won(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchWon($token, $lead->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'won');

        $this->assertDatabaseHas('leads', [
            'id'     => $lead->id,
            'status' => 'won',
        ]);
        $this->assertNotNull($lead->fresh()->won_at);
    }

    public function test_accepts_custom_won_at(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);
        $date  = '2026-06-01T12:00:00Z';

        $this->patchWon($token, $lead->id, ['won_at' => $date])
            ->assertOk();

        $this->assertNotNull($lead->fresh()->won_at);
    }

    public function test_creates_lead_won_activity_log(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchWon($token, $lead->id)
            ->assertOk();

        $this->assertDatabaseHas('lead_activity_logs', [
            'lead_id'    => $lead->id,
            'event_type' => 'lead_won',
        ]);
    }

    public function test_moves_to_terminal_won_stage_if_exists(): void
    {
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A);
        $wonStage = $this->createWonStage(self::TENANT_A);

        $this->patchWon($token, $lead->id)
            ->assertOk()
            ->assertJsonPath('data.stage.id', $wonStage->id);
    }

    public function test_won_note_creates_lead_note(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchWon($token, $lead->id, ['note' => 'Cierre exitoso, cliente feliz.'])
            ->assertOk();

        $this->assertDatabaseHas('lead_notes', [
            'lead_id' => $lead->id,
            'content' => 'Cierre exitoso, cliente feliz.',
        ]);
    }

    public function test_already_won_lead_returns_422(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A, ['status' => 'won', 'won_at' => now()]);

        $this->patchWon($token, $lead->id)
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_lost_lead_cannot_be_marked_won(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A, [
            'status'      => 'lost',
            'lost_at'     => now(),
            'lost_reason' => 'Sin presupuesto',
        ]);

        $this->patchWon($token, $lead->id)
            ->assertUnprocessable();
    }

    // ── Tenant isolation ──────────────────────────────────────────────────────

    public function test_cannot_mark_won_lead_from_other_tenant(): void
    {
        $tokenA = $this->tokenForUser(self::TENANT_A);
        $leadB  = $this->createLead(self::TENANT_B);

        $this->patchWon($tokenA, $leadB->id)
            ->assertNotFound();
    }
}
