<?php

namespace App\Domain\Auth\Enums;

enum ApiAbility: string
{
    // Leads
    case LeadsCreate  = 'leads:create';
    case LeadsRead    = 'leads:read';
    case LeadsUpdate  = 'leads:update';
    case LeadsAssign  = 'leads:assign';
    case LeadsArchive = 'leads:archive';

    // Notas
    case LeadsNotesCreate = 'leads:notes:create';
    case LeadsNotesRead   = 'leads:notes:read';

    // Actividad
    case LeadsActivityRead = 'leads:activity:read';

    // Pipeline
    case PipelineRead   = 'pipeline:read';
    case PipelineManage = 'pipeline:manage';

    // Acciones comerciales
    case LeadsContact  = 'leads:contact';
    case LeadsFollowup = 'leads:followup';
    case LeadsWon      = 'leads:won';
    case LeadsLost     = 'leads:lost';

    // Wildcard (solo para tokens de admin/sistema)
    case All = '*';

    /** Abilities estándar para un cliente externo de creación de leads */
    public static function forExternalCreator(): array
    {
        return [
            self::LeadsCreate->value,
            self::LeadsRead->value,
        ];
    }

    /** Abilities estándar para un agente comercial */
    public static function forAgent(): array
    {
        return [
            self::LeadsRead->value,
            self::LeadsUpdate->value,
            self::LeadsAssign->value,
            self::LeadsContact->value,
            self::LeadsFollowup->value,
            self::LeadsWon->value,
            self::LeadsLost->value,
            self::LeadsNotesCreate->value,
            self::LeadsNotesRead->value,
            self::LeadsActivityRead->value,
            self::PipelineRead->value,
        ];
    }
}
