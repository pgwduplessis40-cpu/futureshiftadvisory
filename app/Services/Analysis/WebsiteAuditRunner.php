<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Enums\AnalysisModule as AnalysisModuleEnum;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\User;
use App\Models\WebsiteAuditSnapshot;
use App\Services\Analysis\Modules\WebsiteAudit;
use App\Services\Audit\AuditWriter;
use App\Services\DataQuality\DataQualityScorer;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

final class WebsiteAuditRunner
{
    public function __construct(
        private readonly AnalysisRunner $analysis,
        private readonly WebsiteAudit $module,
        private readonly WebsiteUrlConfirmationService $confirmations,
        private readonly WebsiteFetcher $fetcher,
        private readonly WebsitePageParser $parser,
        private readonly WebsiteTechnicalProbe $technicalProbe,
        private readonly PageSpeedProbe $pageSpeed,
        private readonly NzTrustComplianceCheck $nzCompliance,
        private readonly WebsiteHealthScorer $scores,
        private readonly WebsiteAuditSnapshotStore $snapshots,
        private readonly WebsiteAuditSnapshotContext $context,
        private readonly DataQualityScorer $dataQuality,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{created_by_user_id?: int|string|null, actor?: Authenticatable|null, skip_document_gate?: bool, skip_data_quality_gate?: bool}  $options
     */
    public function run(Client $client, array $options = []): AnalysisRun
    {
        $confirmation = $this->confirmations->latestConfirmed($client);
        if ($confirmation === null) {
            $reason = $this->confirmations->questionnaireCandidates($client) === []
                ? WebsiteAuditSnapshot::SKIP_NO_WEBSITE_URL_LISTED
                : WebsiteAuditSnapshot::SKIP_AWAITING_ADVISOR_CONFIRMATION;
            $snapshot = $this->snapshots->skip($client, $reason);

            return $this->completeWithoutAi($client, $snapshot, $options, 'website_audit.skipped_no_url');
        }

        try {
            $fetch = $this->fetcher->fetch($confirmation);
        } catch (Throwable $exception) {
            report($exception);
            $snapshot = $this->snapshots->create($client, [
                'website_url_confirmation_id' => $confirmation->getKey(),
                'root_url' => $confirmation->root_url,
                'fetched_at' => now(),
                'pages' => [],
                'ai_evidence' => [],
                'technical' => ['measured' => false],
                'performance' => ['measured' => false],
                'nz_compliance' => ['measured' => false],
                'scores' => ['overall' => null],
                'fetch_status' => WebsiteAuditSnapshot::STATUS_UNREACHABLE,
                'source_attributions' => [],
            ]);

            return $this->completeWithoutAi($client, $snapshot, $options, 'website_audit.unreachable');
        }

        $parsed = array_map(fn (array $page): array => $this->parser->parse($page), $fetch['pages']);
        $pages = array_map(static fn (array $item): array => $item['page'], $parsed);
        $evidence = array_map(static fn (array $item): array => $item['evidence'], $parsed);
        $technical = $this->technicalProbe->assess($fetch, $pages);
        $performance = in_array($fetch['fetch_status'], [WebsiteAuditSnapshot::STATUS_OK, WebsiteAuditSnapshot::STATUS_PARTIAL], true)
            ? $this->pageSpeed->measure($fetch['root_url'])
            : ['measured' => false, 'reason' => 'Website fetch was not available for performance measurement.'];
        $nzCompliance = $this->nzCompliance->assess($client, $pages);
        $score = $this->scores->score($pages, $technical, $performance, $nzCompliance);
        $attributions = array_values(array_map(static fn (array $page): array => [
            'claim' => 'Website page was fetched and parsed for this audit.',
            'source_reference' => (string) ($page['source_reference'] ?? ''),
        ], array_filter($pages, static fn (array $page): bool => trim((string) ($page['source_reference'] ?? '')) !== '')));
        $snapshot = $this->snapshots->create($client, [
            'website_url_confirmation_id' => $confirmation->getKey(),
            'root_url' => $fetch['root_url'],
            'fetched_at' => now(),
            'pages' => $pages,
            'ai_evidence' => [
                'score_source' => 'deterministic_signals_plus_examiner_review',
                'pages' => $evidence,
            ],
            'technical' => $technical,
            'performance' => $performance,
            'nz_compliance' => $nzCompliance,
            'scores' => $score,
            'fetch_status' => $fetch['fetch_status'],
            'source_attributions' => $attributions,
        ]);

        $hasReadablePage = collect($pages)->contains(fn (array $page): bool => (int) ($page['http_status'] ?? 0) >= 200
            && (int) ($page['http_status'] ?? 0) < 300
            && (int) ($page['word_count'] ?? 0) > 0);
        if (! $hasReadablePage) {
            return $this->completeWithoutAi($client, $snapshot, $options, 'website_audit.not_measured');
        }

        $this->context->bind($snapshot);
        try {
            $run = $this->analysis->run($client, $this->module, $options);
        } finally {
            $this->context->clear();
        }

        $snapshot->forceFill(['analysis_run_id' => $run->getKey()])->save();
        $this->audit->record('website_audit.completed', subject: $snapshot, actor: $this->actor($options['actor'] ?? null), after: [
            'analysis_run_id' => $run->getKey(),
            'fetch_status' => $snapshot->fetch_status,
            'overall_score' => data_get($snapshot->scores, 'overall'),
        ]);

        return $run->refresh()->load('findings');
    }

    /**
     * @param  array{created_by_user_id?: int|string|null, actor?: Authenticatable|null}  $options
     */
    private function completeWithoutAi(Client $client, WebsiteAuditSnapshot $snapshot, array $options, string $action): AnalysisRun
    {
        $score = $this->dataQuality->score($client);
        $actor = $this->actor($options['actor'] ?? null);
        $run = AnalysisRun::query()->create([
            'client_id' => $client->getKey(),
            'module' => AnalysisModuleEnum::WebsiteAudit,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [],
            'data_quality_snapshot' => $score->toPayload(),
            'metadata' => [
                'website_audit_snapshot_id' => $snapshot->getKey(),
                'fetch_status' => $snapshot->fetch_status,
                'skip_reason' => $snapshot->skip_reason,
                'not_measured' => true,
            ],
            'tokens_in' => 0,
            'tokens_out' => 0,
            'started_at' => now(),
            'completed_at' => now(),
            'created_by_user_id' => $this->normaliseUserId($options['created_by_user_id'] ?? null, $actor),
        ]);
        $snapshot->forceFill(['analysis_run_id' => $run->getKey()])->save();
        $this->audit->record($action, subject: $snapshot, actor: $actor, after: [
            'analysis_run_id' => $run->getKey(),
            'fetch_status' => $snapshot->fetch_status,
            'skip_reason' => $snapshot->skip_reason,
        ]);

        return $run;
    }

    private function normaliseUserId(mixed $value, ?Authenticatable $actor): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return $actor instanceof User ? $actor->getKey() : null;
    }

    private function actor(mixed $actor): ?Authenticatable
    {
        return $actor instanceof Authenticatable ? $actor : null;
    }
}
