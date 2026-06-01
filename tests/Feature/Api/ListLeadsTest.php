<?php

namespace Tests\Feature\Api;

class ListLeadsTest extends LeadApiTestCase
{
    // ── Autenticación ─────────────────────────────────────────────────────────

    public function test_list_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/leads')
            ->assertUnauthorized()
            ->assertJsonStructure(['message', 'request_id']);
    }

    public function test_list_returns_200_with_paginated_structure(): void
    {
        $token = $this->tokenForUser();
        $this->createLead(self::TENANT_A);
        $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->getJson('/api/v1/leads')
            ->assertOk()
            ->assertJsonStructure([
                'data'       => [['id', 'status', 'customer', 'stage']],
                'meta'       => ['current_page', 'per_page', 'total', 'last_page', 'from', 'to'],
                'request_id',
            ])
            ->assertJsonPath('meta.total', 2);
    }

    // ── Aislamiento multi-tenant ──────────────────────────────────────────────

    public function test_tenant_cannot_see_leads_from_other_tenant(): void
    {
        $tokenA = $this->tokenForUser(self::TENANT_A);
        $this->createLead(self::TENANT_A);
        $this->createLead(self::TENANT_B); // otro tenant

        $this->withToken($tokenA)
            ->getJson('/api/v1/leads')
            ->assertOk()
            ->assertJsonPath('meta.total', 1); // Solo ve el del TENANT_A
    }

    // ── Filtros ───────────────────────────────────────────────────────────────

    public function test_filter_by_status(): void
    {
        $token = $this->tokenForUser();
        $this->createLead(self::TENANT_A, ['status' => 'active']);
        $this->createLead(self::TENANT_A, ['status' => 'won', 'won_at' => now()]);

        $this->withToken($token)
            ->getJson('/api/v1/leads?status=won')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'won');
    }

    public function test_filter_by_stage_id(): void
    {
        $token   = $this->tokenForUser();
        $stageA  = $this->createInitialStage(self::TENANT_A);
        $stageB  = $this->createStage(self::TENANT_A, ['order' => 2]);

        $this->createLead(self::TENANT_A, ['stage_id' => $stageA->id]);
        $this->createLead(self::TENANT_A, ['stage_id' => $stageB->id]);

        $this->withToken($token)
            ->getJson("/api/v1/leads?stage_id={$stageA->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filter_by_assigned_to(): void
    {
        $token = $this->tokenForUser();
        $this->createLead(self::TENANT_A, ['assigned_user_id' => 'agent-001']);
        $this->createLead(self::TENANT_A, ['assigned_user_id' => 'agent-002']);

        $this->withToken($token)
            ->getJson('/api/v1/leads?assigned_to=agent-001')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filter_by_source_system(): void
    {
        $token = $this->tokenForUser();
        $this->createLead(self::TENANT_A, ['source_system' => 'zend_vacations']);
        $this->createLead(self::TENANT_A, ['source_system' => 'web_form']);

        $this->withToken($token)
            ->getJson('/api/v1/leads?source_system=zend_vacations')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.source_system', 'zend_vacations');
    }

    public function test_filter_overdue(): void
    {
        $token = $this->tokenForUser();
        $this->createLead(self::TENANT_A, ['followup_at' => now()->subDay()]); // overdue
        $this->createLead(self::TENANT_A, ['followup_at' => now()->addDay()]);  // not overdue
        $this->createLead(self::TENANT_A);                                      // no followup

        $this->withToken($token)
            ->getJson('/api/v1/leads?overdue=true')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_search_by_customer_name(): void
    {
        $token = $this->tokenForUser();
        $this->createLead(self::TENANT_A, ['customer_name' => 'Juan Pérez']);
        $this->createLead(self::TENANT_A, ['customer_name' => 'María García']);

        $this->withToken($token)
            ->getJson('/api/v1/leads?search=Juan')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.customer.name', 'Juan Pérez');
    }

    public function test_search_by_customer_email(): void
    {
        $token = $this->tokenForUser();
        $this->createLead(self::TENANT_A, ['customer_email' => 'juan@empresa.com']);
        $this->createLead(self::TENANT_A, ['customer_email' => 'maria@empresa.com']);

        $this->withToken($token)
            ->getJson('/api/v1/leads?search=juan@empresa')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    // ── Paginación ────────────────────────────────────────────────────────────

    public function test_per_page_is_respected(): void
    {
        $token = $this->tokenForUser();
        foreach (range(1, 5) as $i) {
            $this->createLead(self::TENANT_A);
        }

        $this->withToken($token)
            ->getJson('/api/v1/leads?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_per_page_max_is_100(): void
    {
        $token = $this->tokenForUser();

        $response = $this->withToken($token)
            ->getJson('/api/v1/leads?per_page=500');

        $response->assertOk();
        $this->assertLessThanOrEqual(100, $response->json('meta.per_page'));
    }

    // ── Estructura de respuesta ───────────────────────────────────────────────

    public function test_response_has_request_id(): void
    {
        $token = $this->tokenForUser();

        $response = $this->withToken($token)->getJson('/api/v1/leads');

        $response->assertOk();
        $this->assertStringStartsWith('req_', $response->json('request_id'));
    }

    public function test_list_does_not_include_notes_or_activity(): void
    {
        $token = $this->tokenForUser();
        $this->createLead(self::TENANT_A);

        $response = $this->withToken($token)->getJson('/api/v1/leads');

        $response->assertOk();
        $this->assertArrayNotHasKey('notes', $response->json('data.0'));
        $this->assertArrayNotHasKey('activity', $response->json('data.0'));
    }

    // ── Abilities ─────────────────────────────────────────────────────────────

    public function test_token_without_leads_read_returns_403(): void
    {
        $token = $this->tokenForUser(self::TENANT_A, ['leads:create']);

        $this->withToken($token)
            ->getJson('/api/v1/leads')
            ->assertForbidden()
            ->assertJsonStructure(['message', 'request_id']);
    }
}
