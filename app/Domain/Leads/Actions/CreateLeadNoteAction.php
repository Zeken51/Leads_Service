<?php

namespace App\Domain\Leads\Actions;

use App\Domain\Leads\Enums\LeadEvent;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadActivityLog;
use App\Domain\Leads\Models\LeadNote;
use Illuminate\Support\Facades\DB;

class CreateLeadNoteAction
{
    public function execute(Lead $lead, array $data, array $causer): LeadNote
    {
        return DB::transaction(function () use ($lead, $data, $causer) {
            // author_user_id y author_name del request toman prioridad sobre el causer del token
            $authorId   = $data['author_user_id'] ?? $causer['causer_id'] ?? null;
            $authorName = $data['author_name']    ?? $causer['causer_name_snapshot'] ?? null;

            $note = LeadNote::create([
                'lead_id'              => $lead->id,
                'tenant_id'            => $lead->tenant_id,
                'content'              => $data['content'],
                'author_id'            => $authorId,
                'author_name_snapshot' => $authorName,
            ]);

            // Las notas son registros internos de actividad del agente.
            // last_contact_at solo se actualiza en /contact (señal explícita de contacto real).
            LeadActivityLog::create(array_merge([
                'lead_id'     => $lead->id,
                'tenant_id'   => $lead->tenant_id,
                'event_type'  => LeadEvent::NoteAdded,
                'description' => 'Nota agregada.',
                'event_data'  => ['note_id' => $note->id],
            ], $causer));

            return $note;
        });
    }
}
