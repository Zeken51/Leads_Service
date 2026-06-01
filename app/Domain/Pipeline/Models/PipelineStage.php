<?php

namespace App\Domain\Pipeline\Models;

use App\Domain\Concerns\HasTenant;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Lead;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PipelineStage extends Model
{
    use HasUuids, HasFactory, HasTenant, SoftDeletes;

    protected static function newFactory(): \Database\Factories\PipelineStageFactory
    {
        return \Database\Factories\PipelineStageFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'order',
        'color',
        'is_initial',
        'is_terminal',
        'maps_to_status',
    ];

    protected function casts(): array
    {
        return [
            'order'          => 'integer',
            'is_initial'     => 'boolean',
            'is_terminal'    => 'boolean',
            'maps_to_status' => LeadStatus::class,
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'stage_id');
    }
}
