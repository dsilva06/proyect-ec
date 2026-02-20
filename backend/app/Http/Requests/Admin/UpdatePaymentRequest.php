<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_id' => ['nullable', 'exists:statuses,id'],
            'paid_by_user_id' => ['nullable', 'exists:users,id'],
            'paid_at' => ['nullable', 'date'],
            'failure_code' => ['nullable', 'string', 'max:100'],
            'failure_message' => ['nullable', 'string', 'max:255'],
        ];
    }
}
