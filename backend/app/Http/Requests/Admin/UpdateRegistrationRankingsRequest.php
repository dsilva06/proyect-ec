<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegistrationRankingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rankings' => ['required', 'array', 'size:2'],
            'rankings.*.slot' => ['required', 'integer', 'in:1,2', 'distinct'],
            'rankings.*.ranking_value' => ['nullable', 'integer', 'min:1'],
            'rankings.*.ranking_source' => ['nullable', 'in:FEP,FIP'],
            'rankings.*.is_verified' => ['required', 'boolean'],
        ];
    }
}
