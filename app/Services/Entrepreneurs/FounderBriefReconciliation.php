<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Pdf\PdfText;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * An advisor's source-bound reconciliation, not an automated financial opinion.
 *
 * @phpstan-type Decision array{state:string,note:string}
 * @phpstan-type Decisions array<string,Decision>
 * @phpstan-type Topic array{key:string,title:string,guide:string,plan_keys:list<string>,budget_facts:list<string>,state:string,note:string}
 * @phpstan-type Reconciliation array{source_token:string,available:bool,current:bool,complete:bool,as_of:string|null,saved_at:string|null,topics:list<Topic>,sources:list<array{key:string,title:string,body:string}>}
 */
final class FounderBriefReconciliation
{
    public function __construct(
        private readonly ExecutiveSummaryContext $context,
        private readonly FounderBriefReconciliationFacts $facts,
    ) {}

    /** @return Reconciliation */
    public function evaluate(BusinessPlan $plan, EntrepreneurProfile $profile): array
    {
        $plan->loadMissing('assessments', 'sections', 'budgetRunway');
        // Evidence can be attached before its response text is written. Include
        // those files as well as the assessed narrative and Budget fingerprint.
        $evidence = $plan->sections->map(fn (PlanSection $section): array => [
            'key' => $section->key, 'files' => (array) $section->attached_document_ids,
        ])->filter(fn (array $row): bool => $row['files'] !== [])->sortBy('key')->values()->all();
        $token = hash('sha256', json_encode([$this->context->hash($plan, $profile), $evidence], JSON_THROW_ON_ERROR));
        $assessment = $plan->assessments->sortByDesc('round')->first();
        $saved = (array) data_get($assessment?->scoring_scope, 'financial_reconciliation', []);
        $current = is_string($saved['source_token'] ?? null) && hash_equals($saved['source_token'], $token);
        $facts = $this->facts->build($plan);
        $topics = [];
        foreach (FounderBriefReconciliationTopics::all() as $key => $definition) {
            $decision = (array) data_get($saved, 'decisions.'.$key, []);
            $topics[] = [
                'key' => $key, ...$definition, 'budget_facts' => $facts[$key] ?? [],
                'state' => $current ? (string) ($decision['state'] ?? 'unresolved') : 'unresolved',
                'note' => (string) ($decision['note'] ?? ''),
            ];
        }
        $sources = $plan->sections->filter(fn (PlanSection $section): bool => $section->key !== BusinessPlanExecutiveSummary::SECTION_KEY)
            ->map(fn (PlanSection $section): array => [
                'key' => (string) data_get($section->metadata, 'requirement_key', $section->key),
                'title' => (string) $section->title,
                'body' => trim(PdfText::normalise(html_entity_decode(strip_tags(Str::markdown((string) $section->body, ['html_input' => 'strip', 'allow_unsafe_links' => false])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))),
            ])->values()->all();

        return [
            'source_token' => $token, 'available' => $assessment instanceof PlanAssessment,
            'current' => $current,
            'complete' => $current && is_string($saved['as_of'] ?? null)
                && collect($topics)->every(fn (array $topic): bool => in_array($topic['state'], ['aligned', 'explained', 'not_applicable'], true) && mb_strlen(trim($topic['note'])) >= 15),
            'as_of' => is_string($saved['as_of'] ?? null) ? $saved['as_of'] : null,
            'saved_at' => is_string($saved['saved_at'] ?? null) ? $saved['saved_at'] : null,
            'topics' => $topics, 'sources' => $sources,
        ];
    }

    /** @param Decisions $decisions */
    public function save(BusinessPlan $plan, EntrepreneurProfile $profile, User $advisor, string $token, string $asOf, array $decisions): void
    {
        $status = $this->evaluate($plan, $profile);
        if (! $status['available'] || ! hash_equals($status['source_token'], $token)) {
            throw ValidationException::withMessages(['reconciliation' => 'The source plan or Budget changed. Refresh, compare the current figures, and save the reconciliation again.']);
        }
        foreach (array_keys(FounderBriefReconciliationTopics::all()) as $key) {
            if (! isset($decisions[$key]) || ! in_array($decisions[$key]['state'], ['unresolved', 'aligned', 'explained', 'not_applicable'], true)
                || ($decisions[$key]['state'] !== 'unresolved' && mb_strlen(trim($decisions[$key]['note'])) < 15)) {
                throw ValidationException::withMessages(['reconciliation' => 'Record an explanation for every resolved topic, or leave it unresolved.']);
            }
            $decisions[$key]['note'] = trim($decisions[$key]['note']);
        }
        $assessment = $plan->assessments->sortByDesc('round')->first();
        $assessment = PlanAssessment::query()->lockForUpdate()->findOrFail($assessment?->getKey());
        $scope = (array) $assessment->scoring_scope;
        $before = $scope['financial_reconciliation'] ?? null;
        $scope['financial_reconciliation'] = [
            'source_token' => $token, 'as_of' => $asOf, 'saved_at' => now()->toIso8601String(),
            'revision' => (string) Str::uuid(),
            'advisor_id' => (int) $advisor->getKey(), 'decisions' => $decisions,
        ];
        $assessment->forceFill(['scoring_scope' => $scope])->save();
        app(AuditWriter::class)->record('entrepreneur.financial_reconciliation_saved', subject: $assessment, actor: $advisor, before: is_array($before) ? $before : [], after: $scope['financial_reconciliation']);
    }
}
