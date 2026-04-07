<?php

use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\Public\LeadController as PublicLeadController;
use App\Http\Controllers\Public\TeamInviteController as PublicTeamInviteController;
use App\Http\Controllers\Public\TournamentController as PublicTournamentController;
use App\Http\Controllers\Public\WildcardController as PublicWildcardController;
use Illuminate\Support\Facades\Route;

Route::post('/public/leads', [PublicLeadController::class, 'store']);
Route::post('/stripe/webhook', StripeWebhookController::class);
Route::get('/public/tournaments', [PublicTournamentController::class, 'index']);
Route::get('/public/wildcards/{token}', [PublicWildcardController::class, 'show']);
Route::get('/team-invites/{token}', [PublicTeamInviteController::class, 'show']);
