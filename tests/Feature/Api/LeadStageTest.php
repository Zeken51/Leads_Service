<?php

namespace Tests\Feature\Api;

class LeadStageTest extends LeadApiTestCase
{
    private function patchStage(string $token, string $leadId, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token)
            ->patchJson("/api/v1/leads/{$leadId}/stage", $body);
    }

    // ── Autenticación y permisos ──────────────────────────────────────────────

    public function test_requires_authentication(): void
    {
        $lead = $this->createLead(self::TENANT_A);
        $stage = $this->createInitialStage(self::TENANT_A);

        $this->patchJson("/api/v1/leads/{$lead->id}/stage", ['stage_id' => $stage->id])
            ->assertUnauthorized();
    }

    public function test_requires_leads_update_ability(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);
        $stage = $this->createStage(self::TENANT_A);

        $this->patchStage($token, $lead->id, ['stage_id' => $stage->id])
            ->assertForbidden();
    }

    // ── Validación ────────────────────────────────────────────────────────────

    public function test_stage_id_is_required(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchStage($token, $lead->id, [])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['stage_id']]);
    }

    public function test_stage_from_another_tenant_returns_422(): void
    {
        $token      = $this->tokenForUser(self::TENANT_A);
        $lead       = $this->createLead(self::TENANT_A);
        $otherStage = $this->createStage(self::TENANT_B);

        $this->patchStage($token, $lead->id, ['stage_id' => $otherStage->id])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['stage_id']]);
    }

    public function test_nonexistent_stage_returns_422(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->patchStage($token, $lead->id, ['stage_id' => fake()->uuid()])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['stage_id']]);
    }

    // ── Cambio de stage exitoso ───────────────────────────────────────────────

    public function test_stage_change_updates_stage_id(): void
    {
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A);
        $newStage = $this->createStage(self::TENANT_A, ['order' => 2]);

        $response = $this->patchStage($token, $lead->id, ['stage_id' => $newStage->id]);

        $response->assertOk()
            ->assertJsonPath('data.stage.id', $newStage->id);

        $this->assertDatabaseHas('leads', [
            'id'       => $lead->id,
            'stage_id' => $newStage->id,
        ]);
    }

    public function test_stage_change_creates_activity_log(): void
    {
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A);
        $newStage = $this->createStage(self::TENANT_A);

        $this->patchStage($token, $lead->id, ['stage_id' => $newStage->id])
            ->assertOk();

        $this->assertDatabaseHas('lead_activity_logs', [
            'lead_id'    => $lead->id,
            'event_type' => 'stage_changed',
        ]);
    }

    public function test_stage_change_does_not_update_last_contact_at(): void
    {
        // Cambiar stage es gestión del pipeline, no un contacto con el cliente.
        // last_contact_at solo se actualiza en /contact.
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A, ['last_contact_at' => null]);
        $newStage = $this->createStage(self::TENANT_A);

        $this->patchStage($token, $lead->id, ['stage_id' => $newStage->id])
            ->assertOk();

        $this->assertNull($lead->fresh()->last_contact_at);
    }

    public function test_stage_change_accepts_next_action_and_followup(): void
    {
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A);
        $newStage = $this->createStage(self::TENANT_A);

        $this->patchStage($token, $lead->id, [
            'stage_id'    => $newStage->id,
            'next_action' => 'Llamar mañana',
            'followup_at' => now()->addDay()->toISOString(),
        ])->assertOk();

        $this->assertDatabaseHas('leads', [
            'id'          => $lead->id,
            'next_action' => 'Llamar mañana',
        ]);
    }

    // ── Stage terminal: ganado ────────────────────────────────────────────────

    public function test_moving_to_won_terminal_stage_sets_status_won(): void
    {
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A);
        $wonStage = $this->createWonStage(self::TENANT_A);

        $this->patchStage($token, $lead->id, ['stage_id' => $wonStage->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'won');

        $this->assertDatabaseHas('leads', [
            'id'     => $lead->id,
            'status' => 'won',
        ]);
        $this->assertNotNull($lead->fresh()->won_at);
    }

    public function test_moving_to_won_stage_creates_lead_won_log(): void
    {
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A);
        $wonStage = $this->createWonStage(self::TENANT_A);

        $this->patchStage($token, $lead->id, ['stage_id' => $wonStage->id])
            ->assertOk();

        $this->assertDatabaseHas('lead_activity_logs', [
            'lead_id'    => $lead->id,
            'event_type' => 'lead_won',
        ]);
        $this->assertDatabaseHas('lead_activity_logs', [
            'lead_id'    => $lead->id,
            'event_type' => 'stage_changed',
        ]);
    }

    // ── Stage terminal: perdido ───────────────────────────────────────────────

    public function test_moving_to_lost_terminal_stage_requires_lost_reason(): void
    {
        $token     = $this->tokenForUser();
        $lead      = $this->createLead(self::TENANT_A);
        $lostStage = $this->createLostStage(self::TENANT_A);

        $this->patchStage($token, $lead->id, ['stage_id' => $lostStage->id])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['lost_reason']]);
    }

    public function test_moving_to_lost_stage_sets_status_lost(): void
    {
        $token     = $this->tokenForUser();
        $lead      = $this->createLead(self::TENANT_A);
        $lostStage = $this->createLostStage(self::TENANT_A);

        $this->patchStage($token, $lead->id, [
            'stage_id'    => $lostStage->id,
            'lost_reason' => 'Cliente no tiene presupuesto.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'lost');

        $this->assertDatabaseHas('leads', [
            'id'          => $lead->id,
            'status'      => 'lost',
            'lost_reason' => 'Cliente no tiene presupuesto.',
        ]);
    }

    // ── Leads cerrados no se pueden mover ─────────────────────────────────────

    public function test_closed_won_lead_cannot_change_stage(): void
    {
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A, ['status' => 'won', 'won_at' => now()]);
        $newStage = $this->createStage(self::TENANT_A);

        $this->patchStage($token, $lead->id, ['stage_id' => $newStage->id])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['stage_id']]);
    }

    public function test_closed_lost_lead_cannot_change_stage(): void
    {
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A, [
            'status'      => 'lost',
            'lost_at'     => now(),
            'lost_reason' => 'Sin interés',
        ]);
        $newStage = $this->createStage(self::TENANT_A);

        $this->patchStage($token, $lead->id, ['stage_id' => $newStage->id])
            ->assertUnprocessable();
    }

    // ── Tenant isolation ──────────────────────────────────────────────────────

    public function test_cannot_update_stage_of_lead_from_another_tenant(): void
    {
        $tokenA   = $this->tokenForUser(self::TENANT_A);
        $leadB    = $this->createLead(self::TENANT_B);
        $stageA   = $this->createStage(self::TENANT_A);

        $this->patchStage($tokenA, $leadB->id, ['stage_id' => $stageA->id])
            ->assertNotFound();
    }
}
