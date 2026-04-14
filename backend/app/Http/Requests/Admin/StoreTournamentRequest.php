<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Carbon\Carbon;

class StoreTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'circuit_id' => ['nullable', 'exists:circuits,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'mode' => ['required', 'in:pro,amateur,open'],
            'classification_method' => ['nullable', 'in:self_selected,referee_assigned'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province_state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'entry_fee_amount' => ['nullable', 'integer', 'min:0'],
            'entry_fee_currency' => ['nullable', 'in:EUR'],
            'registration_open_at' => ['nullable', 'date'],
            'registration_close_at' => ['nullable', 'date', 'after_or_equal:registration_open_at'],
            'day_start_time' => ['nullable', 'date_format:H:i'],
            'day_end_time' => ['nullable', 'date_format:H:i', 'after:day_start_time'],
            'match_duration_minutes' => ['nullable', 'integer', 'min:20'],
            'courts_count' => ['nullable', 'integer', 'min:1', 'max:64'],
            'prize_money' => ['nullable', 'numeric', 'min:0'],
            'prize_currency' => ['nullable', 'in:EUR'],
        ];
    }

    public function prepareForValidation(): void
    {
        if (strtolower((string) $this->input('mode', '')) === 'open') {
            $this->merge(['classification_method' => 'referee_assigned']);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $startDate = $this->input('start_date');
            $openAt = $this->input('registration_open_at');
            $closeAt = $this->input('registration_close_at');

            if ($openAt && $closeAt) {
                $open = Carbon::parse($openAt);
                $close = Carbon::parse($closeAt);
                if ($open->greaterThanOrEqualTo($close)) {
                    $validator->errors()->add('registration_open_at', 'La apertura de inscripciones debe ser antes del cierre.');
                }
            }

            if ($startDate && $openAt) {
                $start = Carbon::parse($startDate);
                $open = Carbon::parse($openAt);
                if ($open->greaterThanOrEqualTo($start)) {
                    $validator->errors()->add('registration_open_at', 'La apertura de inscripciones debe ser antes del inicio del torneo.');
                }
            }

            if ($startDate && $closeAt) {
                $start = Carbon::parse($startDate);
                $close = Carbon::parse($closeAt);
                if ($close->greaterThanOrEqualTo($start)) {
                    $validator->errors()->add('registration_close_at', 'El cierre de inscripciones debe ser antes del inicio del torneo.');
                }
            }
        });
    }
}
