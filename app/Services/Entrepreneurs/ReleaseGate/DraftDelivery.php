<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Models\BusinessPlan;
use App\Services\Audit\AuditWriter;

final readonly class DraftDelivery
{
    public function __construct(private ReleaseGate $gate, private GateContextFactory $contexts, private RulePolicyStore $policies, private AuditWriter $audit) {}

    /** @param callable():RenderedDraft $produce */
    public function render(?BusinessPlan $plan, string $kind, callable $produce, ?string $historicalJson = null): string
    {
        if ($plan === null || ! $plan->exists) {
            return $produce()->bytes;
        }
        $initial = $this->contexts->current($plan, $kind);
        $configuration = $this->policies->current();
        $output = $produce();
        $current = $this->contexts->current($plan, $kind);
        abort_unless(hash_equals($configuration->hash(), $this->policies->current()->hash())
            && ($historicalJson !== null || hash_equals($initial->source->hash(), $current->source->hash())),
            409, 'The source or release policy changed while this draft was being generated. Download the current version again.');
        $context = $initial;
        if ($historicalJson !== null) {
            // Historical content cannot borrow current financial verification.
            $hash = hash('sha256', $historicalJson);
            $source = new SourceVersion((string) $plan->getKey(), $hash, $hash, ['historical_snapshot' => $hash], 0, $initial->source->asOf, $historicalJson);
            $context = new DocumentContext($source, new FactSet([]), $kind, Audience::Internal, [], false);
        } elseif ($output->candidateHtml !== null) {
            $blocks = (new CandidateBlocks)->fromHtml($output->candidateHtml, (string) $plan->getKey(), $output->renderer);
            $context = new DocumentContext($context->source, $context->facts, $kind, Audience::External, $blocks, false);
        } else {
            // A fallback has its own output and never inherits primary checks.
            $context = new DocumentContext($context->source, $context->facts, $kind, Audience::External, [], false);
        }
        $report = PhaseOneRules::evaluator()->evaluate($context, $configuration);
        $run = $this->gate->record($plan, $report, auth()->user());
        $this->audit->record('entrepreneur.draft_document_delivered', subject: $plan, actor: auth()->user(), after: [
            'run_id' => $run->getKey(), 'document' => $kind, 'renderer' => $output->renderer,
            'sha256' => hash('sha256', $output->bytes), 'status' => 'draft', 'final_file_verified' => false, 'historical' => $historicalJson !== null,
        ]);

        return $output->bytes;
    }
}
