<?php

namespace App\Http\Requests\Player;

use App\Models\OpenEntry;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tournament_category_id' => ['nullable', 'exists:tournament_categories,id'],
            'tournament_id' => ['nullable', 'exists:tournaments,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'segment' => ['nullable', 'string', 'in:'.OpenEntry::SEGMENT_MEN.','.OpenEntry::SEGMENT_WOMEN],
            'partner_email' => ['nullable', 'email', 'max:255'],
            'partner_first_name' => ['nullable', 'string', 'max:255'],
            'partner_last_name' => ['nullable', 'string', 'max:255'],
            'partner_dni' => ['nullable', 'string', 'max:50'],
            'self_ranking_value' => ['nullable', 'integer', 'min:1', 'different:partner_ranking_value'],
            'self_ranking_source' => ['nullable', 'string', 'in:FEP,FIP'],
            'partner_ranking_value' => ['nullable', 'integer', 'min:1'],
            'partner_ranking_source' => ['nullable', 'string', 'in:FEP,FIP'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasTournamentId = filled($this->input('tournament_id'));
            $hasTournamentCategoryId = filled($this->input('tournament_category_id'));

            if ($hasTournamentId && $hasTournamentCategoryId) {
                $validator->errors()->add('tournament_id', 'Provide either tournament_id or tournament_category_id, not both.');
                $validator->errors()->add('tournament_category_id', 'Provide either tournament_id or tournament_category_id, not both.');

                return;
            }

            if ($hasTournamentId) {
                $this->validateOpenIntakeRequest($validator);

                return;
            }

            $this->validateCategoryRegistrationRequest($validator, $hasTournamentCategoryId);
        });
    }

    private function validateOpenIntakeRequest(Validator $validator): void
    {
        foreach (['segment', 'partner_email', 'partner_first_name', 'partner_last_name', 'partner_dni'] as $field) {
            if (! filled($this->input($field))) {
                $validator->errors()->add($field, "The {$field} field is required for OPEN signup.");
            }
        }

        if (filled($this->input('team_id'))) {
            $validator->errors()->add('team_id', 'OPEN signup does not support reusing an existing team.');
        }

        if (
            filled($this->input('self_ranking_value'))
            || filled($this->input('self_ranking_source'))
            || filled($this->input('partner_ranking_value'))
            || filled($this->input('partner_ranking_source'))
        ) {
            $validator->errors()->add('ranking', 'OPEN intake signup does not accept ranking fields.');
        }

        $tournament = Tournament::query()->find((int) $this->input('tournament_id'));
        if (! $tournament) {
            return;
        }

        if (strtolower((string) $tournament->mode) !== 'open') {
            $validator->errors()->add('tournament_id', 'OPEN intake signup is only available for OPEN tournaments.');
        }

        if ((string) $tournament->classification_method !== Tournament::CLASSIFICATION_REFEREE_ASSIGNED) {
            $validator->errors()->add('tournament_id', 'This tournament does not use referee-assigned OPEN intake signup.');
        }
    }

    private function validateCategoryRegistrationRequest(Validator $validator, bool $hasTournamentCategoryId): void
    {
        if (! $hasTournamentCategoryId) {
            $validator->errors()->add('tournament_category_id', 'The tournament_category_id field is required.');

            return;
        }

        $category = TournamentCategory::query()
            ->with('tournament')
            ->find((int) $this->input('tournament_category_id'));

        if (
            $category
            && strtolower((string) ($category->tournament?->mode ?? '')) === 'open'
            && (string) ($category->tournament?->classification_method ?? '') === Tournament::CLASSIFICATION_REFEREE_ASSIGNED
        ) {
            $validator->errors()->add('tournament_id', 'Referee-assigned OPEN signup must use tournament_id instead of tournament_category_id.');
        }

        if (! filled($this->input('team_id')) && ! filled($this->input('partner_email'))) {
            $validator->errors()->add('partner_email', 'The partner_email field is required when team_id is not provided.');
        }
    }
}
