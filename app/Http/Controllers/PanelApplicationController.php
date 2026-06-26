<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PanelMember;
use App\Models\User;
use App\Notifications\PanelApplicationResubmittedNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Panels\PanelOnboarding;
use App\Support\FspNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

final class PanelApplicationController extends Controller
{
    public function store(Request $request, PanelOnboarding $panelOnboarding): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(in_array($user->user_type, PanelMember::panelTypes(), true), 403);

        $application = $this->applicationPayload($this->validatedApplication($request, $user));

        $panelOnboarding->submitApplication($user, (string) $user->user_type, array_filter(
            $application,
            static fn (mixed $value): bool => $value !== null && $value !== [],
        ));

        return to_route('dashboard')->with('status', 'panel-application-submitted');
    }

    public function update(Request $request, AuditWriter $auditWriter): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(in_array($user->user_type, PanelMember::panelTypes(), true), 403);

        $member = PanelMember::query()
            ->where('user_id', $user->getKey())
            ->where('panel_type', $user->user_type)
            ->latest('updated_at')
            ->first();
        abort_unless($member instanceof PanelMember, 404);

        $application = is_array($member->application) ? $member->application : [];
        $payload = $this->applicationPayload(
            $this->validatedApplication($request, $user),
            includeMissing: false,
        );
        $resubmitting = $member->status === PanelMember::STATUS_INFORMATION_REQUESTED;
        $before = [
            'status' => $member->status,
            'application' => $application,
            'fsp_number' => $member->fsp_number,
            'fsp_status' => $member->fsp_status,
        ];

        $updates = [
            'application' => [
                ...$application,
                ...$payload,
            ],
        ];

        if ($resubmitting) {
            $previousReview = is_array($application['review'] ?? null)
                ? $application['review']
                : [];
            $updates['status'] = PanelMember::STATUS_APPLICATION_PENDING;
            $updates['applied_at'] = now();
            $updates['application']['review'] = [
                'decision' => 'resubmitted',
                'reason' => 'Updated panel application details have been resubmitted for review.',
                'previous_reason' => is_string($previousReview['reason'] ?? null)
                    ? $previousReview['reason']
                    : null,
                'resubmitted_by_user_id' => $user->getKey(),
                'resubmitted_at' => now()->toIso8601String(),
            ];
        }

        if ($member->panel_type === PanelMember::TYPE_BROKER) {
            $fspNumber = $this->normaliseFspNumber((string) ($payload['fsp_number'] ?? ''));
            $updates['fsp_number'] = $fspNumber;
            $updates['application']['fsp_number'] = $fspNumber;

            if ($this->normaliseFspNumber((string) $member->fsp_number) !== $fspNumber) {
                $updates['fsp_status'] = PanelMember::FSP_STATUS_UNKNOWN;
                $updates['fsp_last_checked_at'] = null;
            }
        }

        $member->forceFill($updates)->save();
        $member->refresh()->loadMissing('user');

        $auditWriter->record(
            action: 'panel.profile_updated',
            subject: $member,
            actor: $user,
            before: $before,
            after: [
                'status' => $member->status,
                'panel_type' => $member->panel_type,
                'application' => $member->application,
                'fsp_number' => $member->fsp_number,
                'fsp_status' => $member->fsp_status,
            ],
        );

        if ($resubmitting) {
            $auditWriter->record(
                action: 'panel.application_resubmitted',
                subject: $member,
                actor: $user,
                before: $before,
                after: [
                    'status' => $member->status,
                    'panel_type' => $member->panel_type,
                    'application' => $member->application,
                ],
            );

            $this->notifyReviewersOfResubmission($member);

            return to_route('dashboard')->with('status', 'panel-application-resubmitted');
        }

        return to_route('dashboard')->with('status', 'panel-profile-updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedApplication(Request $request, User $user): array
    {
        return $request->validate([
            'company' => ['required', 'string', 'max:160'],
            'trading_name' => ['nullable', 'string', 'max:160'],
            'fsp_number' => [
                Rule::requiredIf($user->user_type === PanelMember::TYPE_BROKER),
                'nullable',
                'string',
                'max:40',
            ],
            'regions' => ['nullable', 'string', 'max:400'],
            'specialties' => ['nullable', 'string', 'max:400'],
            'bio' => ['nullable', 'string', 'max:1200'],
            'professional_memberships' => ['nullable', 'string', 'max:400'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applicationPayload(array $validated, bool $includeMissing = true): array
    {
        $payload = [
            'company' => $validated['company'],
        ];

        if ($includeMissing || array_key_exists('trading_name', $validated)) {
            $payload['trading_name'] = $validated['trading_name'] ?? null;
        }

        if ($includeMissing || array_key_exists('fsp_number', $validated)) {
            $payload['fsp_number'] = isset($validated['fsp_number'])
                ? $this->normaliseFspNumber((string) $validated['fsp_number'])
                : null;
        }

        if ($includeMissing || array_key_exists('regions', $validated)) {
            $payload['regions'] = $this->csvList($validated['regions'] ?? null);
        }

        if ($includeMissing || array_key_exists('specialties', $validated)) {
            $payload['specialties'] = $this->csvList($validated['specialties'] ?? null);
        }

        if ($includeMissing || array_key_exists('bio', $validated)) {
            $payload['bio'] = $validated['bio'] ?? null;
        }

        if ($includeMissing || array_key_exists('professional_memberships', $validated)) {
            $payload['professional_memberships'] = $this->csvList($validated['professional_memberships'] ?? null);
        }

        return $payload;
    }

    private function normaliseFspNumber(string $value): string
    {
        return FspNumber::normalise($value);
    }

    private function notifyReviewersOfResubmission(PanelMember $member): void
    {
        $recipients = User::query()
            ->whereIn('user_type', [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN])
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new PanelApplicationResubmittedNotification($member));
    }

    /**
     * @return array<int, string>
     */
    private function csvList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
