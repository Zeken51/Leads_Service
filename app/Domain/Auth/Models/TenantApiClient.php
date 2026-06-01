<?php

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class TenantApiClient extends Model
{
    use HasUuids, HasApiTokens, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'token_name',
        'source_system',
        'source_channel',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata'  => 'array',
        ];
    }
}
