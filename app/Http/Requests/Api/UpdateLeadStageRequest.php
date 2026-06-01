<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage_id'    => ['required', 'uuid'],
            'next_action' => ['nullable', 'string', 'max:500'],
            'followup_at' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
