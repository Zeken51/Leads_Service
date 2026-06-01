<?php

namespace App\Domain\Leads\Enums;

enum LeadPriority: string
{
    case Low    = 'low';
    case Medium = 'medium';
    case High   = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match($this) {
            self::Low    => 'Baja',
            self::Medium => 'Media',
            self::High   => 'Alta',
            self::Urgent => 'Urgente',
        };
    }

    public function weight(): int
    {
        return match($this) {
            self::Low    => 1,
            self::Medium => 2,
            self::High   => 3,
            self::Urgent => 4,
        };
    }
}
