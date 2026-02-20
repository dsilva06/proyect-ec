<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\TournamentResource;
use App\Models\Tournament;
use App\Support\StatusResolver;

class TournamentController extends Controller
{
    public function index()
    {
        $publishedId = StatusResolver::getId('tournament', 'published');
        $openId = StatusResolver::getId('tournament', 'registration_open');

        $query = Tournament::query()
            ->with(['status', 'categories.category', 'categories.status'])
            ->orderBy('start_date');

        if ($publishedId || $openId) {
            $query->whereIn('status_id', array_filter([$publishedId, $openId]));
        }

        return TournamentResource::collection($query->get());
    }
}
