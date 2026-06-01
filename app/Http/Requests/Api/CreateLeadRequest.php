<?php

namespace App\Http\Requests\Api;

use App\Domain\Leads\Enums\LeadPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Origen — nullable porque pueden venir del TenantApiClient
            'source_system'         => ['nullable', 'string', 'max:100'],
            'source_channel'        => ['nullable', 'string', 'max:100'],
            'external_reference_id' => ['nullable', 'string', 'max:255'],

            // Cliente/prospecto
            'customer'              => ['required', 'array'],
            'customer.name'         => ['required', 'string', 'max:255'],
            'customer.email'        => ['nullable', 'email', 'max:255'],
            'customer.phone'        => ['nullable', 'string', 'max:30'],
            'customer.country'      => ['nullable', 'string', 'size:2'],
            'customer.metadata'     => ['nullable', 'array'],

            // Prioridad y pipeline
            'priority'              => ['nullable', Rule::enum(LeadPriority::class)],

            // Asignación externa (todos opcionales)
            'assigned_to'           => ['nullable', 'array'],
            'assigned_to.user_id'   => ['required_with:assigned_to', 'string', 'max:255'],
            'assigned_to.name'      => ['required_with:assigned_to', 'string', 'max:255'],
            'assigned_to.email'     => ['nullable', 'email', 'max:255'],
            'assigned_to.provider'  => ['nullable', 'string', 'max:100'],

            // Seguimiento
            'next_action'           => ['nullable', 'string', 'max:500'],
            'followup_at'           => ['nullable', 'date', 'after:now'],

            // Metadata libre
            'metadata'              => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer.required'      => 'Customer data is required.',
            'customer.name.required' => 'Customer name is required.',
            'followup_at.after'      => 'Follow-up date must be in the future.',
        ];
    }
}
