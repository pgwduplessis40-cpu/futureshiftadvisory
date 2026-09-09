<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use Illuminate\Support\Str;

final class AssessmentFeedback
{
    public function __construct(private readonly FounderChangeRequestMessage $changeRequestMessages) {}

    /**
     * @var array<string, array{what_is_missing:string,what_to_add_or_change:string,where_in_plan:string}>
     */
    private const PRIORITY_GUIDANCE = [
        'type of business' => [
            'what_is_missing' => 'The offer and the practical delivery model are not yet clear enough for someone to understand how the business will operate.',
            'what_to_add_or_change' => 'Describe the offer in plain language, who it is for, how work will be delivered, and who is responsible for each key activity.',
            'where_in_plan' => 'Foundation > Business type, location, and operating model',
        ],
        'location' => [
            'what_is_missing' => 'The chosen location and the reasons it suits the business need stronger practical support.',
            'what_to_add_or_change' => 'Explain where the business will operate, why that location fits the customer and delivery model, and any location-specific constraints.',
            'where_in_plan' => 'Foundation > Business type, location, and operating model',
        ],
        'means of doing business' => [
            'what_is_missing' => 'The day-to-day operating model needs more detail before the business can be assessed confidently.',
            'what_to_add_or_change' => 'Set out how customers will buy, how the work will be delivered, and the people, tools, and routines needed to deliver it consistently.',
            'where_in_plan' => 'Foundation > Business type, location, and operating model',
        ],
        'discuss the industry' => [
            'what_is_missing' => 'The market case needs clearer evidence that the chosen customers have this problem and will pay for the offer.',
            'what_to_add_or_change' => 'Add current industry context, define the target customer, and include specific demand evidence such as interviews, pilots, sales, or tested pricing.',
            'where_in_plan' => 'Market > Industry and customer demand',
        ],
        'what sets the business apart' => [
            'what_is_missing' => 'The plan does not yet make a convincing case for why customers would choose this business over the alternatives.',
            'what_to_add_or_change' => 'Name the main alternatives, explain the customer benefit that is different, and support the claim with customer evidence where possible.',
            'where_in_plan' => 'Market > What sets the business apart',
        ],
        'describe unique success factors' => [
            'what_is_missing' => 'The advantages that make the business more likely to succeed need clearer proof.',
            'what_to_add_or_change' => 'Identify the capabilities, relationships, assets, or experience that give the business an advantage, then show why each one matters.',
            'where_in_plan' => 'Strategy > Unique success factors',
        ],
        'mission and vision statement' => [
            'what_is_missing' => 'The direction of the business needs a clearer connection to the problem it solves and the outcome it is working toward.',
            'what_to_add_or_change' => 'Refine the mission and vision so they explain the problem, the customer, the intended change, and how they guide the business decisions.',
            'where_in_plan' => 'Foundation > Mission and vision',
        ],
        'intellectual property' => [
            'what_is_missing' => 'The plan needs a clearer decision about the intellectual property the business relies on, who owns it, and how it will be protected.',
            'what_to_add_or_change' => 'List the brand, methods, content, data, contracts, licences, or other assets that matter, record ownership, and set out the next protection steps.',
            'where_in_plan' => 'Legal & Operations > Intellectual property',
        ],
        'goals and objectives' => [
            'what_is_missing' => 'The goals provide direction but are not yet measurable enough to guide the next stage of delivery.',
            'what_to_add_or_change' => 'Turn the next goals into dated milestones with a clear measure of success, an owner, and the decision each milestone will support.',
            'where_in_plan' => 'Strategy > Goals and objectives',
        ],
        'culture' => [
            'what_is_missing' => 'The culture and customer promise need a clearer link to how the business will operate.',
            'what_to_add_or_change' => 'Describe the behaviours, values, and customer commitments that will guide decisions, hiring, partnerships, and daily delivery.',
            'where_in_plan' => 'Strategy > Culture',
        ],
        'legal environment' => [
            'what_is_missing' => 'The legal and operating obligations that could affect launch have not yet been worked through clearly enough.',
            'what_to_add_or_change' => 'List the relevant legal, privacy, compliance, supplier, employment, and industry obligations, with the next action and owner for each.',
            'where_in_plan' => 'Legal & Operations > Legal environment',
        ],
        'budget' => [
            'what_is_missing' => 'The financial assumptions need more evidence before the plan can show whether the business is viable.',
            'what_to_add_or_change' => 'Update the customer, price, margin, costs, cash timing, funding, and runway assumptions, and show what evidence supports each important number.',
            'where_in_plan' => 'Financial > Financial assumptions, Revenue model, Funding and support, and Budget',
        ],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function priorities(PlanAssessment $assessment): array
    {
        $assessment->loadMissing('ratingFramework.criteria');

        if (AssessmentScoring::hasIncompleteScores($assessment)) {
            return [];
        }

        $canCompareWithPreviousRound = ! $this->hasNonComparableScoringScope($assessment);
        $previous = $canCompareWithPreviousRound ? $this->previousAssessment($assessment) : null;
        $previousCriteria = $previous instanceof PlanAssessment
            ? collect(AssessmentScoring::criteriaPayload($previous))->keyBy('criterion_number')
            : collect();

        return collect(AssessmentScoring::criteriaPayload($assessment))
            ->sortBy('score')
            ->take(3)
            ->map(function (array $criterion) use ($previous, $previousCriteria): array {
                $name = (string) ($criterion['name'] ?? 'Plan requirement');
                $guidance = self::PRIORITY_GUIDANCE[strtolower(trim($name))] ?? $this->defaultGuidance($name);
                $criterionNumber = (int) ($criterion['criterion_number'] ?? 0);
                $score = round((float) ($criterion['score'] ?? 0), 1);
                $rationale = trim((string) ($criterion['rationale'] ?? ''));
                $sourceSections = is_array($criterion['source_sections'] ?? null)
                    ? $criterion['source_sections']
                    : [];
                $previousRow = $previousCriteria->get($criterionNumber);
                $previousScore = is_array($previousRow) && is_numeric($previousRow['score'] ?? null)
                    ? round((float) $previousRow['score'], 1)
                    : null;

                return [
                    'criterion_number' => $criterionNumber,
                    'title' => $name,
                    'score' => $score,
                    'previous_round' => $previous?->round,
                    'previous_score' => $previousScore,
                    'score_delta' => $previousScore === null ? null : round($score - $previousScore, 1),
                    'what_is_missing' => $this->assessmentFinding($rationale),
                    'what_to_add_or_change' => $guidance['what_to_add_or_change'],
                    'where_in_plan' => $this->reviewedSectionsLabel($sourceSections, $guidance['where_in_plan']),
                    'scoring_rationale' => $rationale,
                    'evidence_mode' => $criterion['evidence_mode'] ?? null,
                    'source_sections' => $sourceSections,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(EntrepreneurProfile $profile, PlanAssessment $assessment): array
    {
        $priorities = $this->priorities($assessment);
        $suggestedFeedback = $this->draft($assessment);
        $suggestedReply = $this->proposedReply($profile, $assessment);

        return [
            'generated_at' => now()->toIso8601String(),
            'source' => [
                'plan_assessment_id' => $assessment->getKey(),
                'business_plan_id' => $assessment->business_plan_id,
                'entrepreneur_profile_id' => $profile->getKey(),
            ],
            'weighted_score' => round(AssessmentScoring::weightedScore($assessment), 2),
            'threshold' => AdvisoryReadiness::THRESHOLD,
            'priorities' => collect($priorities)
                ->values()
                ->map(fn (array $priority, int $index): array => [
                    'rank' => $index + 1,
                    'title' => $priority['title'],
                    'score' => $priority['score'],
                    'where_in_plan' => $priority['where_in_plan'],
                    'what_is_missing_sha256' => $this->textHash((string) $priority['what_is_missing']),
                    'what_to_add_or_change_sha256' => $this->textHash((string) $priority['what_to_add_or_change']),
                ])
                ->all(),
            'suggested_feedback' => [
                'sha256' => $this->textHash($suggestedFeedback),
                'length' => Str::length($suggestedFeedback),
            ],
            'suggested_reply' => [
                'sha256' => $this->textHash($suggestedReply),
                'length' => Str::length($suggestedReply),
            ],
            'document_support' => [
                'attached_document_count' => (int) data_get($assessment->document_support, 'attached_document_count', 0),
            ],
        ];
    }

    public function draft(PlanAssessment $assessment): string
    {
        $score = AssessmentScoring::weightedScore($assessment);
        $threshold = AdvisoryReadiness::THRESHOLD;
        $priorities = $this->priorities($assessment);

        $readiness = $score >= $threshold ? 'meets' : 'is below';
        $intro = sprintf(
            'Assessment completed: the current banded readiness indicator is approximately %d/100, which %s the %.0f/100 advisory-readiness threshold.',
            round($score),
            $readiness,
            $threshold,
        );

        if ($priorities === []) {
            return $intro;
        }

        return implode("\n\n", [
            $intro,
            'These are the three lowest-scoring criteria in this assessment:',
            $this->formatPriorities($priorities, includeScores: true, includeEvidence: true),
            'Use the assessment finding and the reviewed plan sections to decide the next update.',
        ]);
    }

    public function proposedReply(EntrepreneurProfile $profile, PlanAssessment $assessment): string
    {
        $planBudgetFindings = $this->planBudgetFindings($assessment);
        if ($planBudgetFindings !== []) {
            return $this->changeRequestMessages->build($profile, [
                'Before we can finish this review, please update the budget items below. You do not need to start again: we need the written plan and the budget to tell the same financial story.',
                "Please work through these steps:\n\n".$this->formatPlanBudgetFindings($planBudgetFindings),
                'Once the numbers and explanations match, send the plan back for review. If any of the numbers are unclear, reply before changing them and we can talk them through together.',
            ]);
        }

        $priorities = $this->priorities($assessment);

        if ($priorities === []) {
            return $this->changeRequestMessages->build($profile, [
                'You have made good progress. The next step is to add a little more practical detail so we can review the plan with confidence.',
                'Reply if you would like to talk through the next update before you start.',
            ]);
        }

        return $this->changeRequestMessages->build($profile, [
            'You have made real progress here. You do not need to start again. The three areas below are simply the best places to tighten the next version.',
            "I have set out what to focus on next:\n\n".$this->formatPriorities($priorities, includeScores: false, includeEvidence: false),
            'When you are ready, send the plan back and we will review the updated sections. Reply first if you would like to talk through any of these points.',
        ]);
    }

    public function isLegacyFeedback(string $feedback): bool
    {
        $feedback = trim($feedback);

        return (str_starts_with($feedback, 'I have completed the assessment. The current score is')
            && str_contains($feedback, 'The most useful priorities for the next revision are:'))
            || (str_starts_with($feedback, 'Assessment completed:')
                && (str_contains($feedback, 'Ask the founder to update these three areas next:')
                    || str_contains($feedback, 'What is missing:')))
            || $this->containsRetiredFounderLanguage($feedback);
    }

    public function isLegacyReply(string $reply): bool
    {
        $reply = trim($reply);

        return str_contains($reply, 'What is missing:')
            || $this->containsRetiredFounderLanguage($reply);
    }

    /**
     * @param  array<int, array<string, mixed>>  $priorities
     */
    private function formatPriorities(array $priorities, bool $includeScores, bool $includeEvidence): string
    {
        return collect($priorities)
            ->values()
            ->map(function (array $priority, int $index) use ($includeScores, $includeEvidence): string {
                $heading = sprintf('%d. %s', $index + 1, $priority['title']);
                if ($includeScores) {
                    $heading .= sprintf(' (%.0f/100)', $priority['score']);
                }

                $lines = [
                    $heading,
                    'Assessment finding: '.$priority['what_is_missing'],
                    'Suggested next step: '.$priority['what_to_add_or_change'],
                    'Plan sections reviewed: '.$priority['where_in_plan'],
                ];

                if ($includeScores) {
                    $movement = $this->movementLine($priority);
                    if ($movement !== null) {
                        $lines[] = $movement;
                    }
                }

                if ($includeEvidence) {
                    $evidence = $this->sourceEvidenceLine($priority);
                    if ($evidence !== null) {
                        $lines[] = $evidence;
                    }
                }

                return implode("\n", $lines);
            })
            ->implode("\n\n");
    }

    /**
     * @return list<array{category:string,severity:string,message:string,next_action:string}>
     */
    private function planBudgetFindings(PlanAssessment $assessment): array
    {
        $coherence = data_get($assessment->scoring_scope, 'plan_budget_coherence');
        if (! is_array($coherence) || (bool) ($coherence['approval_available'] ?? true)) {
            return [];
        }

        $findings = $coherence['findings'] ?? [];
        if (! is_array($findings)) {
            return [];
        }

        $planBudgetFindings = [];
        foreach ($findings as $finding) {
            if (! is_array($finding)
                || ! in_array($finding['category'] ?? null, ['budget_support', 'plan_correlation'], true)
                || trim((string) ($finding['message'] ?? '')) === '') {
                continue;
            }

            $planBudgetFindings[] = [
                'category' => (string) $finding['category'],
                'severity' => (string) ($finding['severity'] ?? 'review'),
                'message' => trim((string) $finding['message']),
                'next_action' => trim((string) ($finding['next_action'] ?? 'Update the matching budget input and plan assumption.')),
            ];
        }

        return collect($planBudgetFindings)
            ->unique(fn (array $finding): string => Str::lower(
                $finding['category'].'|'.$finding['message'].'|'.$finding['next_action'],
            ))
            ->values()
            ->all();
    }

    /**
     * @param  list<array{category:string,severity:string,message:string,next_action:string}>  $findings
     */
    private function formatPlanBudgetFindings(array $findings): string
    {
        return collect($findings)
            ->groupBy(fn (array $finding): string => $this->planBudgetTopic($finding))
            ->map(function ($group, string $topic): string {
                $details = $group
                    ->map(fn (array $finding): string => '- '.$this->plainLanguageFinding($finding))
                    ->unique()
                    ->values()
                    ->implode("\n");

                return implode("\n", [
                    $this->plainLanguageTopicTitle($topic),
                    'What we found:',
                    $details,
                    'Why this matters: '.$this->plainLanguageWhyItMatters($topic),
                    'What to do: '.$this->plainLanguageNextStep($topic),
                    'Where to update: '.$this->plainLanguageLocation($topic),
                ]);
            })
            ->values()
            ->map(fn (string $item, int $index): string => ($index + 1).'. '.$item)
            ->implode("\n\n");
    }

    /** @param array{category:string,severity:string,message:string,next_action:string} $finding */
    private function planBudgetTopic(array $finding): string
    {
        $message = strtolower($finding['message']);

        if (str_contains($message, 'funding') || str_contains($message, 'cash') || str_contains($message, 'runway') || str_contains($message, 'break-even')) {
            return 'cash_and_funding';
        }

        if (str_contains($message, 'fixed cost')
            || str_contains($message, 'owner compensation')
            || str_contains($message, 'cadence')
            || str_contains($message, 'insurance')
            || str_contains($message, 'trademark')) {
            return 'regular_costs';
        }

        if (str_contains($message, 'revenue') || str_contains($message, 'capacity') || str_contains($message, 'contractor')) {
            return 'sales_forecast';
        }

        return $finding['category'] === 'plan_correlation'
            ? 'plan_and_budget'
            : 'budget_details';
    }

    private function plainLanguageTopicTitle(string $topic): string
    {
        return match ($topic) {
            'cash_and_funding' => 'Check the cash and funding plan',
            'regular_costs' => 'Check the regular business costs',
            'sales_forecast' => 'Check the sales forecast',
            'plan_and_budget' => 'Make the written plan and budget agree',
            default => 'Complete the missing budget details',
        };
    }

    private function plainLanguageWhyItMatters(string $topic): string
    {
        return match ($topic) {
            'cash_and_funding' => 'Cash runway means how long the business can pay its bills with the money available. We need to see how that period will be funded.',
            'regular_costs' => 'Every regular cost needs to be included so the total cost of running the business is realistic.',
            'sales_forecast' => 'The sales forecast needs to match the number of customers you can realistically serve and the cost of delivering the work.',
            'plan_and_budget' => 'We need the written plan and budget to use the same numbers before we can rely on them.',
            default => 'We need enough clear information to check that the budget supports the plan.',
        };
    }

    private function plainLanguageNextStep(string $topic): string
    {
        return match ($topic) {
            'cash_and_funding' => 'Check the cash you start with, the dates money comes in, and the dates bills are paid. If more money is needed, say where it will come from and when it will arrive.',
            'regular_costs' => 'Add every regular cost, the amount, and whether it is paid weekly, monthly, or yearly. Make sure the total matches the number used in the financial plan.',
            'sales_forecast' => 'Check the expected number of sales, the price of each sale, when customers will pay, and the people or contractor time needed to deliver the work.',
            'plan_and_budget' => 'Update the number in the budget and the matching statement in the financial plan so both say the same thing.',
            default => 'Add the missing information in the budget, then check it against the matching financial plan section.',
        };
    }

    private function plainLanguageLocation(string $topic): string
    {
        return match ($topic) {
            'cash_and_funding' => 'Budget > Funding and runway',
            'regular_costs' => 'Budget > Monthly fixed costs',
            'sales_forecast' => 'Budget > Revenue forecast',
            'plan_and_budget' => 'Financial plan > Financial assumptions, Revenue model, and Funding and support',
            default => 'Budget > Financial assumptions',
        };
    }

    /** @param array{category:string,severity:string,message:string,next_action:string} $finding */
    private function plainLanguageFinding(array $finding): string
    {
        $message = $finding['message'];
        $normalised = strtolower($message);

        if (str_contains($normalised, '0 months of runway')) {
            return 'The budget shows no months of cash available to pay business bills.';
        }

        if (str_contains($normalised, 'funding gap of ')) {
            return str_replace('The budget has a funding gap of ', 'The budget is short by ', $message);
        }

        if (str_contains($normalised, 'additional funding to cover the modelled cash trough')) {
            $plainMessage = preg_replace(
                '/^The forecast requires (.+?) of additional funding to cover the modelled cash trough and planned operating buffer\.$/',
                'The budget shows it may need $1 more cash to cover costs and keep a small safety buffer.',
                $message,
            );

            return is_string($plainMessage) ? $plainMessage : $message;
        }

        if (str_contains($normalised, 'fixed-cost trace mismatch')) {
            return 'The itemised regular costs do not add up to the total used in the budget.';
        }

        if (str_contains($normalised, 'does not name a matching cost row')) {
            return str_replace('but the budget does not name a matching cost row.', 'but no matching cost appears in the budget.', $message);
        }

        if (str_contains($normalised, 'cost cadence')) {
            return str_replace('cost cadence', 'whether costs are paid weekly, monthly, or yearly', $message);
        }

        return $message;
    }

    private function movementLine(array $priority): ?string
    {
        if (! is_numeric($priority['previous_score'] ?? null) || ! is_numeric($priority['previous_round'] ?? null)) {
            return null;
        }

        $delta = (float) ($priority['score_delta'] ?? 0);
        $deltaText = $delta > 0
            ? '+'.number_format($delta, 0)
            : number_format($delta, 0);

        return sprintf(
            'Round movement: previous round %d was %d/100; current round is %d/100 (%s).',
            (int) $priority['previous_round'],
            round((float) $priority['previous_score']),
            round((float) $priority['score']),
            $deltaText,
        );
    }

    private function sourceEvidenceLine(array $priority): ?string
    {
        $sections = collect((array) ($priority['source_sections'] ?? []))
            ->map(function (mixed $section): ?string {
                if (! is_array($section)) {
                    return null;
                }

                $title = trim((string) ($section['title'] ?? 'Plan section'));
                $excerpt = trim((string) ($section['body_excerpt'] ?? ''));

                if ($excerpt === '') {
                    return null;
                }

                $updatedAt = trim((string) ($section['updated_at'] ?? ''));
                $label = $updatedAt !== '' ? "{$title} updated {$updatedAt}" : $title;

                $excerpt = $this->completeSentenceExcerpt($excerpt, 220);

                return $excerpt === null ? null : $label.': '.$excerpt;
            })
            ->filter()
            ->take(2)
            ->values()
            ->all();

        if ($sections === []) {
            return null;
        }

        $prefix = match ($priority['evidence_mode'] ?? null) {
            'criterion_scoped_submitted_snapshot' => 'Scored from mapped criterion evidence: ',
            'complete_submitted_plan_snapshot' => 'Scored from the complete submitted-plan snapshot: ',
            default => 'Scored from current source excerpts: ',
        };

        return $prefix.implode(' | ', $sections);
    }

    private function assessmentFinding(string $rationale): string
    {
        $finding = $this->completeSentenceExcerpt($rationale, 420);

        if ($finding !== null) {
            return $finding;
        }

        return 'The assessment note for this criterion was incomplete. Use the suggested next step below to add the practical detail needed for the next review.';
    }

    private function completeSentenceExcerpt(string $text, int $limit): ?string
    {
        $text = trim(Str::squish($text));

        if ($text === '' || $this->containsTruncationMarker($text)) {
            return null;
        }

        if (Str::length($text) <= $limit) {
            return $text;
        }

        $excerpt = '';
        foreach (preg_split('/(?<=[.!?])\s+/u', $text) ?: [] as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            $candidate = trim($excerpt.' '.$sentence);
            if (Str::length($candidate) > $limit) {
                break;
            }

            $excerpt = $candidate;
        }

        return $excerpt === '' ? null : $excerpt;
    }

    private function containsTruncationMarker(string $text): bool
    {
        return preg_match('/(?:\.{3}|\x{2026}|\[\s*(?:\.{3}|\x{2026})\s*\])/u', $text) === 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sourceSections
     */
    private function reviewedSectionsLabel(array $sourceSections, string $fallback): string
    {
        $titles = collect($sourceSections)
            ->filter(fn (mixed $section): bool => is_array($section))
            ->map(fn (array $section): string => trim((string) ($section['title'] ?? '')))
            ->filter()
            ->unique()
            ->take(3)
            ->values()
            ->all();

        return $titles === [] ? $fallback : implode(', ', $titles);
    }

    private function previousAssessment(PlanAssessment $assessment): ?PlanAssessment
    {
        return PlanAssessment::query()
            ->with('ratingFramework.criteria')
            ->where('business_plan_id', $assessment->business_plan_id)
            ->where('round', '<', (int) $assessment->round)
            ->orderByDesc('round')
            ->first();
    }

    private function hasNonComparableScoringScope(PlanAssessment $assessment): bool
    {
        $scope = is_array($assessment->scoring_scope) ? $assessment->scoring_scope : [];
        if (($scope['version'] ?? null) !== 'criterion_evidence_v1') {
            return false;
        }

        $rescored = collect((array) ($scope['rescored_criterion_numbers'] ?? []))
            ->filter(fn (mixed $number): bool => is_numeric($number))
            ->map(fn (mixed $number): int => (int) $number)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $reused = collect((array) ($scope['reused_criterion_numbers'] ?? []))
            ->filter(fn (mixed $number): bool => is_numeric($number))
            ->map(fn (mixed $number): int => (int) $number)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $criteria = collect(AssessmentScoring::criteriaPayload($assessment))
            ->pluck('criterion_number')
            ->filter(fn (mixed $number): bool => is_numeric($number))
            ->map(fn (mixed $number): int => (int) $number)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return ($criteria !== [] && $rescored === $criteria && $reused === [])
            || collect((array) ($scope['scope_correction_criterion_numbers'] ?? []))
                ->contains(fn (mixed $number): bool => is_numeric($number));
    }

    /**
     * @return array{what_is_missing:string,what_to_add_or_change:string,where_in_plan:string}
     */
    private function defaultGuidance(string $name): array
    {
        return [
            'what_is_missing' => 'This part of the plan needs clearer evidence and practical detail before the next assessment.',
            'what_to_add_or_change' => 'Add the specific evidence, assumptions, decisions, and next actions that support this part of the plan.',
            'where_in_plan' => 'Update the plan section that covers '.$name,
        ];
    }

    private function textHash(string $text): string
    {
        return hash('sha256', Str::squish($text));
    }

    private function containsRetiredFounderLanguage(string $text): bool
    {
        $text = Str::lower($text);

        if ($this->containsTruncationMarker($text)) {
            return true;
        }

        foreach ([
            'directionally',
            'materially underdeveloped',
            'launch decision-making',
            'advisory-readiness',
            'targeted updates',
        ] as $retiredPhrase) {
            if (str_contains($text, $retiredPhrase)) {
                return true;
            }
        }

        return false;
    }
}
