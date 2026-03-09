<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreLeadRequest;
use App\Http\Resources\LeadResource;
use App\Mail\LeadReceivedMail;
use App\Models\Lead;
use App\Support\StatusResolver;
use Illuminate\Support\Facades\Mail;

class LeadController extends Controller
{
    public function store(StoreLeadRequest $request)
    {
        $data = $request->validated();
        $data['status_id'] = StatusResolver::getId('lead', 'new');

        $lead = Lead::create($data);
        $lead->load('status');

        $inbox = (string) config('mail.leads_inbox', '');
        if ($inbox !== '') {
            Mail::to($inbox)->queue(new LeadReceivedMail($lead));
        }

        return new LeadResource($lead);
    }
}
