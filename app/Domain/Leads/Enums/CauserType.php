<?php

namespace App\Domain\Leads\Enums;

enum CauserType: string
{
    case User      = 'user';
    case System    = 'system';
    case ApiClient = 'api_client';
}
