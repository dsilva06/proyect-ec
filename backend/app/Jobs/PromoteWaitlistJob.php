<?php

namespace App\Jobs;

use App\Models\TournamentCategory;
use App\Services\AcceptanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PromoteWaitlistJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private ?int $tournamentCategoryId = null)
    {
    }

    public function handle(AcceptanceService $acceptanceService): void
    {
        if ($this->tournamentCategoryId) {
            $acceptanceService->recalculateForTournamentCategory($this->tournamentCategoryId);
            return;
        }

        $categoryIds = TournamentCategory::query()->pluck('id');
        foreach ($categoryIds as $categoryId) {
            $acceptanceService->recalculateForTournamentCategory($categoryId);
        }
    }
}
