<?php

namespace Tests\Feature\Api;

/**
 * Valida que cada endpoint de gestión de leads use la ability correcta según contrato.
 *
 * Tabla de abilities documentada:
 *   GET    /leads             → leads:read
 *   GET    /leads/{id}        → leads:read
 *   PATCH  /leads/{id}/stage  → leads:update
 *   PATCH  /leads/{id}/assign → leads:assign
 *   PATCH  /leads/{id}/followup → leads:update
 *   POST   /leads/{id}/contact  → leads:update
 *   PATCH  /leads/{id}/won    → leads:update
 *   PATCH  /leads/{id}/lost   → leads:update
 *   GET    /leads/{id}/notes  → leads:read
 *   POST   /leads/{id}/notes  → leads:notes:create
 *   GET    /leads/{id}/activity → leads:read
 */
class LeadAbilitiesTest extends LeadApiTestCase
{
    // ── GET /leads necesita leads:read ────────────────────────────────────────

    public function test_list_leads_requires_leads_read(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:update']);

        $this->withToken($token)->getJson('/api/v1/leads')->assertForbidden();
    }

    public function test_list_leads_allowed_with_leads_read(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);

        $this->withToken($token)->getJson('/api/v1/leads')->assertOk();
    }

    // ── GET /leads/{id} necesita leads:read ──────────────────────────────────

    public function test_show_lead_requires_leads_read(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:update']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)->getJson("/api/v1/leads/{$lead->id}")->assertForbidden();
    }

    public function test_show_lead_allowed_with_leads_read(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)->getJson("/api/v1/leads/{$lead->id}")->assertOk();
    }

    // ── PATCH /stage necesita leads:update ───────────────────────────────────

    public function test_update_stage_requires_leads_update(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);
        $stage = $this->createStage(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/stage", ['stage_id' => $stage->id])
            ->assertForbidden();
    }

    public function test_update_stage_allowed_with_leads_update(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:update']);
        $lead  = $this->createLead(self::TENANT_A);
        $stage = $this->createStage(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/stage", ['stage_id' => $stage->id])
            ->assertOk();
    }

    // ── PATCH /assign necesita leads:assign ──────────────────────────────────

    public function test_assign_requires_leads_assign(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:update']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/assign", ['user_id' => 'x', 'name' => 'X'])
            ->assertForbidden();
    }

    public function test_assign_allowed_with_leads_assign(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:assign']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/assign", ['user_id' => 'x', 'name' => 'X'])
            ->assertOk();
    }

    // ── PATCH /followup necesita leads:update ────────────────────────────────

    public function test_followup_requires_leads_update(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/followup", ['next_action' => 'Test'])
            ->assertForbidden();
    }

    public function test_followup_allowed_with_leads_update(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:update']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/followup", ['next_action' => 'Llamar'])
            ->assertOk();
    }

    // ── POST /contact necesita leads:update ──────────────────────────────────

    public function test_contact_requires_leads_update(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->postJson("/api/v1/leads/{$lead->id}/contact", [])
            ->assertForbidden();
    }

    public function test_contact_allowed_with_leads_update(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:update']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->postJson("/api/v1/leads/{$lead->id}/contact", [])
            ->assertOk();
    }

    // ── PATCH /won necesita leads:update ─────────────────────────────────────

    public function test_won_requires_leads_update(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/won", [])
            ->assertForbidden();
    }

    public function test_won_allowed_with_leads_update(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:update']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/won", [])
            ->assertOk();
    }

    // ── PATCH /lost necesita leads:update ────────────────────────────────────

    public function test_lost_requires_leads_update(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/lost", ['lost_reason' => 'Test'])
            ->assertForbidden();
    }

    public function test_lost_allowed_with_leads_update(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:update']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/lost", ['lost_reason' => 'Sin presupuesto'])
            ->assertOk();
    }

    // ── GET /notes necesita leads:read ────────────────────────────────────────

    public function test_list_notes_requires_leads_read(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:update']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}/notes")
            ->assertForbidden();
    }

    public function test_list_notes_allowed_with_leads_read(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}/notes")
            ->assertOk();
    }

    // ── POST /notes necesita leads:notes:create ───────────────────────────────

    public function test_create_note_requires_leads_notes_create(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->postJson("/api/v1/leads/{$lead->id}/notes", ['content' => 'Nota'])
            ->assertForbidden();
    }

    public function test_create_note_allowed_with_leads_notes_create(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:notes:create']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->postJson("/api/v1/leads/{$lead->id}/notes", ['content' => 'Nota de prueba'])
            ->assertCreated();
    }

    // ── GET /activity necesita leads:read ─────────────────────────────────────

    public function test_list_activity_requires_leads_read(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:update']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}/activity")
            ->assertForbidden();
    }

    public function test_list_activity_allowed_with_leads_read(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}/activity")
            ->assertOk();
    }
}
