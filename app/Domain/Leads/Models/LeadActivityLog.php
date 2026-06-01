<?php

namespace App\Domain\Leads\Models;

use App\Domain\Concerns\HasTenant;
use App\Domain\Leads\Enums\CauserType;
use App\Domain\Leads\Enums\ContactChannel;
use App\Domain\Leads\Enums\LeadEvent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivityLog extends Model
{
    use HasUuids, HasTenant;

    // Inmutable: sin updated_at ni soft deletes
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'lead_id',
        'tenant_id',
        'event_type',
        'description',
        'event_data',
        'causer_id',
        'causer_name_snapshot',
        'causer_type',
        'contact_channel',
    ];

    protected function casts(): array
    {
        return [
            'event_type'      => LeadEvent::class,
            'causer_type'     => CauserType::class,
            'contact_channel' => ContactChannel::class,
            'event_data'      => 'array',
            'created_at'      => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
