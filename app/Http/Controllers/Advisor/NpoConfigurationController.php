<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\NpoLegalStructure;
use App\Enums\NpoSocialEnterpriseType;
use App\Enums\NpoTiritiMode;
use App\Http\Controllers\Controller;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Npo\NpoEngagementConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class NpoConfigurationController extends Controller
{
    public function update(Request $request, NpoEngagement $npoEngagement, NpoEngagementConfiguration $configuration): RedirectResponse
    {
        Gate::authorize('update', $npoEngagement->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'legal_structure' => ['required', Rule::enum(NpoLegalStructure::class)],
            'tiriti_mode' => ['required', Rule::enum(NpoTiritiMode::class)],
            'tiriti_decision_guide' => ['required', 'array'],
            'tiriti_decision_guide.'.NpoEngagementConfiguration::GUIDE_GOVERNANCE_OBLIGATION => ['required', 'boolean'],
            'tiriti_decision_guide.'.NpoEngagementConfiguration::GUIDE_MANA_WHENUA_RELATIONSHIP => ['required', 'boolean'],
            'tiriti_decision_guide.'.NpoEngagementConfiguration::GUIDE_TIRITI_OUTCOMES => ['required', 'boolean'],
            'social_enterprise' => ['required', 'boolean'],
            'social_enterprise_type' => ['nullable', Rule::enum(NpoSocialEnterpriseType::class)],
            'commercial_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'mission_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $socialEnterprise = (bool) $validated['social_enterprise'];
        $commercialWeight = Arr::get($validated, 'commercial_weight');
        $missionWeight = Arr::get($validated, 'mission_weight');

        if ($socialEnterprise) {
            if (! is_numeric($commercialWeight) || ! is_numeric($missionWeight)) {
                throw ValidationException::withMessages([
                    'commercial_weight' => 'Commercial and mission weights are required for social enterprise scoring.',
                ]);
            }

            $commercialWeight = (int) $commercialWeight;
            $missionWeight = (int) $missionWeight;

            if ($commercialWeight + $missionWeight !== 100) {
                throw ValidationException::withMessages([
                    'mission_weight' => 'Commercial and mission weights must sum to 100.',
                ]);
            }

            if (! is_string(Arr::get($validated, 'social_enterprise_type'))) {
                throw ValidationException::withMessages([
                    'social_enterprise_type' => 'Select a social enterprise type.',
                ]);
            }
        }

        try {
            $configuration->configure($npoEngagement, $user, $validated);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'npo_engagement' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'npo-configuration-updated');
    }
}
