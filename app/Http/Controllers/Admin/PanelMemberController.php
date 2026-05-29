<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PanelMember;
use App\Models\User;
use App\Services\Panels\PanelOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PanelMemberController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/panels/Index', [
            'members' => PanelMember::query()
                ->with('user')
                ->whereIn('status', [
                    PanelMember::STATUS_APPLICATION_PENDING,
                    PanelMember::STATUS_INFORMATION_REQUESTED,
                    PanelMember::STATUS_APPROVED_PENDING_AGREEMENT,
                    PanelMember::STATUS_DECLINED,
                ])
                ->orderByRaw("case status when 'application_pending' then 0 when 'information_requested' then 1 when 'approved_pending_agreement' then 2 else 3 end")
                ->latest('applied_at')
                ->get()
                ->map(fn (PanelMember $member): array => [
                    'id' => $member->id,
                    'panel_type' => $member->panel_type,
                    'status' => $member->status,
                    'name' => $member->user?->name ?? (string) data_get($member->application, 'company', 'Panel applicant'),
                    'email' => $member->user?->email,
                    'company' => data_get($member->application, 'company'),
                    'fsp_number' => data_get($member->application, 'fsp_number') ?? $member->fsp_number,
                    'regions' => data_get($member->application, 'regions', []),
                    'specialties' => data_get($member->application, 'specialties', []),
                    'review' => data_get($member->application, 'review'),
                    'applied_at' => $member->applied_at?->toIso8601String(),
                    'approve_url' => route('admin.panel-members.approve', $member, absolute: false),
                    'request_info_url' => route('admin.panel-members.request-info', $member, absolute: false),
                    'decline_url' => route('admin.panel-members.decline', $member, absolute: false),
                ])
                ->values(),
        ]);
    }

    public function approve(Request $request, PanelMember $panelMember, PanelOnboarding $onboarding): RedirectResponse
    {
        $admin = $this->admin($request);

        $validated = $request->validate([
            'terms' => ['nullable', 'array'],
        ]);

        $onboarding->approve($panelMember, $admin, $validated['terms'] ?? []);

        return to_route('admin.panel-members.index')->with('status', 'panel-member-approved');
    }

    public function requestInfo(Request $request, PanelMember $panelMember, PanelOnboarding $onboarding): RedirectResponse
    {
        $admin = $this->admin($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:4000'],
        ]);

        $onboarding->requestMoreInformation($panelMember, $admin, $validated['reason']);

        return to_route('admin.panel-members.index')->with('status', 'panel-member-information-requested');
    }

    public function decline(Request $request, PanelMember $panelMember, PanelOnboarding $onboarding): RedirectResponse
    {
        $admin = $this->admin($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:4000'],
        ]);

        $onboarding->decline($panelMember, $admin, $validated['reason']);

        return to_route('admin.panel-members.index')->with('status', 'panel-member-declined');
    }

    private function admin(Request $request): User
    {
        $admin = $request->user();
        abort_unless($admin instanceof User, 403);

        return $admin;
    }
}
