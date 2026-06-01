<?php

namespace App\Domain\Leads\Actions;

use App\Domain\Leads\Enums\LeadEvent;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadActivityLog;
use Illuminate\Support\Facades\DB;

class AssignLeadAction
{
    public function execute(Lead $lead, array $data, array $causer): Lead
    {
        return DB::transaction(function () use ($lead, $data, $causer) {
            $previous = [
                'user_id' => $lead->assigned_user_id,
                'name'    => $lead->assigned_user_name_snapshot,
            ];

            $lead->update([
                'assigned_user_id'             => $data['user_id'],
                'assigned_user_name_snapshot'  => $data['name'],
                'assigned_user_email_snapshot' => $data['email'] ?? null,
                'assigned_user_provider'       => $data['provider'] ?? null,
            ]);

            LeadActivityLog::create(array_merge([
                'lead_id'     => $lead->id,
                'tenant_id'   => $lead->tenant_id,
                'event_type'  => LeadEvent::LeadAssigned,
                'description' => "Lead asignado a {$data['name']}",
                'event_data'  => [
                    'from' => $previous,
                    'to'   => ['user_id' => $data['user_id'], 'name' => $data['name']],
                ],
            ], $causer));

            return $lead->refresh();
        });
    }
}
