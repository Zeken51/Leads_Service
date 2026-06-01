<?php

namespace App\Domain\Leads\Actions;

use App\Domain\Leads\Enums\ContactChannel;
use App\Domain\Leads\Enums\LeadEvent;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadActivityLog;
use App\Domain\Leads\Models\LeadNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RegisterLeadContactAction
{
    public function execute(Lead $lead, array $data, array $causer): Lead
    {
        return DB::transaction(function () use ($lead, $data, $causer) {
            $updates = ['last_contact_at' => now()];

            if (isset($data['next_action'])) {
                $updates['next_action'] = $data['next_action'];
            }
            if (isset($data['followup_at'])) {
                $updates['followup_at'] = Carbon::parse($data['followup_at']);
            }

            $lead->update($updates);

            // Crear nota automática si se proporcionaron contact_notes
            if (! empty($data['contact_notes'])) {
                LeadNote::create([
                    'lead_id'              => $lead->id,
                    'tenant_id'            => $lead->tenant_id,
                    'content'              => $data['contact_notes'],
                    'author_id'            => $causer['causer_id'] ?? null,
                    'author_name_snapshot' => $causer['causer_name_snapshot'] ?? null,
                ]);
            }

            $channel = isset($data['contact_channel'])
                ? ContactChannel::from($data['contact_channel'])
                : null;

            LeadActivityLog::create(array_merge([
                'lead_id'         => $lead->id,
                'tenant_id'       => $lead->tenant_id,
                'event_type'      => LeadEvent::ContactRegistered,
                'description'     => 'Contacto registrado' . ($channel ? " vía {$channel->label()}" : '.'),
                'event_data'      => array_filter([
                    'contact_channel' => $channel?->value,
                    'next_action'     => $data['next_action'] ?? null,
                    'followup_at'     => isset($data['followup_at'])
                        ? Carbon::parse($data['followup_at'])->toIso8601String()
                        : null,
                ]),
                'contact_channel' => $channel,
            ], $causer));

            return $lead->refresh();
        });
    }
}
