<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_id' => ['nullable', 'exists:statuses,id'],
            'queue_position' => ['nullable', 'integer', 'min:1'],
            'seed_number' => ['nullable', 'integer', 'min:1'],
            'team_ranking_score' => ['nullable', 'integer', 'min:0'],
            'accepted_at' => ['nullable', 'date'],
            'payment_due_at' => ['nullable', 'date'],
            'cancelled_at' => ['nullable', 'date'],
            'notes_admin' => ['nullable', 'string'],
        ];
    }
}
