<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\PlayerProfile;
use App\Models\TeamInvite;
use App\Models\User;
use App\Support\StatusResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password_hash' => Hash::make($validated['password']),
            'role' => 'player',
            'is_active' => true,
        ]);

        PlayerProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'dni' => $validated['dni'] ?? null,
                'province_state' => $validated['province_state'] ?? 'Unknown',
                'ranking_source' => 'NONE',
                'ranking_value' => null,
                'ranking_updated_at' => null,
            ],
        );

        TeamInvite::query()
            ->where('invited_email', $user->email)
            ->whereNull('invited_user_id')
            ->where('status_id', StatusResolver::getId('team_invite', 'sent'))
            ->update(['invited_user_id' => $user->id]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('playerProfile')),
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password_hash)) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'User is inactive'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('playerProfile')),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => new UserResource($request->user()->load('playerProfile'))]);
    }
}
