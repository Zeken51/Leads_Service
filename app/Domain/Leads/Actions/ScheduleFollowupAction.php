<?php

namespace App\Domain\Leads\Actions;

use App\Domain\Leads\Enums\LeadEvent;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadActivityLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleFollowupAction
{
    public function execute(Lead $lead, array $data, array $causer): Lead
    {
        return DB::transaction(function () use ($lead, $data, $causer) {
            $updates = [];

            if (isset($data['next_action'])) {
                $updates['next_action'] = $data['next_action'];
            }
            if (isset($data['followup_at'])) {
                $updates['followup_at'] = Carbon::parse($data['followup_at']);
            }

            $lead->update($updates);

            LeadActivityLog::create(array_merge([
                'lead_id'     => $lead->id,
                'tenant_id'   => $lead->tenant_id,
                'event_type'  => LeadEvent::FollowupScheduled,
                'description' => 'Seguimiento programado.',
                'event_data'  => array_filter([
                    'next_action' => $data['next_action'] ?? null,
                    'followup_at' => isset($data['followup_at'])
                        ? Carbon::parse($data['followup_at'])->toIso8601String()
                        : null,
                ]),
            ], $causer));

            return $lead->refresh();
        });
    }
}
