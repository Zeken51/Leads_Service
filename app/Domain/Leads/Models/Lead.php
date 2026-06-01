<?php

namespace App\Domain\Leads\Models;

use App\Domain\Concerns\HasTenant;
use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Pipeline\Models\PipelineStage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasUuids, HasFactory, HasTenant, SoftDeletes;

    protected static function newFactory(): \Database\Factories\LeadFactory
    {
        return \Database\Factories\LeadFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'source_system',
        'source_channel',
        'external_reference_id',
        'status',
        'stage_id',
        'priority',
        // Customer
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_country',
        'customer_metadata',
        // Asignación
        'assigned_user_id',
        'assigned_user_name_snapshot',
        'assigned_user_email_snapshot',
        'assigned_user_provider',
        // Seguimiento
        'next_action',
        'followup_at',
        'last_contact_at',
        // Cierre
        'won_at',
        'lost_at',
        'lost_reason',
        // Extra
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status'            => LeadStatus::class,
            'priority'          => LeadPriority::class,
            'customer_metadata' => 'array',
            'metadata'          => 'array',
            'followup_at'       => 'datetime',
            'last_contact_at'   => 'datetime',
            'won_at'            => 'datetime',
            'lost_at'           => 'datetime',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(LeadActivityLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === LeadStatus::Active;
    }

    public function isClosed(): bool
    {
        return $this->status->isTerminal();
    }

    public function isOverdue(): bool
    {
        return $this->isActive()
            && $this->followup_at !== null
            && $this->followup_at->isPast();
    }
}
