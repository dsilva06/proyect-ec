<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AcceptanceService;
use App\Services\RankingService;
use Illuminate\Http\Request;

class RankingController extends Controller
{
    public function show(Request $request)
    {
        return new UserResource($request->user()->load('playerProfile'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'ranking_value' => ['nullable', 'integer', 'min:1'],
            'ranking_source' => ['nullable', 'string', 'in:FEP,FIP,NONE'],
        ]);

        $user = $request->user();

        app(RankingService::class)->updateRanking(
            $user,
            $data['ranking_value'] ?? null,
            $data['ranking_source'] ?? null
        );

        app(AcceptanceService::class)->recalculateForUser($user);

        return new UserResource($user->fresh()->load('playerProfile'));
    }
}
