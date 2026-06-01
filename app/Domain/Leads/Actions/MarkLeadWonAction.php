<?php

namespace App\Domain\Leads\Actions;

use App\Domain\Leads\Enums\LeadEvent;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadActivityLog;
use App\Domain\Leads\Models\LeadNote;
use App\Domain\Pipeline\Models\PipelineStage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkLeadWonAction
{
    public function execute(Lead $lead, array $data, array $causer): Lead
    {
        if ($lead->isClosed()) {
            throw ValidationException::withMessages([
                'status' => ["Lead status is '{$lead->status->value}'. Cannot mark as won."],
            ]);
        }

        return DB::transaction(function () use ($lead, $data, $causer) {
            $wonAt = isset($data['won_at']) ? Carbon::parse($data['won_at']) : now();

            // Buscar stage terminal tipo won del tenant
            $wonStage = PipelineStage::withoutGlobalScopes()
                ->where('tenant_id', $lead->tenant_id)
                ->where('is_terminal', true)
                ->where('maps_to_status', LeadStatus::Won->value)
                ->whereNull('deleted_at')
                ->first();

            $updates = [
                'status' => LeadStatus::Won,
                'won_at' => $wonAt,
            ];

            if ($wonStage) {
                $updates['stage_id'] = $wonStage->id;
            }

            if (! empty($data['metadata'])) {
                $updates['metadata'] = array_merge($lead->metadata ?? [], $data['metadata']);
            }

            $lead->update($updates);

            if (! empty($data['note'])) {
                LeadNote::create([
                    'lead_id'              => $lead->id,
                    'tenant_id'            => $lead->tenant_id,
                    'content'              => $data['note'],
                    'author_id'            => $causer['causer_id'] ?? null,
                    'author_name_snapshot' => $causer['causer_name_snapshot'] ?? null,
                ]);
            }

            LeadActivityLog::create(array_merge([
                'lead_id'     => $lead->id,
                'tenant_id'   => $lead->tenant_id,
                'event_type'  => LeadEvent::LeadWon,
                'description' => 'Lead marcado como ganado.',
                'event_data'  => ['won_at' => $wonAt->toIso8601String()],
            ], $causer));

            $lead->refresh()->load('stage');

            return $lead;
        });
    }
}
