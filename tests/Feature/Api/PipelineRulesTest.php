<?php

namespace Tests\Feature\Api;

use App\Domain\Leads\Models\LeadActivityLog;

/**
 * Valida las reglas comerciales del pipeline:
 * - stage inicial en creación
 * - last_contact_at: solo se actualiza en /contact
 * - stage change: event_data con from/to, no actualiza last_contact_at
 * - terminal stages: bloquean cambios de stage, no aceptan next_action/followup_at
 * - followup: next_action requerido con followup_at, bloqueado en terminales
 * - overdue: solo leads activos con followup_at en el pasado
 * - activity logs: event_type y event_data consistentes
 */
class PipelineRulesTest extends LeadApiTestCase
{
    // ── 1. Stage inicial en creación ──────────────────────────────────────────

    public function test_lead_created_without_pipeline_has_null_stage(): void
    {
        $token = $this->tokenForUser();

        $response = $this->withToken($token)
            ->postJson('/api/v1/leads', [
                'source_system'  => 'web_form',
                'source_channel' => 'landing_page',
                'customer'       => ['name' => 'Test Lead'],
            ]);

        $response->assertCreated();
        $this->assertNull($response->json('data.stage'));
    }

    public function test_lead_created_with_pipeline_uses_initial_stage(): void
    {
        $token        = $this->tokenForUser();
        $initialStage = $this->createInitialStage(self::TENANT_A);
        $this->createStage(self::TENANT_A, ['order' => 2]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/leads', [
                'source_system'  => 'web_form',
                'source_channel' => 'landing_page',
                'customer'       => ['name' => 'Test Lead'],
            ]);

