<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Services\StatusService;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::query()->with('status')->orderByDesc('created_at');

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->query('status_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        return LeadResource::collection($query->get());
    }

    public function update(UpdateLeadRequest $request, Lead $lead)
    {
        $data = $request->validated();
        if (! empty($data['status_id'])) {
            app(StatusService::class)->transition($lead, 'lead', (int) $data['status_id'], $request->user()?->id, 'admin_update');
            unset($data['status_id']);
        }

        if ($data) {
            $lead->update($data);
        }
        $lead->load('status');

        return new LeadResource($lead);
    }
}
