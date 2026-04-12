<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Requests\Player\StoreTeamRequest;
use App\Http\Resources\TeamResource;
use App\Services\TeamService;

class TeamController extends Controller
{
    public function store(StoreTeamRequest $request, TeamService $teamService)
    {
        $team = $teamService->createTeam($request->user(), $request->validated());

        return new TeamResource($team->load('users'));
    }
}
