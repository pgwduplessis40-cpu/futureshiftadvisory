<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\NpoSocialEnterpriseType;
use App\Enums\NpoTiritiMode;
use App\Events\NpoEngagementWeightingChanged;
use App\Models\Client;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use InvalidArgumentException;

final class NpoEngagementConfiguration
{
    public const GUIDE_GOVERNANCE_OBLIGATION = 'governance_obligation';

    public const GUIDE_MANA_WHENUA_RELATIONSHIP = 'mana_whenua_relationship';

    public const GUIDE_TIRITI_OUTCOMES = 'tiriti_outcomes';

    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @return array<int, array{key:string, label:string}>
     */
    public static function tiritiDecisionQuestions(): array
    {
        return [
            [
                'key' => self::GUIDE_GOVERNANCE_OBLIGATION,
                'label' => 'Constitution, funder, or board obligations explicitly name Te Tiriti.',
            ],
            [
                'key' => self::GUIDE_MANA_WHENUA_RELATIONSHIP,
                'label' => 'The organisation has active mana whenua or Maori partnership commitments.',
            ],
            [
                'key' => self::GUIDE_TIRITI_OUTCOMES,
                'label' => 'The work affects Maori outcomes, equity, access, or cultural safety.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    public function suggestTiritiMode(array $answers): NpoTiritiMode
    {
        return in_array(true, $this->normaliseDecisionGuide($answers), true)
            ? NpoTiritiMode::Standalone
            : NpoTiritiMode::Woven;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function configure(NpoEngagement $engagement, User $actor, array $input): NpoEngagement
    {
        $this->assertFullEngagement($engagement);

        $legalStructure = NpoLegalStructure::from((string) $input['legal_structure']);
        $tiritiMode = NpoTiritiMode::from((string) $input['tiriti_mode']);
        $decisionGuide = $this->normaliseDecisionGuide((array) ($input['tiriti_decision_guide'] ?? []));
        $suggestedTiritiMode = $this->suggestTiritiMode($decisionGuide);
        $socialEnterprise = (bool) $input['social_enterprise'];
        $socialEnterpriseType = null;
        $commercialWeight = null;
        $missionWeight = null;

        if ($socialEnterprise) {
            $socialEnterpriseType = NpoSocialEnterpriseType::from((string) $input['social_enterprise_type']);
            $commercialWeight = (int) $input['commercial_weight'];
            $missionWeight = (int) $input['mission_weight'];

            if ($commercialWeight + $missionWeight !== 100) {
                throw new InvalidArgumentException('Social enterprise weights must sum to 100.');
            }
        }

        $before = $this->configurationSnapshot($engagement);
        $weightingBefore = $this->weightingSnapshot($engagement);

        $engagement->forceFill([
            'legal_structure' => $legalStructure,
            'tiriti_mode' => $tiritiMode,
            'tiriti_decision_guide' => $decisionGuide,
            'social_enterprise' => $socialEnterprise,
            'social_enterprise_type' => $socialEnterpriseType,
            'commercial_weight' => $commercialWeight,
            'mission_weight' => $missionWeight,
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $engagement = $engagement->refresh();
        $after = $this->configurationSnapshot($engagement);
        $weightingAfter = $this->weightingSnapshot($engagement);

        if ($before !== $after) {
            $this->audit->record('npo_engagement.configuration_updated', subject: $engagement, actor: $actor, before: $before, after: [
                ...$after,
                'tiriti_suggested_mode' => $suggestedTiritiMode->value,
            ]);
        }

        if ($weightingBefore !== $weightingAfter) {
            $this->audit->record('npo_engagement.weighting_changed', subject: $engagement, actor: $actor, before: $weightingBefore, after: [
                ...$weightingAfter,
                'recompute_deferred_to' => 'WO-N09',
            ]);

            event(new NpoEngagementWeightingChanged(
                npoEngagementId: (string) $engagement->getKey(),
                clientId: (string) $engagement->client_id,
                socialEnterprise: (bool) $engagement->social_enterprise,
                socialEnterpriseType: $engagement->social_enterprise_type?->value,
                commercialWeight: $engagement->commercial_weight,
                missionWeight: $engagement->mission_weight,
                actorUserId: (string) $actor->getKey(),
            ));
        }

        return $engagement;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function clientSummary(Client $client): ?array
    {
        $engagement = NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->whereIn('sub_type', [
                NpoEngagementSubType::StandardNpo->value,
                NpoEngagementSubType::SocialEnterprise->value,
            ])
            ->latest()
            ->first();

        return $engagement instanceof NpoEngagement
            ? $this->summary($engagement)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(NpoEngagement $engagement): array
    {
        $this->assertFullEngagement($engagement);

        $decisionGuide = $this->normaliseDecisionGuide($engagement->tiriti_decision_guide ?? []);
        $suggestedTiritiMode = $this->suggestTiritiMode($decisionGuide);

        return [
            'id' => $engagement->id,
            'client_id' => $engagement->client_id,
            'sub_type' => $engagement->sub_type->value,
            'sub_type_label' => $engagement->sub_type->label(),
            'legal_structure' => $engagement->legal_structure->value,
            'legal_structure_label' => $engagement->legal_structure->label(),
            'legal_structure_options' => NpoLegalStructure::options(),
            'tiriti_mode' => $engagement->tiriti_mode?->value,
            'tiriti_mode_label' => $engagement->tiriti_mode?->label(),
            'tiriti_mode_options' => NpoTiritiMode::options(),
            'tiriti_decision_questions' => self::tiritiDecisionQuestions(),
            'tiriti_decision_guide' => $decisionGuide,
            'tiriti_suggested_mode' => $suggestedTiritiMode->value,
            'social_enterprise' => (bool) $engagement->social_enterprise,
            'social_enterprise_type' => $engagement->social_enterprise_type?->value,
            'social_enterprise_type_label' => $engagement->social_enterprise_type?->label(),
            'social_enterprise_type_options' => NpoSocialEnterpriseType::options(),
            'commercial_weight' => $engagement->commercial_weight,
            'mission_weight' => $engagement->mission_weight,
            'update_url' => route('advisor.npo-engagements.configuration.update', $engagement, absolute: false),
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, bool>
     */
    private function normaliseDecisionGuide(array $answers): array
    {
        $normalised = [];

        foreach (self::tiritiDecisionQuestions() as $question) {
            $normalised[$question['key']] = (bool) ($answers[$question['key']] ?? false);
        }

        return $normalised;
    }

    private function assertFullEngagement(NpoEngagement $engagement): void
    {
        if (! in_array($engagement->sub_type, [NpoEngagementSubType::StandardNpo, NpoEngagementSubType::SocialEnterprise], true)) {
            throw new InvalidArgumentException('Full NPO configuration requires a standard NPO or social enterprise engagement.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function configurationSnapshot(NpoEngagement $engagement): array
    {
        return [
            'legal_structure' => $engagement->legal_structure?->value,
            'tiriti_mode' => $engagement->tiriti_mode?->value,
            'tiriti_decision_guide' => $this->normaliseDecisionGuide($engagement->tiriti_decision_guide ?? []),
            ...$this->weightingSnapshot($engagement),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function weightingSnapshot(NpoEngagement $engagement): array
    {
        return [
            'social_enterprise' => (bool) $engagement->social_enterprise,
            'social_enterprise_type' => $engagement->social_enterprise_type?->value,
            'commercial_weight' => $engagement->commercial_weight,
            'mission_weight' => $engagement->mission_weight,
        ];
    }
}
