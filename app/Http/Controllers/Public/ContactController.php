<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreContactRequest;
use App\Mail\ProspectLeadReceived;
use App\Models\ProspectLead;
use App\Support\Public\EngagementTypeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('public/contact', [
            'engagementOptions' => EngagementTypeCatalog::selectOptions(),
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $lead = ProspectLead::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'engagement_interest' => $data['engagement_interest'] ?? null,
            'message' => $data['message'],
            'source' => 'public_contact_form',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        $ownerEmail = config('mail.owner_address');

        if ($ownerEmail) {
            try {
                Mail::to($ownerEmail)->send(new ProspectLeadReceived($lead));
            } catch (\Throwable $e) {
                Log::warning('Failed to email prospect lead notification', [
                    'lead_id' => $lead->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('public.contact.thanks');
    }

    public function thanks(Request $request): Response
    {
        return Inertia::render('public/contact-thanks');
    }
}
