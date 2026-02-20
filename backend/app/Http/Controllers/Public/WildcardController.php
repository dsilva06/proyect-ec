<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\WildcardInvitationResource;
use App\Models\Invitation;
use Illuminate\Http\Request;

class WildcardController extends Controller
{
    public function show(string $token)
    {
        $invite = Invitation::query()
            ->where('token', $token)
            ->where('purpose', 'wildcard')
            ->with(['status', 'tournamentCategory.category', 'tournamentCategory.tournament.status'])
            ->firstOrFail();

        return new WildcardInvitationResource($invite);
    }
}
