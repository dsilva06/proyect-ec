<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamInviteResource;
use App\Models\TeamInvite;
use Illuminate\Http\Request;

class TeamInviteController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invite = TeamInvite::query()
            ->where('token', $token)
            ->with(['team', 'status'])
            ->firstOrFail();

        return new TeamInviteResource($invite);
    }
}
