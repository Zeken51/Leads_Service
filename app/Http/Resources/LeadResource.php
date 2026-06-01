<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly ?bool $idempotentReplay = null,
    ) {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'tenant_id'             => $this->tenant_id,
            'source_system'         => $this->source_system,
            'source_channel'        => $this->source_channel,
            'external_reference_id' => $this->external_reference_id,
            'status'                => $this->status->value,
            'stage'                 => $this->stage_id ? [
                'id'    => $this->stage_id,
                'name'  => $this->whenLoaded('stage', fn () => $this->stage->name),
                'slug'  => $this->whenLoaded('stage', fn () => $this->stage->slug),
                'order' => $this->whenLoaded('stage', fn () => $this->stage->order),
                'color' => $this->whenLoaded('stage', fn () => $this->stage->color),
            ] : null,
            'priority'              => $this->priority->value,
            'customer'              => [
                'name'     => $this->customer_name,
                'email'    => $this->customer_email,
                'phone'    => $this->customer_phone,
                'country'  => $this->customer_country,
                'metadata' => $this->customer_metadata,
            ],
            'assigned_to'           => $this->assigned_user_id ? [
                'user_id'  => $this->assigned_user_id,
                'name'     => $this->assigned_user_name_snapshot,
                'email'    => $this->assigned_user_email_snapshot,
                'provider' => $this->assigned_user_provider,
            ] : null,
            'next_action'           => $this->next_action,
            'followup_at'           => $this->followup_at?->toIso8601String(),
            'last_contact_at'       => $this->last_contact_at?->toIso8601String(),
            'won_at'                => $this->won_at?->toIso8601String(),
            'lost_at'               => $this->lost_at?->toIso8601String(),
            'lost_reason'           => $this->lost_reason,
            'metadata'              => $this->metadata,
            'idempotent_replay'     => $this->when($this->idempotentReplay !== null, $this->idempotentReplay),
            // Solo presentes en el detalle del lead (GET /leads/{id})
            'notes'                 => $this->when(
                $this->relationLoaded('notes'),
                fn () => LeadNoteResource::collection($this->notes),
            ),
            'activity'              => $this->when(
                $this->relationLoaded('activityLogs'),
                fn () => LeadActivityLogResource::collection($this->activityLogs),
            ),
            'created_at'            => $this->created_at->toIso8601String(),
            'updated_at'            => $this->updated_at->toIso8601String(),
        ];
    }
}
