<?php

namespace App\Domain\Leads\Models;

use App\Domain\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadNote extends Model
{
    use HasUuids, HasTenant, SoftDeletes;

    protected $fillable = [
        'lead_id',
        'tenant_id',
        'content',
        'author_id',
        'author_name_snapshot',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
