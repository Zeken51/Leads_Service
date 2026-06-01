<?php

namespace Tests\Feature\Api;

class LeadContactTest extends LeadApiTestCase
{
    private function postContact(string $token, string $leadId, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token)
            ->postJson("/api/v1/leads/{$leadId}/contact", $body);
    }

    // ── Autenticación y permisos ──────────────────────────────────────────────

    public function test_requires_authentication(): void
    {
        $lead = $this->createLead(self::TENANT_A);

        $this->postJson("/api/v1/leads/{$lead->id}/contact")
            ->assertUnauthorized();
    }

    public function test_requires_leads_contact_ability(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->postContact($token, $lead->id)
            ->assertForbidden();
    }

    // ── Contacto exitoso ──────────────────────────────────────────────────────

    public function test_contact_updates_last_contact_at(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->postContact($token, $lead->id, ['contact_channel' => 'phone'])
            ->assertOk();

        $this->assertNotNull($lead->fresh()->last_contact_at);
    }

    public function test_contact_with_empty_body_is_valid(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->postContact($token, $lead->id, [])
            ->assertOk();
    }

    public function test_contact_creates_activity_log_with_channel(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->postContact($token, $lead->id, ['contact_channel' => 'whatsapp'])
            ->assertOk();

        $this->assertDatabaseHas('lead_activity_logs', [
            'lead_id'         => $lead->id,
            'event_type'      => 'contact_registered',
            'contact_channel' => 'whatsapp',
        ]);
    }

    public function test_contact_with_notes_creates_lead_note(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->postContact($token, $lead->id, [
            'contact_notes' => 'Cliente confirmó interés para julio.',
        ])->assertOk();

        $this->assertDatabaseHas('lead_notes', [
            'lead_id' => $lead->id,
            'content' => 'Cliente confirmó interés para julio.',
        ]);
    }

    public function test_contact_updates_next_action_and_followup(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->postContact($token, $lead->id, [
            'next_action' => 'Enviar propuesta',
            'followup_at' => now()->addDays(2)->toISOString(),
        ])->assertOk()
            ->assertJsonPath('data.next_action', 'Enviar propuesta');
    }

    public function test_invalid_contact_channel_returns_422(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->postContact($token, $lead->id, ['contact_channel' => 'telegram'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['contact_channel']]);
    }

    // ── Tenant isolation ──────────────────────────────────────────────────────

    public function test_cannot_register_contact_for_lead_from_other_tenant(): void
    {
        $tokenA = $this->tokenForUser(self::TENANT_A);
        $leadB  = $this->createLead(self::TENANT_B);

        $this->postContact($tokenA, $leadB->id, ['contact_channel' => 'phone'])
            ->assertNotFound();
    }
}
