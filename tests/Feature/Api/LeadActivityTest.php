<?php

namespace Tests\Feature\Api;

use App\Domain\Leads\Models\LeadActivityLog;

class LeadActivityTest extends LeadApiTestCase
{
    // ── Autenticación y permisos ──────────────────────────────────────────────

    public function test_requires_authentication(): void
    {
        $lead = $this->createLead(self::TENANT_A);

        $this->getJson("/api/v1/leads/{$lead->id}/activity")
            ->assertUnauthorized();
    }

    public function test_requires_leads_activity_read_ability(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:update']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}/activity")
            ->assertForbidden();
    }

    // ── Lista paginada ────────────────────────────────────────────────────────

    public function test_returns_paginated_activity_logs(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        LeadActivityLog::create([
            'lead_id'     => $lead->id,
            'tenant_id'   => self::TENANT_A,
            'event_type'  => 'lead_created',
            'description' => 'Lead creado.',
            'causer_type' => 'api_client',
        ]);

        $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}/activity")
            ->assertOk()
            ->assertJsonStructure([
                'data'       => [['id', 'event', 'description', 'payload', 'causer', 'created_at']],
                'meta'       => ['current_page', 'per_page', 'total'],
                'request_id',
            ]);
    }

    public function test_activity_event_field_maps_event_type(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        LeadActivityLog::create([
            'lead_id'     => $lead->id,
            'tenant_id'   => self::TENANT_A,
            'event_type'  => 'stage_changed',
            'description' => 'Etapa cambiada.',
            'causer_type' => 'user',
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}/activity");

        $response->assertOk();
        $this->assertEquals('stage_changed', $response->json('data.0.event'));
    }

    public function test_can_filter_by_event_type(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        LeadActivityLog::create([
            'lead_id'     => $lead->id,
            'tenant_id'   => self::TENANT_A,
            'event_type'  => 'lead_created',
            'description' => 'Lead creado.',
            'causer_type' => 'api_client',
        ]);
        LeadActivityLog::create([
            'lead_id'     => $lead->id,
            'tenant_id'   => self::TENANT_A,
            'event_type'  => 'stage_changed',
            'description' => 'Etapa cambiada.',
            'causer_type' => 'user',
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}/activity?event=stage_changed");

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('stage_changed', $response->json('data.0.event'));
    }

    public function test_activity_is_ordered_by_date_desc(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        LeadActivityLog::forceCreate([
            'id'          => \Illuminate\Support\Str::uuid(),
            'lead_id'     => $lead->id,
            'tenant_id'   => self::TENANT_A,
            'event_type'  => 'lead_created',
            'description' => 'Primero',
            'causer_type' => 'system',
            'created_at'  => now()->subHour(),
        ]);
        LeadActivityLog::forceCreate([
            'id'          => \Illuminate\Support\Str::uuid(),
            'lead_id'     => $lead->id,
            'tenant_id'   => self::TENANT_A,
            'event_type'  => 'lead_updated',
            'description' => 'Último',
            'causer_type' => 'system',
            'created_at'  => now(),
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}/activity");

        $response->assertOk();
        $this->assertEquals('Último', $response->json('data.0.description'));
    }

    // ── Tenant isolation ──────────────────────────────────────────────────────

    public function test_cannot_view_activity_of_lead_from_other_tenant(): void
    {
        $tokenA = $this->tokenForUser(self::TENANT_A);
        $leadB  = $this->createLead(self::TENANT_B);

        $this->withToken($tokenA)
            ->getJson("/api/v1/leads/{$leadB->id}/activity")
            ->assertNotFound();
    }
}
