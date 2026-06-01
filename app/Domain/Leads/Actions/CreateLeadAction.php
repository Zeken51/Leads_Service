<?php

namespace App\Domain\Leads\Actions;

use App\Domain\Leads\Enums\CauserType;
use App\Domain\Leads\Enums\LeadEvent;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadActivityLog;
use App\Domain\Pipeline\Models\PipelineStage;
use Illuminate\Support\Facades\DB;

class CreateLeadAction
{
    /**
     * Crea un lead con su activity log inicial dentro de una sola transacción.
     * Usa withoutGlobalScopes() en las queries internas para no depender del
     * TenantContext — el tenant se pasa explícitamente como parámetro.
     */
    public function execute(array $data, string $tenantId): Lead
    {
        return DB::transaction(function () use ($data, $tenantId) {
            $stageId = PipelineStage::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_initial', true)
                ->orderBy('order')
                ->value('id');

            $lead = Lead::create([
                'tenant_id'                    => $tenantId,
                'source_system'                => $data['source_system'],
                'source_channel'               => $data['source_channel'],
                'external_reference_id'        => $data['external_reference_id'] ?? null,
                'status'                       => LeadStatus::Active,
                'stage_id'                     => $stageId,
                'priority'                     => $data['priority'] ?? 'medium',
                'customer_name'                => $data['customer']['name'],
                'customer_email'               => $data['customer']['email'] ?? null,
                'customer_phone'               => $data['customer']['phone'] ?? null,
                'customer_country'             => $data['customer']['country'] ?? null,
                'customer_metadata'            => $data['customer']['metadata'] ?? null,
                'assigned_user_id'             => $data['assigned_to']['user_id'] ?? null,
                'assigned_user_name_snapshot'  => $data['assigned_to']['name'] ?? null,
                'assigned_user_email_snapshot' => $data['assigned_to']['email'] ?? null,
                'assigned_user_provider'       => $data['assigned_to']['provider'] ?? null,
                'next_action'                  => $data['next_action'] ?? null,
                'followup_at'                  => $data['followup_at'] ?? null,
                'metadata'                     => $data['metadata'] ?? null,
            ]);

            LeadActivityLog::create([
                'lead_id'     => $lead->id,
                'tenant_id'   => $tenantId,
                'event_type'  => LeadEvent::LeadCreated,
                'description' => "Lead creado desde {$data['source_system']}/{$data['source_channel']}",
                'event_data'  => [
                    'source_system'         => $data['source_system'],
                    'source_channel'        => $data['source_channel'],
                    'external_reference_id' => $data['external_reference_id'] ?? null,
                ],
                'causer_type' => CauserType::ApiClient,
            ]);

            return $lead;
        });
    }
}
