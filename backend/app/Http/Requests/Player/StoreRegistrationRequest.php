<?php

namespace App\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tournament_category_id' => ['required', 'exists:tournament_categories,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'partner_email' => ['required', 'email', 'max:255'],
            'self_ranking_value' => ['nullable', 'integer', 'min:1', 'different:partner_ranking_value'],
            'self_ranking_source' => ['nullable', 'string', 'in:FEP,FIP'],
            'partner_ranking_value' => ['nullable', 'integer', 'min:1'],
            'partner_ranking_source' => ['nullable', 'string', 'in:FEP,FIP'],
        ];
    }
}
