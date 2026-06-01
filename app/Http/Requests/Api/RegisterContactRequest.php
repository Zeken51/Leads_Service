<?php

namespace App\Http\Requests\Api;

use App\Domain\Leads\Enums\ContactChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_channel' => ['nullable', Rule::enum(ContactChannel::class)],
            'contact_notes'   => ['nullable', 'string', 'max:5000'],
            'next_action'     => ['nullable', 'string', 'max:500'],
            'followup_at'     => ['nullable', 'date'],
        ];
    }
}
