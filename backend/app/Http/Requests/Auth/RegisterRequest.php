<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $firstName = trim((string) $this->input('first_name'));
        $lastName = trim((string) $this->input('last_name'));
        $dni = trim((string) $this->input('dni'));
        $email = trim((string) $this->input('email'));
        $phone = trim((string) $this->input('phone', ''));
        $provinceState = trim((string) $this->input('province_state', ''));

        $this->merge([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'dni' => $dni,
            'email' => Str::lower($email),
            'phone' => $phone !== '' ? $phone : null,
            'province_state' => $provinceState !== '' ? $provinceState : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'string', 'max:50', Rule::unique('player_profiles', 'dni')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:50', Rule::unique('users', 'phone')],
            'province_state' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
