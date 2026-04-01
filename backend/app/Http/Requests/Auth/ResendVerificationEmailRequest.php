<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ResendVerificationEmailRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $email = trim((string) $this->input('email'));

        $this->merge([
            'email' => Str::lower($email),
            'verification_context' => trim((string) $this->input('verification_context', '')) ?: null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'verification_context' => ['nullable', 'string'],
        ];
    }
}
