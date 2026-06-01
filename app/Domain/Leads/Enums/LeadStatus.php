<?php

namespace App\Domain\Leads\Enums;

enum LeadStatus: string
{
    case Active   = 'active';
    case Won      = 'won';
    case Lost     = 'lost';
    case Archived = 'archived';

    public function isTerminal(): bool
    {
        return match($this) {
            self::Won, self::Lost => true,
            default               => false,
        };
    }

    public function label(): string
    {
        return match($this) {
            self::Active   => 'Activo',
            self::Won      => 'Ganado',
            self::Lost     => 'Perdido',
            self::Archived => 'Archivado',
        };
    }
}
