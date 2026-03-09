<?php

namespace App\Http\Controllers;

use App\Http\Resources\StatusResource;
use App\Models\Status;
use App\Support\StatusResolver;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    public function index(Request $request)
    {
        $module = $request->query('module');

        $statuses = $module
            ? StatusResolver::getByModule($module)
            : Status::query()->orderBy('module')->orderBy('sort_order')->get();

        return StatusResource::collection($statuses);
    }
}
