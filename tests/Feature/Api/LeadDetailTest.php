<?php

namespace Tests\Feature\Api;

use App\Domain\Leads\Models\LeadActivityLog;
use App\Domain\Leads\Models\LeadNote;

class LeadDetailTest extends LeadApiTestCase
{
    // ── Autenticación ─────────────────────────────────────────────────────────

    public function test_detail_without_token_returns_401(): void
    {
        $lead = $this->createLead(self::TENANT_A);

        $this->getJson("/api/v1/leads/{$lead->id}")
            ->assertUnauthorized();
    }

    public function test_detail_returns_200_with_full_structure(): void
    {
        $token = $this->tokenForUser();
        $stage = $this->createInitialStage(self::TENANT_A);
        $lead  = $this->createLead(self::TENANT_A, ['stage_id' => $stage->id]);

        $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'tenant_id', 'status', 'priority',
                    'customer' => ['name', 'email'],
                    'stage'    => ['id', 'name', 'slug', 'order', 'color'],
                    'notes',
                    'activity',
                    'created_at', 'updated_at',
                ],
                'request_id',
            ]);
    }

    // ── Aislamiento multi-tenant ──────────────────────────────────────────────

    public function test_cannot_see_lead_from_other_tenant(): void
    {
        $tokenA = $this->tokenForUser(self::TENANT_A);
        $leadB  = $this->createLead(self::TENANT_B);

        $this->withToken($tokenA)
            ->getJson("/api/v1/leads/{$leadB->id}")
            ->assertNotFound();
    }

    // ── Notas y actividad en detalle ──────────────────────────────────────────

    public function test_detail_includes_up_to_10_recent_notes(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        for ($i = 1; $i <= 12; $i++) {
            LeadNote::create([
                'lead_id'   => $lead->id,
                'tenant_id' => self::TENANT_A,
                'content'   => "Nota {$i}",
            ]);
        }

        $response = $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}");

        $response->assertOk();
        $this->assertCount(10, $response->json('data.notes'));
    }

    public function test_detail_includes_up_to_10_recent_activity_logs(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        for ($i = 1; $i <= 12; $i++) {
            LeadActivityLog::create([
                'lead_id'     => $lead->id,
                'tenant_id'   => self::TENANT_A,
                'event_type'  => 'lead_updated',
                'description' => "Evento {$i}",
                'causer_type' => 'system',
            ]);
        }

        $response = $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}");

        $response->assertOk();
        $this->assertCount(10, $response->json('data.activity'));
    }

    public function test_detail_shows_activity_event_field(): void
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

        $response = $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}");

        $response->assertOk();
        $this->assertEquals('lead_created', $response->json('data.activity.0.event'));
    }

    // ── Abilities ─────────────────────────────────────────────────────────────

    public function test_token_without_leads_read_returns_403(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:create']);
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}")
            ->assertForbidden();
    }

    // ── Campos del lead ───────────────────────────────────────────────────────

    public function test_detail_contains_assigned_to_when_set(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A, [
            'assigned_user_id'            => 'ext-agent-abc',
            'assigned_user_name_snapshot'  => 'Carlos Agente',
            'assigned_user_email_snapshot' => 'carlos@zend.com',
            'assigned_user_provider'       => 'zend_platform',
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}");

        $response->assertOk()
            ->assertJsonPath('data.assigned_to.user_id', 'ext-agent-abc')
            ->assertJsonPath('data.assigned_to.name', 'Carlos Agente')
            ->assertJsonPath('data.assigned_to.provider', 'zend_platform');
    }

    public function test_detail_shows_request_id(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $response = $this->withToken($token)
            ->getJson("/api/v1/leads/{$lead->id}");

        $this->assertStringStartsWith('req_', $response->json('request_id'));
    }
}
