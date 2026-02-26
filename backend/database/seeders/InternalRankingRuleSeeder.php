<?php

namespace Database\Seeders;

use App\Models\InternalRankingRule;
use Illuminate\Database\Seeder;

class InternalRankingRuleSeeder extends Seeder
{
    public function run(): void
    {
        InternalRankingRule::query()->updateOrCreate(
            ['id' => 1],
            [
                'win_points' => 10,
                'final_played_bonus' => 5,
                'final_won_bonus' => 8,
                'updated_by' => null,
            ]
        );
    }
}
