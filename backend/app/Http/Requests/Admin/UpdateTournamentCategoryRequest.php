<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTournamentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_teams' => ['nullable', 'integer', 'in:32,64,128'],
            'wildcard_slots' => ['nullable', 'integer', 'min:0'],
            'entry_fee_amount' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'acceptance_type' => ['nullable', 'in:immediate,waitlist'],
            'acceptance_window_hours' => ['nullable', 'integer', 'min:1'],
            'seeding_rule' => ['nullable', 'in:ranking_desc,manual,fifo'],
            'min_fip_rank' => ['nullable', 'integer', 'min:1'],
            'max_fip_rank' => ['nullable', 'integer', 'min:1', 'gte:min_fip_rank'],
            'min_fep_rank' => ['nullable', 'integer', 'min:1'],
            'max_fep_rank' => ['nullable', 'integer', 'min:1', 'gte:min_fep_rank'],
            'status_id' => ['nullable', 'exists:statuses,id'],
        ];
    }
}
