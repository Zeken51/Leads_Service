<?php

namespace App\Domain\Leads\Actions;

use App\Domain\Leads\Enums\LeadEvent;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadActivityLog;
use App\Domain\Pipeline\Models\PipelineStage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkLeadLostAction
{
    public function execute(Lead $lead, array $data, array $causer): Lead
    {
        if ($lead->isClosed()) {
            throw ValidationException::withMessages([
                'status' => ["Lead status is '{$lead->status->value}'. Cannot mark as lost."],
            ]);
        }

        return DB::transaction(function () use ($lead, $data, $causer) {
            $lostAt = isset($data['lost_at']) ? Carbon::parse($data['lost_at']) : now();

            // Buscar stage terminal tipo lost del tenant
            $lostStage = PipelineStage::withoutGlobalScopes()
                ->where('tenant_id', $lead->tenant_id)
                ->where('is_terminal', true)
                ->where('maps_to_status', LeadStatus::Lost->value)
                ->whereNull('deleted_at')
                ->first();

            $updates = [
                'status'      => LeadStatus::Lost,
                'lost_at'     => $lostAt,
                'lost_reason' => $data['lost_reason'],
            ];

            if ($lostStage) {
                $updates['stage_id'] = $lostStage->id;
            }

            if (! empty($data['metadata'])) {
                $updates['metadata'] = array_merge($lead->metadata ?? [], $data['metadata']);
            }

            $lead->update($updates);

            LeadActivityLog::create(array_merge([
                'lead_id'     => $lead->id,
                'tenant_id'   => $lead->tenant_id,
                'event_type'  => LeadEvent::LeadLost,
                'description' => 'Lead marcado como perdido.',
                'event_data'  => [
                    'lost_at'     => $lostAt->toIso8601String(),
                    'lost_reason' => $data['lost_reason'],
                ],
            ], $causer));

            $lead->refresh()->load('stage');

            return $lead;
        });
    }
}
