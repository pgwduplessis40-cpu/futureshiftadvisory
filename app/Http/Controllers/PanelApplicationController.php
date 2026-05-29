<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PanelMember;
use App\Models\User;
use App\Services\Panels\PanelOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PanelApplicationController extends Controller
{
    public function store(Request $request, PanelOnboarding $panelOnboarding): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(in_array($user->user_type, PanelMember::panelTypes(), true), 403);

        $validated = $request->validate([
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

        $application = [
            'company' => $validated['company'],
            'trading_name' => $validated['trading_name'] ?? null,
            'fsp_number' => $validated['fsp_number'] ?? null,
            'regions' => $this->csvList($validated['regions'] ?? null),
            'specialties' => $this->csvList($validated['specialties'] ?? null),
            'bio' => $validated['bio'] ?? null,
            'professional_memberships' => $this->csvList($validated['professional_memberships'] ?? null),
        ];

        $panelOnboarding->submitApplication($user, (string) $user->user_type, array_filter(
            $application,
            static fn (mixed $value): bool => $value !== null && $value !== [],
        ));

        return to_route('dashboard')->with('status', 'panel-application-submitted');
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
