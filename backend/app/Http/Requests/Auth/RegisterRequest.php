<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    private const DOCUMENT_TYPES = ['DNI', 'NIE', 'PASSPORT'];

    protected function prepareForValidation(): void
    {
        $firstName = trim((string) $this->input('first_name'));
        $lastName = trim((string) $this->input('last_name'));
        $documentType = $this->normalizeDocumentType((string) $this->input('document_type', ''));
        $documentNumber = $this->normalizeDocumentNumber(
            $documentType,
            (string) $this->input('document_number', '')
        );
        $dni = $documentNumber;
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
            'document_type' => $documentType,
            'document_number' => $documentNumber,
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
            'document_type' => ['required', 'string', Rule::in(self::DOCUMENT_TYPES)],
            'document_number' => [
                'required',
                'string',
                'max:40',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $type = (string) $this->input('document_type');
                    $number = (string) $value;

                    $isValid = match ($type) {
                        'DNI' => preg_match('/^\d{8}[A-Z]$/', $number) === 1,
                        'NIE' => preg_match('/^[XYZ]\d{7}[A-Z]$/', $number) === 1,
                        'PASSPORT' => preg_match('/^[A-Z0-9]{5,20}$/', $number) === 1,
                        default => false,
                    };

                    if (! $isValid) {
                        $fail('El documento no tiene un formato válido para el tipo seleccionado.');
                    }
                },
            ],
            'dni' => [
                'required',
                'string',
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
            'document_type.in' => 'El tipo de documento debe ser DNI, NIE o pasaporte.',
            'phone.regex' => 'El teléfono debe incluir código de país y solo números, por ejemplo +584121234567.',
        ];
    }

    private function normalizeDocumentType(string $type): string
    {
        $type = strtoupper(trim($type));

        return match ($type) {
            'DNI', 'NIE', 'PASSPORT' => $type,
            'PASAPORTE' => 'PASSPORT',
            default => $type,
        };
    }

    private function normalizeDocumentNumber(string $type, string $number): string
    {
        $number = strtoupper(trim($number));
        $number = preg_replace('/[\s.-]+/', '', $number) ?? '';

        return match ($type) {
            'DNI', 'NIE', 'PASSPORT' => $number,
            default => strtoupper((string) preg_replace('/\s+/', '', $number)),
        };
    }
}
