<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadActivityLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'event'       => $this->event_type->value,
            'description' => $this->description,
            'payload'     => $this->event_data ?? [],
            'causer'      => [
                'id'   => $this->causer_id,
                'name' => $this->causer_name_snapshot,
                'type' => $this->causer_type->value,
            ],
            'created_at'  => $this->created_at->toIso8601String(),
        ];
    }
}
