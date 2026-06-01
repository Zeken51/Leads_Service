<?php

namespace App\Domain\Leads\Enums;

enum LeadEvent: string
{
    case LeadCreated       = 'lead_created';
    case LeadUpdated       = 'lead_updated';
    case StageChanged      = 'stage_changed';
    case StatusChanged     = 'status_changed';
    case LeadAssigned      = 'lead_assigned';
    case LeadUnassigned    = 'lead_unassigned';
    case NoteAdded         = 'note_added';
    case ContactRegistered = 'contact_registered';
    case FollowupScheduled = 'followup_scheduled';
    case LeadWon           = 'lead_won';
    case LeadLost          = 'lead_lost';
    case LeadArchived      = 'lead_archived';
    case LeadRestored      = 'lead_restored';

    public function description(): string
    {
        return match($this) {
            self::LeadCreated       => 'Lead creado',
            self::LeadUpdated       => 'Lead actualizado',
            self::StageChanged      => 'Etapa cambiada',
            self::StatusChanged     => 'Estado cambiado',
            self::LeadAssigned      => 'Lead asignado',
            self::LeadUnassigned    => 'Lead desasignado',
            self::NoteAdded         => 'Nota agregada',
            self::ContactRegistered => 'Contacto registrado',
            self::FollowupScheduled => 'Seguimiento programado',
            self::LeadWon           => 'Lead ganado',
            self::LeadLost          => 'Lead perdido',
            self::LeadArchived      => 'Lead archivado',
            self::LeadRestored      => 'Lead restaurado',
        };
    }
}
