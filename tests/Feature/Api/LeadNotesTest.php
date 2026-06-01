<?php

namespace Tests\Feature\Api;

use App\Domain\Leads\Models\LeadNote;

class LeadNotesTest extends LeadApiTestCase
{
    // ── GET /leads/{id}/notes ─────────────────────────────────────────────────

    public function test_list_notes_requires_authentication(): void
    {
        $lead = $this->createLead(self::TENANT_A);

        $this->getJson("/api/v1/leads/{$lead->id}/notes")
            ->assertUnauthorized();
    }

    public function test_list_notes_returns_paginated_structure(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        LeadNote::create([
            'lead_id'              => $lead->id,
            'tenant_id'            => self::TENANT_A,
            'content'              => 'Nota de prueba',
            'author_id'            => 'agent-001',
            'author_name_snapshot' => 'Carlos Agente',
        ]);

        $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}/notes")
            ->assertOk()
            ->assertJsonStructure([
                'data'       => [['id', 'content', 'author' => ['id', 'name'], 'created_at']],
                'meta'       => ['current_page', 'per_page', 'total'],
                'request_id',
            ]);
    }

    public function test_list_notes_requires_leads_notes_read_ability(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:update']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}/notes")
            ->assertForbidden();
    }

    public function test_notes_are_ordered_by_date_desc(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        // Crear con tiempos separados para garantizar el orden
        LeadNote::forceCreate([
            'id'         => \Illuminate\Support\Str::uuid(),
            'lead_id'    => $lead->id,
            'tenant_id'  => self::TENANT_A,
            'content'    => 'Primera nota',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
        LeadNote::forceCreate([
            'id'         => \Illuminate\Support\Str::uuid(),
            'lead_id'    => $lead->id,
            'tenant_id'  => self::TENANT_A,
            'content'    => 'Nota más reciente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}/notes");

        $response->assertOk();
        $this->assertEquals('Nota más reciente', $response->json('data.0.content'));
    }

    public function test_cannot_list_notes_of_lead_from_other_tenant(): void
    {
        $tokenA = $this->tokenForUser(self::TENANT_A);
        $leadB  = $this->createLead(self::TENANT_B);

        $this->withToken($tokenA)
            ->getJson("/api/v1/leads/{$leadB->id}/notes")
            ->assertNotFound();
    }

    // ── POST /leads/{id}/notes ────────────────────────────────────────────────

    public function test_create_note_requires_authentication(): void
    {
        $lead = $this->createLead(self::TENANT_A);

        $this->postJson("/api/v1/leads/{$lead->id}/notes", ['content' => 'Nota'])
            ->assertUnauthorized();
    }

    public function test_create_note_requires_content(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->postJson("/api/v1/leads/{$lead->id}/notes", [])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['content']]);
    }

    public function test_create_note_requires_leads_notes_create_ability(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:read']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->postJson("/api/v1/leads/{$lead->id}/notes", ['content' => 'Nota'])
            ->assertForbidden();
    }

    public function test_creates_note_successfully(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->postJson("/api/v1/leads/{$lead->id}/notes", [
                'content'        => 'El cliente confirmó interés.',
                'author_user_id' => 'agent-123',
                'author_name'    => 'María Agente',
            ])
            ->assertCreated()
            ->assertJsonPath('data.content', 'El cliente confirmó interés.')
            ->assertJsonPath('data.author.id', 'agent-123')
            ->assertJsonPath('data.author.name', 'María Agente');

        $this->assertDatabaseHas('lead_notes', [
            'lead_id'              => $lead->id,
            'content'              => 'El cliente confirmó interés.',
            'author_id'            => 'agent-123',
            'author_name_snapshot' => 'María Agente',
        ]);
    }

    public function test_create_note_does_not_update_last_contact_at(): void
    {
        // Las notas son registros internos del agente, no señales de contacto real.
        // Para registrar contacto con el cliente, usar POST /contact.
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A, ['last_contact_at' => null]);

        $this->withToken($token)
            ->postJson("/api/v1/leads/{$lead->id}/notes", ['content' => 'Nota de seguimiento'])
            ->assertCreated();

        $this->assertNull($lead->fresh()->last_contact_at);
    }

    public function test_create_note_creates_activity_log(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->postJson("/api/v1/leads/{$lead->id}/notes", ['content' => 'Nota'])
            ->assertCreated();

        $this->assertDatabaseHas('lead_activity_logs', [
            'lead_id'    => $lead->id,
            'event_type' => 'note_added',
        ]);
    }

    public function test_cannot_add_note_to_lead_from_other_tenant(): void
    {
        $tokenA = $this->tokenForUser(self::TENANT_A);
        $leadB  = $this->createLead(self::TENANT_B);

        $this->withToken($tokenA)
            ->postJson("/api/v1/leads/{$leadB->id}/notes", ['content' => 'Nota'])
            ->assertNotFound();
    }
}
