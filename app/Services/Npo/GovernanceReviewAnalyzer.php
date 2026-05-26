<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Enums\FindingSeverity;
use App\Enums\NpoLegalStructure;
use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\GovernanceReviewFinding;
use App\Models\NpoComplianceAlert;
use App\Models\NpoEngagement;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Integrity\SourceAttribution;
use App\Services\Ai\Prompts\PromptRegistry;
use App\Services\Analysis\Modules\ComplianceChecker;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class GovernanceReviewAnalyzer
{
    public const PROMPT_ID = 'npo.governance_review.analysis';

    public function __construct(
        private readonly AiClient $ai,
        private readonly PromptRegistry $prompts,
        private readonly SourceAttribution $sourceAttribution,
        private readonly NpoComplianceLookup $compliance,
        private readonly AuditWriter $auditWriter,
    ) {}

    /**
     * @return Collection<int, GovernanceReviewFinding>
     */
    public function run(NpoEngagement $engagement, ?User $actor = null): Collection
    {
        $engagement->loadMissing('client');
        $client = $engagement->client;
        if (! $client instanceof Client) {
            throw new InvalidArgumentException('NPO engagement must belong to a client.');
        }

        if ($this->compliance->blocksAnalysis($engagement)) {
            throw new InvalidArgumentException('NPO compliance alerts must be acknowledged before governance analysis can run.');
        }

        $questionnaireResponse = $this->latestGovernanceResponse($engagement);
        $evidence = $questionnaireResponse instanceof QuestionnaireResponse
            ? $this->evidenceFromResponse($questionnaireResponse)
            : [];
        $criteria = $this->criteriaFor($this->legalStructure($engagement));
        $complianceAlerts = $this->complianceAlerts($engagement);
        $prompt = $this->prompts->envelope(
            id: self::PROMPT_ID,
            input: [
                'client' => [
                    'id' => (string) $client->getKey(),
                    'legal_name' => $client->legal_name,
                    'nzbn' => $client->nzbn,
                ],
                'npo_engagement' => [
                    'id' => (string) $engagement->getKey(),
                    'sub_type' => (string) $engagement->sub_type?->value,
                    'legal_structure' => $this->legalStructure($engagement)->value,
                    'legal_structure_label' => $this->legalStructure($engagement)->label(),
                    'isa_2022_reregistered' => $engagement->isa_2022_reregistered,
                ],
                'criteria' => $criteria,
                'questionnaire_response' => [
                    'id' => $questionnaireResponse?->getKey(),
                    'submitted_at' => $questionnaireResponse?->submitted_at?->toIso8601String(),
                    'evidence' => $evidence,
                ],
                'compliance_alerts' => $complianceAlerts,
                'advisor_review_required' => true,
            ],
            dataQualitySummary: [
                'governance_response_present' => $questionnaireResponse instanceof QuestionnaireResponse,
                'evidence_items' => count($evidence),
                'document_evidence_items' => $this->documentEvidenceCount($evidence),
                'unresolved_compliance_alerts' => count($complianceAlerts),
            ],
            sourceReferences: $this->sourceReferences($engagement, $questionnaireResponse, $evidence, $criteria, $complianceAlerts),
        );

        $response = $this->ai->analyse($prompt);
        $this->sourceAttribution->validate($response);

        $findings = $this->findingsFor($engagement, $questionnaireResponse, $evidence, $criteria, $response);
        $persisted = DB::transaction(function () use ($client, $engagement, $findings, $response, $actor): array {
            $persisted = [];
            $aiPayload = $this->aiPayload($response);

            foreach ($findings as $finding) {
                $this->assertAttributions($finding['attributions'], $finding['title']);

                $persisted[] = GovernanceReviewFinding::query()->updateOrCreate(
                    [
                        'client_id' => $client->getKey(),
                        'npo_engagement_id' => $engagement->getKey(),
                        'finding_key' => $finding['finding_key'],
                    ],
                    [
                        'category' => $finding['category'],
                        'severity' => $finding['severity'],
                        'title' => $finding['title'],
                        'body' => $finding['body'],
                        'criteria' => $finding['criteria'],
                        'evidence' => $finding['evidence'],
                        'attributions' => $finding['attributions'],
                        'uncertainty' => $finding['uncertainty'],
                        'ai_payload' => $aiPayload,
                        'status' => GovernanceReviewFinding::STATUS_PENDING_ADVISOR_REVIEW,
                        'advisor_notes' => null,
                        'reviewed_at' => null,
                        'reviewed_by_user_id' => null,
                    ],
                )->refresh();
            }

            $this->auditWriter->record('npo.governance_review_analysis_generated', subject: $engagement, actor: $actor, after: [
                'client_id' => $client->getKey(),
                'findings_created' => count($persisted),
                'prompt_hash' => $response->promptHash,
                'prompt_version' => $response->promptVersion,
                'ai_model' => $response->model,
            ]);

            return $persisted;
        });

        return collect($persisted);
    }

    public function review(GovernanceReviewFinding $finding, User $advisor, ?string $notes = null): GovernanceReviewFinding
    {
        $finding->forceFill([
            'status' => GovernanceReviewFinding::STATUS_REVIEWED,
            'advisor_notes' => $notes,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $advisor->getKey(),
        ])->save();

        $this->auditWriter->record('npo.governance_review_finding_reviewed', subject: $finding, actor: $advisor, after: [
            'client_id' => $finding->client_id,
            'npo_engagement_id' => $finding->npo_engagement_id,
            'finding_key' => $finding->finding_key,
        ]);

        return $finding->refresh();
    }

    /**
     * @return Collection<int, GovernanceReviewFinding>
     */
    public function clientFacingFindings(NpoEngagement $engagement): Collection
    {
        return GovernanceReviewFinding::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('status', GovernanceReviewFinding::STATUS_REVIEWED)
            ->orderBy('category')
            ->get();
    }

    private function latestGovernanceResponse(NpoEngagement $engagement): ?QuestionnaireResponse
    {
        return QuestionnaireResponse::query()
            ->where('client_id', $engagement->client_id)
            ->where('npo_engagement_id', $engagement->getKey())
            ->whereHas('questionnaire', fn ($query) => $query->forSet(QuestionnaireSet::GOVERNANCE_REVIEW))
            ->with(['answers.question.section'])
            ->latest('submitted_at')
            ->latest()
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function evidenceFromResponse(QuestionnaireResponse $response): array
    {
        return $response->answers
            ->map(fn (QuestionnaireAnswer $answer): array => [
                'answer_id' => (string) $answer->getKey(),
                'question_id' => (string) $answer->question_id,
                'section' => (string) ($answer->question?->section?->title ?? 'Governance review'),
                'prompt' => (string) ($answer->question?->prompt ?? 'Governance review answer'),
                'value' => $this->displayValue($answer),
                'attached_document_ids' => $this->documentIds($answer),
                'source_reference' => 'questionnaire_answer:'.$answer->getKey(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function criteriaFor(NpoLegalStructure $structure): array
    {
        $criteria = [
            [
                'key' => 'board_composition',
                'label' => 'Board composition, role coverage, skills gap, succession, and meeting discipline',
                'source_reference' => 'questionnaire_section:board-composition-and-skills',
            ],
            [
                'key' => 'conflicts_of_interest',
                'label' => 'Conflicts of interest register, declarations, minute capture, and management framework',
                'source_reference' => 'questionnaire_section:governance-evidence-pack',
            ],
            [
                'key' => 'constitution_currency',
                'label' => 'Constitution, rules, or trust deed currency against current law and practice',
                'source_reference' => 'questionnaire_section:constitution-and-compliance',
            ],
            [
                'key' => 'financial_oversight',
                'label' => 'Board-level financial statements, reserves, reporting cadence, delegated authority, and two-person controls',
                'source_reference' => 'questionnaire_section:financial-oversight',
            ],
        ];

        if ($this->requiresCharityCriteria($structure)) {
            $criteria[] = [
                'key' => 'charities_act_s42g',
                'label' => 'Charities Act 2005 s.42G officer qualification status and officer governance obligations',
                'source_reference' => 'statute:nz:charities-act-2005-s42g',
            ];
            $criteria[] = [
                'key' => 'charities_amendment_2023',
                'label' => 'Charities Amendment Act 2023 governance, reporting, and registration currency',
                'source_reference' => 'statute:nz:charities-amendment-act-2023',
            ];
        }

        if ($this->requiresIncorporatedSocietyCriteria($structure)) {
            $criteria[] = [
                'key' => 'isa_2022',
                'label' => 'Incorporated Societies Act 2022 re-registration, officer duties, disputes process, and constitution requirements',
                'source_reference' => 'statute:nz:incorporated-societies-act-2022',
            ];
        }

        if ($structure === NpoLegalStructure::CharitableTrustBoard) {
            $criteria[] = [
                'key' => 'trust_deed_currency',
                'label' => 'Trust deed currency, trustee powers, and charitable trust governance controls',
                'source_reference' => 'statute:nz:trusts-act-2019',
            ];
        }

        return $criteria;
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<int, array<string, mixed>>
     */
    private function findingsFor(
        NpoEngagement $engagement,
        ?QuestionnaireResponse $questionnaireResponse,
        array $evidence,
        array $criteria,
        AiResponse $response,
    ): array {
        $structure = $this->legalStructure($engagement);
        $findings = [
            $this->legalStructureFinding($engagement, $criteria, $response),
            $this->boardCompositionFinding($engagement, $questionnaireResponse, $evidence, $criteria),
            $this->conflictsFinding($engagement, $questionnaireResponse, $evidence, $criteria),
            $this->constitutionFinding($engagement, $questionnaireResponse, $evidence, $criteria),
            $this->financialOversightFinding($engagement, $questionnaireResponse, $evidence, $criteria),
            $this->evidenceDepthFinding($engagement, $questionnaireResponse, $evidence),
        ];

        if ($this->paidStaffPresent($evidence)) {
            $findings[] = $this->paidStaffFinding($engagement, $evidence);
        }

        if (! $this->requiresCharityCriteria($structure) && ! $this->requiresIncorporatedSocietyCriteria($structure)) {
            $findings[] = $this->unregisteredStructureFinding($engagement, $criteria);
        }

        return $findings;
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function legalStructureFinding(NpoEngagement $engagement, array $criteria, AiResponse $response): array
    {
        $structure = $this->legalStructure($engagement);
        $isaMissing = $this->requiresIncorporatedSocietyCriteria($structure) && $engagement->isa_2022_reregistered === false;
        $severity = $isaMissing ? FindingSeverity::Critical : FindingSeverity::Info;
        $criteriaLabels = implode('; ', array_map(static fn (array $item): string => (string) $item['label'], $criteria));
        $body = $isaMissing
            ? 'The engagement is an incorporated society and ISA 2022 re-registration is not recorded. Advisor-reviewed governance analysis should treat constitution currency and officer duties as critical until the registry position is resolved or consciously accepted.'
            : 'The governance review criteria have been selected for '.$structure->label().'. The AI layer must use these criteria as review lenses, disclose uncertainty, and keep outputs pending advisor review.';

        return [
            'finding_key' => 'legal_structure_compliance',
            'category' => 'legal_structure',
            'severity' => $severity,
            'title' => 'Legal-structure governance criteria selected',
            'body' => $body,
            'criteria' => $criteria,
            'evidence' => [
                'legal_structure' => $structure->value,
                'legal_structure_label' => $structure->label(),
                'isa_2022_reregistered' => $engagement->isa_2022_reregistered,
                'criteria_labels' => $criteriaLabels,
                'ai_uncertainty' => $response->uncertainty->value,
            ],
            'attributions' => [
                [
                    'claim' => 'The NPO engagement legal structure is '.$structure->label().'.',
                    'source_reference' => 'npo_engagement:'.$engagement->getKey(),
                ],
                ...$this->criteriaAttributions($criteria),
            ],
            'uncertainty' => $isaMissing ? Uncertainty::Medium : Uncertainty::Low,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function boardCompositionFinding(NpoEngagement $engagement, ?QuestionnaireResponse $response, array $evidence, array $criteria): array
    {
        $items = $this->matchingEvidence($evidence, ['board', 'committee', 'skills', 'roles', 'succession', 'meet', 'voice', 'tangata']);
        $text = $this->evidenceText($items);
        $severity = $items === [] || $this->containsAny($text, ['very low', 'low', 'not currently meeting', 'ad hoc'])
            ? FindingSeverity::High
            : ($this->containsAny($text, ['moderate', 'quarterly']) ? FindingSeverity::Medium : FindingSeverity::Low);

        $body = $items === []
            ? 'Board composition, role coverage, skills gap, succession, and meeting-rhythm evidence is thin. Advisor review should request board register, role map, skills matrix, and meeting cadence evidence before forming client-facing conclusions.'
            : 'Board composition evidence covers '.$this->summary($items).'. Advisor review should test whether role coverage, skills depth, succession, meeting cadence, and community or tangata whenua voice are strong enough for the organisation context.';

        return $this->finding(
            engagement: $engagement,
            response: $response,
            key: 'board_composition',
            category: 'governance_capability',
            severity: $severity,
            title: 'Board composition and skills gap',
            body: $body,
            criteria: $this->criteriaSubset($criteria, ['board_composition']),
            evidence: $items,
            fallbackClaim: 'Board composition evidence is required for governance analysis.',
            uncertainty: $items === [] ? Uncertainty::High : Uncertainty::Medium,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function conflictsFinding(NpoEngagement $engagement, ?QuestionnaireResponse $response, array $evidence, array $criteria): array
    {
        $items = $this->matchingEvidence($evidence, ['conflict', 'interest']);
        $text = $this->evidenceText($items);
        $hasRegister = $this->hasDocumentEvidence($this->matchingEvidence($items, ['register']));
        $severity = ! $hasRegister || $this->containsAny($text, ['not currently recorded', 'informally'])
            ? FindingSeverity::High
            : ($this->containsAny($text, ['when a conflict arises']) ? FindingSeverity::Medium : FindingSeverity::Low);

        $body = $hasRegister
            ? 'Conflict-of-interest evidence includes a register or supporting document. Advisor review should confirm declarations are refreshed, minuted, and managed consistently.'
            : 'Conflict-of-interest evidence does not include a register attachment. Advisor review should treat the COI framework as a governance gap until register and minute evidence is supplied.';

        return $this->finding(
            engagement: $engagement,
            response: $response,
            key: 'conflicts_of_interest',
            category: 'risk_controls',
            severity: $severity,
            title: 'Conflicts of interest framework',
            body: $body,
            criteria: $this->criteriaSubset($criteria, ['conflicts_of_interest']),
            evidence: $items,
            fallbackClaim: 'Conflict-of-interest evidence is required for governance analysis.',
            uncertainty: $hasRegister ? Uncertainty::Medium : Uncertainty::High,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function constitutionFinding(NpoEngagement $engagement, ?QuestionnaireResponse $response, array $evidence, array $criteria): array
    {
        $items = $this->matchingEvidence($evidence, ['constitution', 'rules', 'trust deed', 'incorporated societies act', 'charities act', 's.42g', 'statutory']);
        $text = $this->evidenceText($items);
        $hasConstitution = $this->hasDocumentEvidence($this->matchingEvidence($items, ['constitution', 'rules', 'trust deed']));
        $structure = $this->legalStructure($engagement);
        $isaMissing = $this->requiresIncorporatedSocietyCriteria($structure) && $engagement->isa_2022_reregistered === false;
        $hasCurrencyIssue = $isaMissing || ! $hasConstitution || $this->containsAny($text, ['yes', 'in progress', 'not started', 'unsure', 'needs updates']);
        $severity = $isaMissing ? FindingSeverity::Critical : ($hasCurrencyIssue ? FindingSeverity::High : FindingSeverity::Low);

        $body = $hasCurrencyIssue
            ? 'Constitution or rules currency requires advisor attention before conclusions are shown to the client. The review should test the governing document against applicable ISA 2022, Charities Amendment Act 2023, s.42G, trust-law, or practice-update criteria for the recorded structure.'
            : 'Constitution or rules evidence is present, with no obvious currency gap in the submitted questionnaire evidence. Advisor review should still verify statutory currency before publication.';

        return $this->finding(
            engagement: $engagement,
            response: $response,
            key: 'constitution_currency',
            category: 'legal_compliance',
            severity: $severity,
            title: 'Constitution and statutory currency',
            body: $body,
            criteria: $this->criteriaSubset($criteria, ['constitution_currency', 'charities_act_s42g', 'charities_amendment_2023', 'isa_2022', 'trust_deed_currency']),
            evidence: [
                ...$items,
                [
                    'source_reference' => 'npo_engagement:'.$engagement->getKey(),
                    'prompt' => 'ISA 2022 re-registration status',
                    'value' => $engagement->isa_2022_reregistered,
                    'attached_document_ids' => [],
                ],
            ],
            fallbackClaim: 'Constitution and statutory currency evidence is required for governance analysis.',
            uncertainty: $hasConstitution && ! $hasCurrencyIssue ? Uncertainty::Medium : Uncertainty::High,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function financialOversightFinding(NpoEngagement $engagement, ?QuestionnaireResponse $response, array $evidence, array $criteria): array
    {
        $items = $this->matchingEvidence($evidence, ['financial', 'reserves', 'reporting', 'approval', 'delegated', 'two-person', 'funder', 'funding']);
        $text = $this->evidenceText($items);
        $hasStatements = $this->hasDocumentEvidence($this->matchingEvidence($items, ['financial statements']));
        $severity = ! $hasStatements || $this->containsAny($text, ['not currently provided', 'only on request', 'very low', 'low'])
            ? FindingSeverity::High
            : ($this->containsAny($text, ['quarterly', 'moderate']) ? FindingSeverity::Medium : FindingSeverity::Low);

        $body = $hasStatements
            ? 'Financial oversight evidence includes financial statements or reporting support. Advisor review should confirm reporting cadence, reserves, delegated authorities, two-person controls, and funder-condition monitoring.'
            : 'Financial oversight evidence is missing the latest financial statements attachment. Advisor review should avoid client-facing conclusions until statements, controls, and reporting cadence are confirmed.';

        return $this->finding(
            engagement: $engagement,
            response: $response,
            key: 'financial_oversight',
            category: 'financial_governance',
            severity: $severity,
            title: 'Financial oversight controls',
            body: $body,
            criteria: $this->criteriaSubset($criteria, ['financial_oversight']),
            evidence: $items,
            fallbackClaim: 'Financial oversight evidence is required for governance analysis.',
            uncertainty: $hasStatements ? Uncertainty::Medium : Uncertainty::High,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @return array<string, mixed>
     */
    private function evidenceDepthFinding(NpoEngagement $engagement, ?QuestionnaireResponse $response, array $evidence): array
    {
        $documentCount = $this->documentEvidenceCount($evidence);
        $requiredDocumentCount = 4;
        $severity = ! $response instanceof QuestionnaireResponse || count($evidence) < 10 || $documentCount < $requiredDocumentCount
            ? FindingSeverity::High
            : FindingSeverity::Low;
        $uncertainty = $severity === FindingSeverity::High ? Uncertainty::High : Uncertainty::Medium;
        $body = ! $response instanceof QuestionnaireResponse
            ? 'No submitted governance review questionnaire response is available for this NPO engagement. All governance findings must be treated as evidence-thin until the response and supporting pack are supplied.'
            : sprintf(
                'Governance evidence currently contains %d answer(s) and %d supporting document reference(s). Advisor review should resolve evidence gaps before client-facing release, especially where required constitution, financial-statement, board-register, or COI-register attachments are absent.',
                count($evidence),
                $documentCount,
            );

        return [
            'finding_key' => 'evidence_depth',
            'category' => 'data_quality',
            'severity' => $severity,
            'title' => 'Evidence depth and uncertainty',
            'body' => $body,
            'criteria' => [
                [
                    'key' => 'source_attribution',
                    'label' => 'Every governance finding must have explicit evidence or disclose evidence absence',
                    'source_reference' => 'ai_integrity:source-attribution',
                ],
            ],
            'evidence' => [
                'questionnaire_response_id' => $response?->getKey(),
                'evidence_items' => count($evidence),
                'document_evidence_items' => $documentCount,
                'required_document_evidence_items' => $requiredDocumentCount,
            ],
            'attributions' => $this->attributionsForEvidence($engagement, $response, [], 'Governance evidence depth is based on the questionnaire response and required evidence pack.'),
            'uncertainty' => $uncertainty,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @return array<string, mixed>
     */
    private function paidStaffFinding(NpoEngagement $engagement, array $evidence): array
    {
        $items = $this->matchingEvidence($evidence, ['paid staff', 'contractors', 'payroll', 'hr risks']);
        $text = $this->evidenceText($items);
        $severity = $this->containsAny($text, ['risk', 'breach', 'missing', 'unclear']) ? FindingSeverity::High : FindingSeverity::Medium;

        return [
            'finding_key' => 'paid_staff_holidays_act',
            'category' => 'employment_compliance',
            'severity' => $severity,
            'title' => 'Paid-staff Holidays Act check',
            'body' => 'Paid staff or contractors are present in the governance evidence. The advisor review should reuse the compliance checker Holidays Act/payroll validation path before client-facing governance recommendations are released.',
            'criteria' => [
                [
                    'key' => 'holidays_act',
                    'label' => ComplianceChecker::HOLIDAYS_ACT.' payroll validation and leave-liability exposure',
                    'source_reference' => 'statute:nz:holidays-act-2003',
                ],
                [
                    'key' => 'compliance_checker',
                    'label' => 'Reuse '.ComplianceChecker::PROMPT_ID.' employment/payroll compliance evidence where available',
                    'source_reference' => 'analysis_module:'.ComplianceChecker::PROMPT_ID,
                ],
            ],
            'evidence' => $items,
            'attributions' => [
                ...$this->attributionsForEvidence($engagement, null, $items, 'Paid-staff governance evidence was supplied.'),
                [
                    'claim' => ComplianceChecker::HOLIDAYS_ACT.' is the statutory payroll validation lens for paid-staff governance review.',
                    'source_reference' => 'statute:nz:holidays-act-2003',
                ],
            ],
            'uncertainty' => Uncertainty::Medium,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function unregisteredStructureFinding(NpoEngagement $engagement, array $criteria): array
    {
        $structure = $this->legalStructure($engagement);

        return [
            'finding_key' => 'unregistered_structure_governance',
            'category' => 'legal_compliance',
            'severity' => FindingSeverity::Medium,
            'title' => 'Unregistered or non-charity governance pathway',
            'body' => 'The recorded structure does not trigger Charities Services or incorporated-society criteria. Advisor review should confirm whether registration, trust deed, officer eligibility, or funding obligations create additional governance criteria before client-facing release.',
            'criteria' => $criteria,
            'evidence' => [
                'legal_structure' => $structure->value,
                'legal_structure_label' => $structure->label(),
            ],
            'attributions' => [
                [
                    'claim' => 'The NPO engagement legal structure is '.$structure->label().'.',
                    'source_reference' => 'npo_engagement:'.$engagement->getKey(),
                ],
            ],
            'uncertainty' => Uncertainty::Medium,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @param  array<int, array<string, mixed>>  $evidence
     * @return array<string, mixed>
     */
    private function finding(
        NpoEngagement $engagement,
        ?QuestionnaireResponse $response,
        string $key,
        string $category,
        FindingSeverity $severity,
        string $title,
        string $body,
        array $criteria,
        array $evidence,
        string $fallbackClaim,
        Uncertainty $uncertainty,
    ): array {
        return [
            'finding_key' => $key,
            'category' => $category,
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'criteria' => $criteria,
            'evidence' => $evidence,
            'attributions' => [
                ...$this->attributionsForEvidence($engagement, $response, $evidence, $fallbackClaim),
                ...$this->criteriaAttributions($criteria),
            ],
            'uncertainty' => $uncertainty,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function criteriaAttributions(array $criteria): array
    {
        return array_values(array_filter(array_map(
            static fn (array $criterion): array => [
                'claim' => (string) $criterion['label'],
                'source_reference' => (string) $criterion['source_reference'],
            ],
            $criteria,
        ), static fn (array $attribution): bool => $attribution['claim'] !== '' && $attribution['source_reference'] !== ''));
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function attributionsForEvidence(
        NpoEngagement $engagement,
        ?QuestionnaireResponse $response,
        array $evidence,
        string $fallbackClaim,
    ): array {
        $attributions = [
            [
                'claim' => 'The finding belongs to the NPO governance review engagement.',
                'source_reference' => 'npo_engagement:'.$engagement->getKey(),
            ],
        ];

        if ($response instanceof QuestionnaireResponse) {
            $attributions[] = [
                'claim' => 'Governance review evidence comes from the submitted questionnaire response.',
                'source_reference' => 'questionnaire_response:'.$response->getKey(),
            ];
        }

        foreach ($evidence as $item) {
            $source = trim((string) ($item['source_reference'] ?? ''));
            if ($source === '') {
                continue;
            }

            $attributions[] = [
                'claim' => 'Governance review evidence: '.trim((string) ($item['prompt'] ?? $fallbackClaim)),
                'source_reference' => $source,
            ];
        }

        if ($evidence === []) {
            $attributions[] = [
                'claim' => $fallbackClaim,
                'source_reference' => 'questionnaire_set:'.QuestionnaireSet::GOVERNANCE_REVIEW->value,
            ];
        }

        return $this->uniqueAttributions($attributions);
    }

    /**
     * @param  array<int, array{claim:string, source_reference:string}>  $attributions
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function uniqueAttributions(array $attributions): array
    {
        $seen = [];
        $unique = [];

        foreach ($attributions as $attribution) {
            $key = $attribution['claim'].'|'.$attribution['source_reference'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $attribution;
        }

        return $unique;
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @param  array<int, array<string, mixed>>  $criteria
     * @param  array<int, array<string, mixed>>  $alerts
     * @return array<int, string>
     */
    private function sourceReferences(
        NpoEngagement $engagement,
        ?QuestionnaireResponse $response,
        array $evidence,
        array $criteria,
        array $alerts,
    ): array {
        $sources = [
            'npo_engagement:'.$engagement->getKey(),
        ];

        if ($response instanceof QuestionnaireResponse) {
            $sources[] = 'questionnaire_response:'.$response->getKey();
        } else {
            $sources[] = 'questionnaire_set:'.QuestionnaireSet::GOVERNANCE_REVIEW->value;
        }

        foreach ($evidence as $item) {
            $sources[] = (string) ($item['source_reference'] ?? '');
        }

        foreach ($criteria as $criterion) {
            $sources[] = (string) ($criterion['source_reference'] ?? '');
        }

        foreach ($alerts as $alert) {
            $sources[] = 'npo_compliance_alert:'.$alert['id'];
        }

        return array_values(array_unique(array_filter($sources)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $attributions
     */
    private function assertAttributions(array $attributions, string $title): void
    {
        if ($attributions === []) {
            throw new InvalidArgumentException("Governance finding [{$title}] has no source attributions.");
        }

        foreach ($attributions as $attribution) {
            if (trim((string) ($attribution['claim'] ?? '')) === '' || trim((string) ($attribution['source_reference'] ?? '')) === '') {
                throw new InvalidArgumentException("Governance finding [{$title}] has an incomplete source attribution.");
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function aiPayload(AiResponse $response): array
    {
        return [
            'text' => $response->text,
            'attributions' => $response->attributions,
            'uncertainty' => $response->uncertainty->value,
            'bias_signals' => $response->biasSignals,
            'model' => $response->model,
            'prompt_version' => $response->promptVersion,
            'prompt_hash' => $response->promptHash,
            'tokens_in' => $response->tokensIn,
            'tokens_out' => $response->tokensOut,
            'metadata' => $response->metadata,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function complianceAlerts(NpoEngagement $engagement): array
    {
        return NpoComplianceAlert::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->whereNull('resolved_at')
            ->latest('triggered_at')
            ->get()
            ->map(fn (NpoComplianceAlert $alert): array => [
                'id' => (string) $alert->getKey(),
                'type' => $alert->type,
                'severity' => $alert->severity,
                'message' => $alert->message,
                'source' => $alert->source,
                'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
                'blocks_analysis' => $alert->blocksAnalysis(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    private function criteriaSubset(array $criteria, array $keys): array
    {
        return array_values(array_filter(
            $criteria,
            static fn (array $criterion): bool => in_array((string) ($criterion['key'] ?? ''), $keys, true),
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     * @param  array<int, string>  $needles
     * @return array<int, array<string, mixed>>
     */
    private function matchingEvidence(array $evidence, array $needles): array
    {
        return array_values(array_filter($evidence, function (array $item) use ($needles): bool {
            $haystack = strtolower(trim((string) ($item['section'] ?? '').' '.(string) ($item['prompt'] ?? '').' '.$this->valueText($item['value'] ?? null)));

            foreach ($needles as $needle) {
                if (str_contains($haystack, strtolower($needle))) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function hasDocumentEvidence(array $items): bool
    {
        foreach ($items as $item) {
            $documentIds = $item['attached_document_ids'] ?? [];
            if (is_array($documentIds) && $documentIds !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     */
    private function documentEvidenceCount(array $evidence): int
    {
        return array_sum(array_map(function (array $item): int {
            $documentIds = $item['attached_document_ids'] ?? [];

            return is_array($documentIds) ? count($documentIds) : 0;
        }, $evidence));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function summary(array $items): string
    {
        $parts = array_slice(array_map(
            fn (array $item): string => trim((string) ($item['prompt'] ?? 'evidence item')).' = '.$this->valueText($item['value'] ?? null),
            $items,
        ), 0, 4);

        return implode('; ', $parts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function evidenceText(array $items): string
    {
        return strtolower(implode(' ', array_map(
            fn (array $item): string => trim((string) ($item['prompt'] ?? '').' '.$this->valueText($item['value'] ?? null)),
            $items,
        )));
    }

    /**
     * @param  array<int, array<string, mixed>>  $evidence
     */
    private function paidStaffPresent(array $evidence): bool
    {
        $items = $this->matchingEvidence($evidence, ['employ paid staff', 'paid-staff oversight']);
        $text = $this->evidenceText($items);

        return $this->containsAny($text, ['yes', 'payroll', 'paid staff', 'contractors']);
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function displayValue(QuestionnaireAnswer $answer): mixed
    {
        $value = $answer->value;

        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_values(array_map(
                    fn (mixed $item): string => $this->optionLabel($answer, (string) $item),
                    $value,
                ));
            }

            return $value;
        }

        if (is_scalar($value)) {
            return $this->optionLabel($answer, (string) $value);
        }

        return null;
    }

    private function optionLabel(QuestionnaireAnswer $answer, string $value): string
    {
        $options = $answer->question?->options ?? [];
        if (! is_array($options)) {
            return $value;
        }

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            if ((string) ($option['value'] ?? '') === $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function documentIds(QuestionnaireAnswer $answer): array
    {
        $ids = $answer->attached_document_ids ?? [];
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $ids)));
    }

    private function valueText(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map('strval', Arr::flatten($value)));
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function legalStructure(NpoEngagement $engagement): NpoLegalStructure
    {
        return $engagement->legal_structure instanceof NpoLegalStructure
            ? $engagement->legal_structure
            : NpoLegalStructure::from((string) $engagement->legal_structure);
    }

    private function requiresCharityCriteria(NpoLegalStructure $structure): bool
    {
        return in_array($structure, [
            NpoLegalStructure::RegisteredCharity,
            NpoLegalStructure::RegisteredCharityAndIncorporatedSociety,
            NpoLegalStructure::CharitableTrustBoard,
            NpoLegalStructure::SocialEnterpriseRegisteredCharity,
        ], true);
    }

    private function requiresIncorporatedSocietyCriteria(NpoLegalStructure $structure): bool
    {
        return in_array($structure, [
            NpoLegalStructure::IncorporatedSociety,
            NpoLegalStructure::RegisteredCharityAndIncorporatedSociety,
        ], true);
    }
}
