<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MarkLeadLostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lost_reason' => ['required', 'string', 'max:1000'],
            'lost_at'     => ['nullable', 'date'],
            'metadata'    => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'lost_reason.required' => 'The lost_reason field is required when closing a lead as lost.',
        ];
    }
}
