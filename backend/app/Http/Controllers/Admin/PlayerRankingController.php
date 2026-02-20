<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AcceptanceService;
use App\Services\RankingService;
use Illuminate\Http\Request;

class PlayerRankingController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()
            ->where('role', 'player')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return UserResource::collection($query->with('playerProfile')->get());
    }

    public function update(Request $request, User $user)
    {
        if ($user->role !== 'player') {
            return response()->json(['message' => 'Solo se permite actualizar rankings de jugadores.'], 404);
        }

        $data = $request->validate([
            'ranking_value' => ['nullable', 'integer', 'min:1'],
            'ranking_source' => ['nullable', 'string', 'in:FEP,FIP,NONE'],
        ]);

        app(RankingService::class)->updateRanking(
            $user,
            $data['ranking_value'] ?? null,
            $data['ranking_source'] ?? null
        );

        app(AcceptanceService::class)->recalculateForUser($user);

        return new UserResource($user->fresh()->load('playerProfile'));
    }
}