        $response->assertCreated();
        $this->assertEquals($initialStage->id, $response->json('data.stage.id'));
    }

    public function test_lead_created_always_has_active_status(): void
    {
        $token = $this->tokenForUser();

        $this->withToken($token)
            ->postJson('/api/v1/leads', [
                'source_system'  => 'web_form',
                'source_channel' => 'landing_page',
                'customer'       => ['name' => 'Test Lead'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');
    }

    // ── 2. last_contact_at: solo /contact lo actualiza ────────────────────────

    public function test_contact_updates_last_contact_at(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A, ['last_contact_at' => null]);

        $this->withToken($token)
            ->postJson("/api/v1/leads/{$lead->id}/contact", ['contact_channel' => 'phone'])
            ->assertOk();

        $this->assertNotNull($lead->fresh()->last_contact_at);
    }

    public function test_stage_change_does_not_update_last_contact_at(): void
    {
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A, ['last_contact_at' => null]);
        $newStage = $this->createStage(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/stage", ['stage_id' => $newStage->id])
            ->assertOk();

        $this->assertNull($lead->fresh()->last_contact_at);
    }

    public function test_note_does_not_update_last_contact_at(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A, ['last_contact_at' => null]);

        $this->withToken($token)
            ->postJson("/api/v1/leads/{$lead->id}/notes", ['content' => 'Nota interna'])
            ->assertCreated();

        $this->assertNull($lead->fresh()->last_contact_at);
    }

    // ── 3. Stage change: event_data con from/to ───────────────────────────────

    public function test_stage_change_records_from_and_to_in_event_data(): void
    {
        $token    = $this->tokenForUser();
        $fromStage = $this->createInitialStage(self::TENANT_A);
        $toStage   = $this->createStage(self::TENANT_A, ['order' => 2, 'name' => 'Contactado']);
        $lead      = $this->createLead(self::TENANT_A, ['stage_id' => $fromStage->id]);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/stage", ['stage_id' => $toStage->id])
            ->assertOk();

        $log = LeadActivityLog::where('lead_id', $lead->id)
            ->where('event_type', 'stage_changed')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($fromStage->id, $log->event_data['from']['id']);
        $this->assertEquals($toStage->id,   $log->event_data['to']['id']);
        $this->assertEquals($fromStage->name, $log->event_data['from']['name']);
        $this->assertEquals($toStage->name,   $log->event_data['to']['name']);
    }

    // ── 4. Terminal stages: next_action/followup_at se rechazan con 422 ─────────

    public function test_stage_change_to_terminal_won_rejects_next_action(): void
    {
        // next_action en un stage terminal es incompatible: el lead no tendrá acciones pendientes.
        // Se rechaza con 422 en lugar de ignorar silenciosamente (el cliente debe saberlo).
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A);
        $wonStage = $this->createWonStage(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/stage", [
                'stage_id'    => $wonStage->id,
                'next_action' => 'Acción incompatible con cierre',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['next_action'], 'request_id']);
    }

    public function test_stage_change_to_terminal_won_rejects_followup_at(): void
    {
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A);
        $wonStage = $this->createWonStage(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/stage", [
                'stage_id'    => $wonStage->id,
                'followup_at' => now()->addDay()->toISOString(),
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['followup_at'], 'request_id']);
    }

    public function test_stage_change_to_terminal_lost_rejects_followup_at(): void
    {
        $token     = $this->tokenForUser();
        $lead      = $this->createLead(self::TENANT_A);
        $lostStage = $this->createLostStage(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/stage", [
                'stage_id'    => $lostStage->id,
                'lost_reason' => 'Sin presupuesto',
                'followup_at' => now()->addDay()->toISOString(),
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['followup_at'], 'request_id']);
    }

    public function test_stage_change_to_terminal_won_without_followup_fields_succeeds(): void
    {
        // Sin next_action ni followup_at el cierre vía /stage es correcto
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A);
        $wonStage = $this->createWonStage(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/stage", ['stage_id' => $wonStage->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'won');
    }

    public function test_stage_change_to_terminal_lost_without_followup_fields_succeeds(): void
    {
        $token     = $this->tokenForUser();
        $lead      = $this->createLead(self::TENANT_A);
        $lostStage = $this->createLostStage(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/stage", [
                'stage_id'    => $lostStage->id,
                'lost_reason' => 'El cliente eligió otra agencia',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'lost');
    }

    public function test_non_terminal_stage_change_accepts_next_action(): void
    {
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A);
        $newStage = $this->createStage(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/stage", [
                'stage_id'    => $newStage->id,
                'next_action' => 'Enviar propuesta',
                'followup_at' => now()->addDay()->toISOString(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('leads', [
            'id'          => $lead->id,
            'next_action' => 'Enviar propuesta',
        ]);
        $this->assertNotNull($lead->fresh()->followup_at);
    }

    // ── 5. Followup: reglas de negocio ────────────────────────────────────────

    public function test_followup_at_alone_requires_next_action(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/followup", [
                'followup_at' => now()->addDay()->toISOString(),
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['next_action']]);
    }

    public function test_followup_with_both_fields_succeeds(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/followup", [
                'next_action' => 'Llamar a las 10am',
                'followup_at' => now()->addDay()->toISOString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.next_action', 'Llamar a las 10am');
    }

    public function test_followup_with_only_next_action_succeeds(): void
    {
        // next_action solo (sin fecha) es válido para acción inmediata sin fecha
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/followup", [
                'next_action' => 'Llamar mañana',
            ])
            ->assertOk();
    }

    public function test_followup_blocked_on_won_lead(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A, ['status' => 'won', 'won_at' => now()]);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/followup", ['next_action' => 'Test'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_followup_blocked_on_lost_lead(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A, [
            'status'      => 'lost',
            'lost_at'     => now(),
            'lost_reason' => 'Sin interés',
        ]);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/followup", ['next_action' => 'Test'])
            ->assertUnprocessable();
    }

    // ── 6. Overdue: solo leads activos ────────────────────────────────────────

    public function test_overdue_filter_excludes_won_leads(): void
    {
        $token = $this->tokenForUser();
        // Lead overdue pero ganado (no debe aparecer en overdue)
        $this->createLead(self::TENANT_A, [
            'status'      => 'won',
            'won_at'      => now(),
            'followup_at' => now()->subDay(),
        ]);
        // Lead overdue activo (sí debe aparecer)
        $this->createLead(self::TENANT_A, ['followup_at' => now()->subDay()]);

        $this->withToken($token)
            ->getJson('/api/v1/leads?overdue=true')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_overdue_filter_excludes_lost_leads(): void
    {
        $token = $this->tokenForUser();
        // Lead overdue pero perdido (no debe aparecer)
        $this->createLead(self::TENANT_A, [
            'status'      => 'lost',
            'lost_at'     => now(),
            'lost_reason' => 'Sin budget',
            'followup_at' => now()->subDay(),
        ]);
        // Lead activo sin followup (no debe aparecer en overdue)
        $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->getJson('/api/v1/leads?overdue=true')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    // ── 7. Activity logs: event_data consistente ──────────────────────────────

    public function test_lead_created_activity_log_has_source_data(): void
    {
        $token = $this->tokenForUser();

        $this->withToken($token)
            ->postJson('/api/v1/leads', [
                'source_system'         => 'web_form',
                'source_channel'        => 'landing_page',
                'external_reference_id' => 'WF-999',
                'customer'              => ['name' => 'Test'],
            ])
            ->assertCreated();

        $log = LeadActivityLog::where('event_type', 'lead_created')
            ->where('tenant_id', self::TENANT_A)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('web_form', $log->event_data['source_system']);
        $this->assertEquals('landing_page', $log->event_data['source_channel']);
        $this->assertEquals('WF-999', $log->event_data['external_reference_id']);
    }

    public function test_followup_activity_log_has_next_action_and_date(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);
        $date  = now()->addDays(3)->toISOString();

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/followup", [
                'next_action' => 'Enviar propuesta',
                'followup_at' => $date,
            ])
            ->assertOk();

        $log = LeadActivityLog::where('lead_id', $lead->id)
            ->where('event_type', 'followup_scheduled')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Enviar propuesta', $log->event_data['next_action']);
        $this->assertNotNull($log->event_data['followup_at']);
    }

    public function test_contact_activity_log_has_contact_channel(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->postJson("/api/v1/leads/{$lead->id}/contact", [
                'contact_channel' => 'whatsapp',
            ])
            ->assertOk();

        $log = LeadActivityLog::where('lead_id', $lead->id)
            ->where('event_type', 'contact_registered')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('whatsapp', $log->contact_channel->value);
    }

    public function test_won_activity_log_has_won_at(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/won")
            ->assertOk();

        $log = LeadActivityLog::where('lead_id', $lead->id)
            ->where('event_type', 'lead_won')
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->event_data['won_at']);
    }

    public function test_lost_activity_log_has_reason_and_date(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/lost", [
                'lost_reason' => 'El cliente eligió a la competencia.',
            ])
            ->assertOk();

        $log = LeadActivityLog::where('lead_id', $lead->id)
            ->where('event_type', 'lead_lost')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('El cliente eligió a la competencia.', $log->event_data['lost_reason']);
        $this->assertNotNull($log->event_data['lost_at']);
    }

    public function test_assign_activity_log_has_from_and_to(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A, [
            'assigned_user_id'            => 'agent-old',
            'assigned_user_name_snapshot'  => 'Agente Anterior',
        ]);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/assign", [
                'user_id' => 'agent-new',
                'name'    => 'Nuevo Agente',
            ])
            ->assertOk();

        $log = LeadActivityLog::where('lead_id', $lead->id)
            ->where('event_type', 'lead_assigned')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('agent-old', $log->event_data['from']['user_id']);
        $this->assertEquals('agent-new', $log->event_data['to']['user_id']);
        $this->assertEquals('Nuevo Agente', $log->event_data['to']['name']);
    }

    // ── 8. Terminal states: bloqueos explícitos ───────────────────────────────

    public function test_won_lead_cannot_change_stage(): void
    {
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A, ['status' => 'won', 'won_at' => now()]);
        $newStage = $this->createStage(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/stage", ['stage_id' => $newStage->id])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['stage_id']]);
    }

    public function test_lost_lead_cannot_change_stage(): void
    {
        $token    = $this->tokenForUser();
        $lead     = $this->createLead(self::TENANT_A, [
            'status'      => 'lost',
            'lost_at'     => now(),
            'lost_reason' => 'Sin budget',
        ]);
        $newStage = $this->createStage(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/stage", ['stage_id' => $newStage->id])
            ->assertUnprocessable();
    }

    public function test_won_lead_cannot_be_marked_lost(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A, ['status' => 'won', 'won_at' => now()]);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/lost", ['lost_reason' => 'Test'])
            ->assertUnprocessable();
    }

    public function test_lost_lead_cannot_be_marked_won(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A, [
            'status'      => 'lost',
            'lost_at'     => now(),
            'lost_reason' => 'Sin budget',
        ]);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/won")
            ->assertUnprocessable();
    }

    // ── 9. Assignación: solo referencias externas ─────────────────────────────

    public function test_assign_stores_external_user_snapshots(): void
    {
        $token = $this->tokenForUser();
        $lead  = $this->createLead(self::TENANT_A);

        $this->withToken($token)
            ->patchJson("/api/v1/leads/{$lead->id}/assign", [
                'user_id'  => 'ext-system-user-id',
                'name'     => 'Agente Externo',
                'email'    => 'agente@sistema.com',
                'provider' => 'zend_platform',
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to.user_id', 'ext-system-user-id')
            ->assertJsonPath('data.assigned_to.provider', 'zend_platform');

        $this->assertDatabaseHas('leads', [
            'id'                           => $lead->id,
            'assigned_user_id'             => 'ext-system-user-id',
            'assigned_user_name_snapshot'  => 'Agente Externo',
            'assigned_user_email_snapshot' => 'agente@sistema.com',
            'assigned_user_provider'       => 'zend_platform',
        ]);
    }

    public function test_lead_model_isoverdue_false_for_terminal_lead(): void
    {
        $lead = $this->createLead(self::TENANT_A, [
            'status'      => 'won',
            'won_at'      => now(),
            'followup_at' => now()->subDay(),
        ]);

        $this->assertFalse($lead->isOverdue());
    }

    public function test_lead_model_isoverdue_true_for_active_overdue(): void
    {
        $lead = $this->createLead(self::TENANT_A, [
            'followup_at' => now()->subDay(),
        ]);

        $this->assertTrue($lead->isOverdue());
    }
}
