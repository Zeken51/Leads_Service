<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'next_action' => ['nullable', 'string', 'max:500'],
            'followup_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->filled('next_action') && ! $this->filled('followup_at')) {
                $v->errors()->add('next_action', 'At least one of next_action or followup_at is required.');
            }
        });
    }
}
