<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Enums\AnalysisLens;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Exceptions\MissingAttributionException;
use App\Services\Ai\Integrity\SourceAttribution;
use App\Services\Ai\Prompts\PromptRegistry;
use App\Services\Analysis\Contracts\AnalysisModule;
use App\Services\Audit\AuditWriter;
use App\Services\DataQuality\DataQualityScore;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\Documents\DocumentVerificationBlockedException;
use App\Services\Documents\DocumentVerificationGate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class AnalysisRunner
{
    public function __construct(
        private readonly DataQualityScorer $dataQuality,
        private readonly DocumentVerificationGate $documents,
        private readonly PromptRegistry $prompts,
        private readonly AiClient $ai,
        private readonly SourceAttribution $sourceAttribution,
        private readonly AnalyticalFramework $framework,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{created_by_user_id?: int|string|null, actor?: Authenticatable|null}  $options
     */
    public function run(Client $client, AnalysisModule $module, array $options = []): AnalysisRun
    {
        $score = $this->dataQuality->score($client);
        $run = $this->createRun($client, $module, $score, $options['created_by_user_id'] ?? null);

        if ($score->level === Client::DATA_QUALITY_INSUFFICIENT) {
            try {
                $this->documents->ensureClear($client);
            } catch (DocumentVerificationBlockedException $e) {
                return $this->blockForDocuments($run, $e, $options['actor'] ?? null);
            }

            return $this->blockForDataQuality($run, $score, $options['actor'] ?? null);
        }

        try {
            $this->documents->ensureClear($client);
        } catch (DocumentVerificationBlockedException $e) {
            return $this->blockForDocuments($run, $e, $options['actor'] ?? null);
        }

        try {
            $prompt = $this->prompts->envelope(
                id: $module->promptId(),
                input: $module->promptInput($client, $score),
                dataQualitySummary: $score->toPayload(),
                sourceReferences: $module->sourceReferences($client, $score),
            );
            $response = $this->ai->analyse($prompt);
            $this->sourceAttribution->validate($response);

            $findings = $module->mapFindings($client, $response, $score);
            $persistedLenses = $this->persistFindings($run, $client, $response, $score, $findings, $options['actor'] ?? null);
        } catch (MissingAttributionException $e) {
            return $this->failForIntegrityViolation($run, $e, $options['actor'] ?? null);
        } catch (Throwable $e) {
            return $this->failRun($run, $e, $options['actor'] ?? null);
        }

        $run->forceFill([
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => $this->framework->normalise($persistedLenses),
            'ai_model' => $response->model,
            'prompt_version' => $response->promptVersion,
            'prompt_hash' => $response->promptHash,
            'tokens_in' => $response->tokensIn,
            'tokens_out' => $response->tokensOut,
            'completed_at' => now(),
        ])->save();

        $this->audit->record(
            action: 'analysis.completed',
            subject: $run,
            actor: $this->actor($options['actor'] ?? null),
            after: [
                'module' => $module->module()->value,
                'findings_created' => count($persistedLenses),
                'framework_lenses' => $run->framework_lenses,
            ],
        );

        return $run->refresh()->load('findings');
    }

    private function createRun(Client $client, AnalysisModule $module, DataQualityScore $score, mixed $createdByUserId): AnalysisRun
    {
        return AnalysisRun::query()->create([
            'client_id' => $client->getKey(),
            'module' => $module->module(),
            'status' => AnalysisRun::STATUS_RUNNING,
            'framework_lenses' => [],
            'data_quality_snapshot' => $score->toPayload(),
            'tokens_in' => 0,
            'tokens_out' => 0,
            'started_at' => now(),
            'created_by_user_id' => $this->normaliseUserId($createdByUserId),
        ]);
    }

    private function blockForDataQuality(AnalysisRun $run, DataQualityScore $score, ?Authenticatable $actor): AnalysisRun
    {
        $run->forceFill([
            'status' => AnalysisRun::STATUS_BLOCKED_DATA_QUALITY,
            'completed_at' => now(),
        ])->save();

        $this->audit->record(
            action: 'analysis.blocked_data_quality',
            subject: $run,
            actor: $this->actor($actor),
            after: [
                'data_quality' => $score->toPayload(),
            ],
        );

        return $run->refresh();
    }

    private function blockForDocuments(AnalysisRun $run, DocumentVerificationBlockedException $e, ?Authenticatable $actor): AnalysisRun
    {
        $run->forceFill([
            'status' => AnalysisRun::STATUS_BLOCKED_DOCUMENTS,
            'completed_at' => now(),
        ])->save();

        $this->audit->record(
            action: 'analysis.blocked_documents',
            subject: $run,
            actor: $this->actor($actor),
            after: [
                'blocking_verification_ids' => $e->flags
                    ->map(static fn ($flag): string => (string) $flag->getKey())
                    ->values()
                    ->all(),
            ],
        );

        return $run->refresh();
    }

    private function failForIntegrityViolation(AnalysisRun $run, MissingAttributionException $e, ?Authenticatable $actor): AnalysisRun
    {
        $run->forceFill([
            'status' => AnalysisRun::STATUS_FAILED,
            'completed_at' => now(),
        ])->save();

        $this->audit->record(
            action: 'analysis.integrity_violation',
            subject: $run,
            actor: $this->actor($actor),
            after: [
                'reason' => $e->getMessage(),
            ],
        );

        return $run->refresh();
    }

    private function failRun(AnalysisRun $run, Throwable $e, ?Authenticatable $actor): AnalysisRun
    {
        $run->forceFill([
            'status' => AnalysisRun::STATUS_FAILED,
            'completed_at' => now(),
        ])->save();

        $this->audit->record(
            action: 'analysis.failed',
            subject: $run,
            actor: $this->actor($actor),
            after: [
                'error' => $e::class,
                'message' => $e->getMessage(),
            ],
        );

        return $run->refresh();
    }

    /**
     * @param  array<int, AnalysisFindingData>  $findings
     * @return array<int, AnalysisLens>
     */
    private function persistFindings(
        AnalysisRun $run,
        Client $client,
        AiResponse $response,
        DataQualityScore $score,
        array $findings,
        ?Authenticatable $actor,
    ): array {
        $persistedLenses = [];
        $disclaimer = $this->dataQualityDisclaimer($score);

        foreach ($findings as $finding) {
            $attributions = $finding->attributions ?? $response->attributions;

            if (! $this->hasCompleteAttributions($attributions)) {
                $this->audit->record(
                    action: 'analysis.finding_dropped_missing_attribution',
                    subject: $run,
                    actor: $this->actor($actor),
                    after: [
                        'title' => $finding->title,
                        'lens' => $finding->lens->value,
                    ],
                );

                continue;
            }

            AnalysisFinding::query()->create([
                'analysis_run_id' => $run->getKey(),
                'client_id' => $client->getKey(),
                'lens' => $finding->lens,
                'severity' => $finding->severity,
                'title' => $finding->title,
                'body' => $finding->body,
                'attributions' => $attributions,
                'document_support' => $finding->documentSupport,
                'uncertainty' => $finding->uncertainty ?? $response->uncertainty,
                'data_quality_disclaimer' => $finding->dataQualityDisclaimer ?? $disclaimer,
                'bias_signals' => $finding->biasSignals ?? $response->biasSignals,
                'pv_link_id' => $finding->pvLinkId,
            ]);

            $persistedLenses[] = $finding->lens;
        }

        return $persistedLenses;
    }

    private function dataQualityDisclaimer(DataQualityScore $score): ?string
    {
        if ($score->level === Client::DATA_QUALITY_HIGH) {
            return null;
        }

        if (! in_array($score->level, [Client::DATA_QUALITY_MEDIUM, Client::DATA_QUALITY_LOW], true)) {
            return null;
        }

        return sprintf(
            'Data quality is %s (%d/100): %s',
            $score->label(),
            $score->score,
            $score->message(),
        );
    }

    /**
     * @param  array<int, array{claim:string, source_reference:string}>  $attributions
     */
    private function hasCompleteAttributions(array $attributions): bool
    {
        if ($attributions === []) {
            return false;
        }

        foreach ($attributions as $attribution) {
            if (trim((string) ($attribution['claim'] ?? '')) === '') {
                return false;
            }

            if (trim((string) ($attribution['source_reference'] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    private function normaliseUserId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        $id = Auth::id();

        return is_int($id) ? $id : null;
    }

    private function actor(?Authenticatable $actor): ?Authenticatable
    {
        if ($actor instanceof User) {
            return $actor;
        }

        return $actor;
    }
}
