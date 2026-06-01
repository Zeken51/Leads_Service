<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateLeadNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content'        => ['required', 'string', 'max:5000'],
            'author_user_id' => ['nullable', 'string', 'max:255'],
            'author_name'    => ['nullable', 'string', 'max:255'],
        ];
    }
}
