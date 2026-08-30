<?php

declare(strict_types=1);

namespace App\Services\Journeys;

use App\Enums\EngagementType;
use App\Models\ServiceActivation;

final class ServiceJourneyPrograms
{
    public const VERSION = '2026-08';

    /**
     * @return array<array-key, mixed>
     */
    public static function for(string $serviceKey): array
    {
        $serviceKey = self::normalise($serviceKey);

        $labels = match ($serviceKey) {
            EngagementType::DUE_DILIGENCE->value => [
                'title' => 'Due Diligence journey',
                'milestones' => [
                    'scope' => 'DD scope confirmed',
                    'evidence' => 'Evidence pack shared',
                    'advisor_review' => 'DD review complete',
                    'outputs' => 'Decision pack ready',
                    'outcomes' => 'Outcome check complete',
                ],
            ],
            EngagementType::POST_ACQUISITION_ADVISORY->value => [
                'title' => 'Post-acquisition journey',
                'milestones' => [
                    'scope' => 'Post-close scope confirmed',
                    'evidence' => 'Gap evidence shared',
                    'advisor_review' => 'Gap review complete',
                    'outputs' => 'First-100-days plan ready',
                    'outcomes' => 'Outcome check complete',
                ],
            ],
            EngagementType::NPO->value => [
                'title' => 'NPO advisory journey',
                'milestones' => [
                    'scope' => 'NPO scope confirmed',
                    'evidence' => 'Governance and impact evidence shared',
                    'advisor_review' => 'NPO review complete',
                    'outputs' => 'Board-ready outputs released',
                    'outcomes' => 'Impact follow-up complete',
                ],
            ],
            ServiceActivation::SERVICE_INTEGRATION_SCOPING => [
                'title' => 'Integration scoping journey',
                'milestones' => [
                    'scope' => 'Scoping brief confirmed',
                    'evidence' => 'Systems evidence shared',
                    'advisor_review' => 'Scope review complete',
                    'outputs' => 'Integration scope ready',
                    'outcomes' => 'Scope outcome check complete',
                ],
            ],
            ServiceActivation::SERVICE_INTEGRATION => [
                'title' => 'Integration delivery journey',
                'milestones' => [
                    'scope' => 'Delivery plan confirmed',
                    'evidence' => 'Implementation evidence shared',
                    'advisor_review' => 'Delivery review complete',
                    'outputs' => 'Handover ready',
                    'outcomes' => 'Benefits check complete',
                ],
            ],
            EngagementType::ENTREPRENEUR_MODULE->value => [
                'title' => 'Entrepreneur journey',
                'milestones' => [
                    'scope' => 'Journey scope confirmed',
                    'evidence' => 'Business evidence shared',
                    'advisor_review' => 'Advisor review complete',
                    'outputs' => 'Business outputs ready',
                    'outcomes' => 'Outcome check complete',
                ],
            ],
            default => [
                'title' => 'Advisory journey',
                'milestones' => [
                    'scope' => 'Advisory scope confirmed',
                    'evidence' => 'Business evidence shared',
                    'advisor_review' => 'Advisory review complete',
                    'outputs' => 'Priority roadmap released',
                    'outcomes' => 'Outcome check complete',
                ],
            ],
        };

        return [
            'service_key' => $serviceKey,
            'version' => self::VERSION,
            'title' => $labels['title'],
            'milestones' => collect($labels['milestones'])
                ->map(function (string $label, string $key): array {
                    return [
                        'key' => $key,
                        'label' => $label,
                        'owner' => match ($key) {
                            'advisor_review', 'outputs' => 'fsa',
                            default => 'client',
                        },
                        // Recognition rewards only verified client contribution and follow-through.
                        'points' => match ($key) {
                            'scope' => 25,
                            'evidence' => 75,
                            'outcomes' => 40,
                            default => 0,
                        },
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    public static function normalise(string $serviceKey): string
    {
        return match ($serviceKey) {
            ServiceActivation::SERVICE_ENTREPRENEUR => EngagementType::ENTREPRENEUR_MODULE->value,
            EngagementType::FOUNDING_ADVISORY->value => EngagementType::STANDARD_ADVISORY->value,
            default => $serviceKey,
        };
    }
}
