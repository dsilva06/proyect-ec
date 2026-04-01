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
        $dni = strtoupper((string) preg_replace('/\s+/', '', trim((string) $this->input('dni'))));
        $email = trim((string) $this->input('email'));
        $phone = trim((string) $this->input('phone', ''));
        $provinceState = trim((string) $this->input('province_state', ''));

        if ($phone !== '') {
            $digits = preg_replace('/\D+/', '', $phone);
            $phone = $digits !== '' ? '+'.$digits : '';
        }

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
            'dni' => [
                'required',
                'string',
                'regex:/^(V|E|P)-\d{7,10}$/',
                Rule::unique('player_profiles', 'dni')->where(function ($query) {
                    $query->whereExists(function ($subquery) {
                        $subquery
                            ->selectRaw('1')
                            ->from('users')
                            ->whereColumn('users.id', 'player_profiles.user_id')
                            ->whereNotNull('users.email_verified_at');
                    });
                }),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where(fn ($query) => $query->whereNotNull('email_verified_at')),
            ],
            'phone' => [
                'nullable',
                'string',
                'regex:/^\+[1-9]\d{7,14}$/',
                Rule::unique('users', 'phone')->where(fn ($query) => $query->whereNotNull('email_verified_at')),
            ],
            'province_state' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'dni.regex' => 'El DNI debe tener el formato V-12345678.',
            'phone.regex' => 'El teléfono debe incluir código de país y solo números, por ejemplo +584121234567.',
        ];
    }
}
