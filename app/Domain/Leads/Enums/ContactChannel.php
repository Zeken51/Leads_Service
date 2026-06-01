<?php

namespace App\Domain\Leads\Enums;

enum ContactChannel: string
{
    case Phone     = 'phone';
    case WhatsApp  = 'whatsapp';
    case Email     = 'email';
    case InPerson  = 'in_person';
    case VideoCall = 'video_call';
    case Sms       = 'sms';
    case Other     = 'other';

    public function label(): string
    {
        return match($this) {
            self::Phone     => 'Llamada',
            self::WhatsApp  => 'WhatsApp',
            self::Email     => 'Email',
            self::InPerson  => 'En persona',
            self::VideoCall => 'Videollamada',
            self::Sms       => 'SMS',
            self::Other     => 'Otro',
        };
    }
}
