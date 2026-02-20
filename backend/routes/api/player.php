<?php

use App\Http\Controllers\Player\MeController;
use App\Http\Controllers\Player\PaymentController as PlayerPaymentController;
use App\Http\Controllers\Player\BracketController as PlayerBracketController;
use App\Http\Controllers\Player\RankingController as PlayerRankingController;
use App\Http\Controllers\Player\RegistrationController as PlayerRegistrationController;
use App\Http\Controllers\Player\TeamInviteController as PlayerTeamInviteController;
use App\Http\Controllers\Player\TeamController;
use App\Http\Controllers\Player\TournamentController as PlayerTournamentController;
use App\Http\Controllers\Player\WildcardController as PlayerWildcardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:player'])->prefix('player')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
    Route::get('/ranking', [PlayerRankingController::class, 'show']);
    Route::put('/ranking', [PlayerRankingController::class, 'update']);
    Route::get('/tournaments', [PlayerTournamentController::class, 'index']);
    Route::get('/tournaments/{tournament}', [PlayerTournamentController::class, 'show']);
    Route::get('/brackets', [PlayerBracketController::class, 'index']);
    Route::get('/wildcards/{token}', [PlayerWildcardController::class, 'show']);
    Route::post('/wildcards/{token}/claim', [PlayerWildcardController::class, 'claim']);
    Route::post('/teams', [TeamController::class, 'store']);
    Route::get('/team-invites', [PlayerTeamInviteController::class, 'index']);
    Route::post('/team-invites/claim', [PlayerTeamInviteController::class, 'claim']);
    Route::post('/team-invites/{teamInvite}/accept', [PlayerTeamInviteController::class, 'accept']);
    Route::post('/registrations', [PlayerRegistrationController::class, 'store']);
    Route::get('/registrations', [PlayerRegistrationController::class, 'index']);
    Route::get('/payments', [PlayerPaymentController::class, 'index']);
});
