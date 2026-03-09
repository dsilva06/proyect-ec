<?php

use App\Http\Controllers\Admin\BracketController;
use App\Http\Controllers\Admin\BracketSlotController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Admin\MatchController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlayerRankingController as AdminPlayerRankingController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\TournamentCategoryController;
use App\Http\Controllers\Admin\TournamentController;
use App\Http\Controllers\Admin\WildcardController as AdminWildcardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/tournaments', [TournamentController::class, 'index']);
    Route::post('/tournaments', [TournamentController::class, 'store']);
    Route::get('/tournaments/{tournament}', [TournamentController::class, 'show']);
    Route::put('/tournaments/{tournament}', [TournamentController::class, 'update']);
    Route::patch('/tournaments/{tournament}/status', [TournamentController::class, 'updateStatus']);
    Route::delete('/tournaments/{tournament}', [TournamentController::class, 'destroy']);

    Route::post('/tournaments/{tournament}/categories', [TournamentCategoryController::class, 'store']);
    Route::patch('/tournament-categories/{tournamentCategory}', [TournamentCategoryController::class, 'update']);
    Route::delete('/tournament-categories/{tournamentCategory}', [TournamentCategoryController::class, 'destroy']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);

    Route::get('/registrations', [RegistrationController::class, 'index']);
    Route::patch('/registrations/{registration}', [RegistrationController::class, 'update']);
    Route::patch('/registrations/{registration}/rankings', [RegistrationController::class, 'updateRankings']);

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::patch('/payments/{payment}', [PaymentController::class, 'update']);

    Route::get('/brackets', [BracketController::class, 'index']);
    Route::post('/brackets', [BracketController::class, 'store']);
    Route::patch('/brackets/{bracket}', [BracketController::class, 'update']);
    Route::post('/brackets/{bracket}/generate', [BracketController::class, 'generate']);
    Route::delete('/brackets/{bracket}', [BracketController::class, 'destroy']);

    Route::post('/bracket-slots', [BracketSlotController::class, 'store']);
    Route::patch('/bracket-slots/{bracketSlot}', [BracketSlotController::class, 'update']);

    Route::get('/matches', [MatchController::class, 'index']);
    Route::post('/matches', [MatchController::class, 'store']);
    Route::patch('/matches/{match}', [MatchController::class, 'update']);
    Route::delete('/matches/{match}', [MatchController::class, 'destroy']);
    Route::post('/matches/{match}/delay', [MatchController::class, 'delay']);

    Route::get('/leads', [AdminLeadController::class, 'index']);
    Route::patch('/leads/{lead}', [AdminLeadController::class, 'update']);

    Route::get('/players', [AdminPlayerRankingController::class, 'index']);
    Route::patch('/players/{user}/ranking', [AdminPlayerRankingController::class, 'update']);
    Route::patch('/players/{user}/ranking-fep', [AdminPlayerRankingController::class, 'updateFep']);
    Route::get('/players/{user}/palmares', [AdminPlayerRankingController::class, 'showPalmares']);
    Route::get('/players/{user}/prize-payouts', [AdminPlayerRankingController::class, 'listPrizePayouts']);
    Route::post('/players/{user}/prize-payouts', [AdminPlayerRankingController::class, 'storePrizePayout']);
    Route::patch('/player-prize-payouts/{playerPrizePayout}', [AdminPlayerRankingController::class, 'updatePrizePayout']);
    Route::delete('/player-prize-payouts/{playerPrizePayout}', [AdminPlayerRankingController::class, 'destroyPrizePayout']);
    Route::get('/internal-ranking-rule', [AdminPlayerRankingController::class, 'showInternalRule']);
    Route::patch('/internal-ranking-rule', [AdminPlayerRankingController::class, 'updateInternalRule']);

    Route::get('/wildcards', [AdminWildcardController::class, 'index']);
    Route::post('/wildcards', [AdminWildcardController::class, 'store']);
    Route::patch('/wildcards/{wildcard}', [AdminWildcardController::class, 'update']);
    Route::delete('/wildcards/{wildcard}', [AdminWildcardController::class, 'destroy']);
});
