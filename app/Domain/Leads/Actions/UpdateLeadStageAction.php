<?php

namespace App\Domain\Leads\Actions;

use App\Domain\Leads\Enums\CauserType;
use App\Domain\Leads\Enums\LeadEvent;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadActivityLog;
use App\Domain\Pipeline\Models\PipelineStage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateLeadStageAction
{
    /**
     * Cambia la etapa del pipeline de un lead.
     * Si el stage es terminal, actualiza el status automáticamente.
     * Si maps_to_status=lost, lost_reason es obligatorio.
     */
    public function execute(Lead $lead, array $data, array $causer): Lead
    {
        if ($lead->isClosed()) {
            throw ValidationException::withMessages([
                'stage_id' => ["Lead status is '{$lead->status->value}'. Stage changes are not allowed on closed leads."],
            ]);
        }

        $tenantId = $lead->tenant_id;
        $stageId  = $data['stage_id'];

        $stage = PipelineStage::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', $stageId)
            ->whereNull('deleted_at')
            ->first();

        if (! $stage) {
            throw ValidationException::withMessages([
                'stage_id' => ['The stage does not exist or does not belong to this tenant.'],
            ]);
        }

        // Si el stage mapea a lost, lost_reason es obligatorio
        if ($stage->is_terminal && $stage->maps_to_status === LeadStatus::Lost) {
            if (empty($data['lost_reason'])) {
                throw ValidationException::withMessages([
                    'lost_reason' => ['The lost_reason field is required when closing a lead as lost.'],
                ]);
            }
        }

        // next_action y followup_at son incompatibles con stages terminales.
        // Un lead cerrado (won/lost) no tiene próximas acciones pendientes.
        // Ignorarlos silenciosamente confundiría al cliente que pensaría que se guardaron.
        if ($stage->is_terminal) {
            $errors = [];
            if (isset($data['next_action']) && $data['next_action'] !== null) {
                $errors['next_action'] = ['Cannot set next_action when moving to a terminal stage (won/lost). Close the lead via /stage with only stage_id, or use /won or /lost directly.'];
            }
            if (isset($data['followup_at']) && $data['followup_at'] !== null) {
                $errors['followup_at'] = ['Cannot set followup_at when moving to a terminal stage (won/lost). Terminal leads cannot have pending follow-up dates.'];
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }

        return DB::transaction(function () use ($lead, $data, $causer, $stage) {
            $previousStageId   = $lead->stage_id;
            $previousStageName = optional($lead->stage)->name ?? 'Sin etapa';

            $updates = ['stage_id' => $stage->id];

            // next_action y followup_at solo en stages no terminales (ya validado arriba)
            if (! $stage->is_terminal) {
                if (array_key_exists('next_action', $data) && $data['next_action'] !== null) {
                    $updates['next_action'] = $data['next_action'];
                }
                if (array_key_exists('followup_at', $data) && $data['followup_at'] !== null) {
                    $updates['followup_at'] = Carbon::parse($data['followup_at']);
                }
            }

            // Transiciones de estado para stages terminales
            if ($stage->is_terminal && $stage->maps_to_status === LeadStatus::Won) {
                $updates['status'] = LeadStatus::Won;
                $updates['won_at'] = now();

                LeadActivityLog::create(array_merge([
                    'lead_id'     => $lead->id,
                    'tenant_id'   => $lead->tenant_id,
                    'event_type'  => LeadEvent::LeadWon,
                    'description' => 'Lead marcado como ganado al avanzar a etapa terminal.',
                    'event_data'  => ['stage' => $stage->name],
                ], $causer));
            } elseif ($stage->is_terminal && $stage->maps_to_status === LeadStatus::Lost) {
                $updates['status']      = LeadStatus::Lost;
                $updates['lost_at']     = now();
                $updates['lost_reason'] = $data['lost_reason'];

                LeadActivityLog::create(array_merge([
                    'lead_id'     => $lead->id,
                    'tenant_id'   => $lead->tenant_id,
                    'event_type'  => LeadEvent::LeadLost,
                    'description' => 'Lead marcado como perdido al avanzar a etapa terminal.',
                    'event_data'  => ['stage' => $stage->name, 'lost_reason' => $data['lost_reason']],
                ], $causer));
            }

            $lead->update($updates);

            LeadActivityLog::create(array_merge([
                'lead_id'     => $lead->id,
                'tenant_id'   => $lead->tenant_id,
                'event_type'  => LeadEvent::StageChanged,
                'description' => "Etapa cambiada de '{$previousStageName}' a '{$stage->name}'",
                'event_data'  => [
                    'from' => ['id' => $previousStageId, 'name' => $previousStageName],
                    'to'   => ['id' => $stage->id,       'name' => $stage->name],
                ],
            ], $causer));

            $lead->refresh()->load('stage');

            return $lead;
        });
    }
}
