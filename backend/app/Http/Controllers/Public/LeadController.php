<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Support\StatusResolver;

class LeadController extends Controller
{
    public function store(StoreLeadRequest $request)
    {
        $data = $request->validated();
        $data['status_id'] = StatusResolver::getId('lead', 'new');

        $lead = Lead::create($data);
        $lead->load('status');

        return new LeadResource($lead);
    }
}
