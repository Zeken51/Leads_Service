<?php

namespace App\Domain\Idempotency\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasUuids;

    // Solo created_at — sin updated_at
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'tenant_id',
        'idempotency_key',
        'request_hash',
        'method',
        'path',
        'source_system',
        'source_channel',
        'external_reference_id',
        'lead_id',
        'response_status',
        'response_body',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'response_status' => 'integer',
            'expires_at'    => 'datetime',
            'created_at'    => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
