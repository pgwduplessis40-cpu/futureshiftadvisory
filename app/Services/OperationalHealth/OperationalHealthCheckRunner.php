<?php

declare(strict_types=1);

namespace App\Services\OperationalHealth;

use App\Enums\EngagementType;
use App\Models\Document;
use App\Models\OperationalHealthCheckResult;
use App\Models\OperationalHealthCheckRun;
use App\Models\Template;
use App\Models\User;
use App\Services\Security\MfaChallenger;
use App\Services\Security\StepUpEvaluator;
use App\Support\ReleaseVersion;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class OperationalHealthCheckRunner
{
    public function __construct(private readonly ReleaseVersion $releaseVersion) {}

    public function run(): OperationalHealthCheckRun
    {
        $startedAt = now();

        /** @var OperationalHealthCheckRun $run */
        $run = OperationalHealthCheckRun::query()->create([
            'status' => OperationalHealthCheckRun::STATUS_WARNING,
            'environment' => (string) config('app.env'),
            'release_version' => $this->releaseVersion->current(),
            'app_url' => (string) config('app.url'),
            'started_at' => $startedAt,
            'metadata' => [
                'php_version' => PHP_VERSION,
                'timezone' => (string) config('operational_health.timezone', 'Pacific/Auckland'),
                'runner' => self::class,
            ],
        ]);

        foreach ($this->definitions() as $definition) {
            $this->recordDefinition($run, $definition);
        }

        $this->finaliseRun($run, $startedAt);

        return $run->refresh()->load('results');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        $superAdmin = $this->superAdminUser();
        $clientUser = $this->clientPortalUser();
        $ddUser = $this->clientPortalUser(EngagementType::DUE_DILIGENCE);
        $entrepreneurUser = $this->entrepreneurUser();
        $document = $this->clientDocumentCandidate();
        $documentUser = $document instanceof Document && is_string($document->client_id)
            ? $this->clientPortalUserForClient($document->client_id)
            : null;
        $template = $this->templateCandidate();

        return [
            [
                'key' => 'core.up',
                'name' => 'Public health endpoint',
                'area' => 'Core availability',
                'method' => 'GET',
                'url' => '/up',
                'route_name' => null,
                'user' => null,
                'expected_statuses' => [200],
                'expected_behavior' => 'The Laravel health endpoint should return HTTP 200.',
                'missing_fixture' => null,
            ],
            [
                'key' => 'staff.dashboard',
                'name' => 'Staff dashboard',
                'area' => 'Authenticated shell',
                'method' => 'GET',
                'url' => route('dashboard', absolute: false),
                'route_name' => 'dashboard',
                'user' => $superAdmin,
                'expected_statuses' => [200],
                'expected_behavior' => 'A verified super administrator with an MFA-verified session should reach the staff dashboard.',
                'missing_fixture' => 'No super administrator monitor user is available.',
            ],
            [
                'key' => 'admin.app_health.index',
                'name' => 'App checks admin page',
                'area' => 'Operational monitoring',
                'method' => 'GET',
                'url' => route('admin.app-health.index', absolute: false),
                'route_name' => 'admin.app-health.index',
                'user' => $superAdmin,
                'expected_statuses' => [200],
                'expected_behavior' => 'A super administrator should be able to open the operational health findings page.',
                'missing_fixture' => 'No super administrator monitor user is available.',
            ],
            [
                'key' => 'admin.audit_trail.index',
                'name' => 'Audit trail admin page',
                'area' => 'Administration',
                'method' => 'GET',
                'url' => route('admin.audit-trail.index', absolute: false),
                'route_name' => 'admin.audit-trail.index',
                'user' => $superAdmin,
                'expected_statuses' => [200],
                'expected_behavior' => 'A super administrator should be able to open the audit trail.',
                'missing_fixture' => 'No super administrator monitor user is available.',
            ],
            [
                'key' => 'portal.dashboard',
                'name' => 'Client portal dashboard',
                'area' => 'Client portal',
                'method' => 'GET',
                'url' => route('portal.dashboard', absolute: false),
                'route_name' => 'portal.dashboard',
                'user' => $clientUser,
                'expected_statuses' => [200],
                'expected_behavior' => 'A client portal user with a client assignment should reach the portal dashboard.',
                'missing_fixture' => 'No client portal monitor user with a client assignment is available.',
            ],
            [
                'key' => 'portal.business_plan_budget.document',
                'name' => 'Business plan and budget document',
                'area' => 'Plan preview',
                'method' => 'GET',
                'url' => route('portal.business-plan-budget.document', absolute: false),
                'route_name' => 'portal.business-plan-budget.document',
                'user' => $clientUser,
                'expected_statuses' => [200],
                'expected_behavior' => 'A client portal user should be able to open the business plan and budget document view.',
                'missing_fixture' => 'No client portal monitor user with a client assignment is available.',
            ],
            [
                'key' => 'portal.business_plan_budget.pdf',
                'name' => 'Business plan and budget PDF',
                'area' => 'Plan preview',
                'method' => 'GET',
                'url' => route('portal.business-plan-budget.pdf', absolute: false),
                'route_name' => 'portal.business-plan-budget.pdf',
                'user' => $clientUser,
                'expected_statuses' => [200],
                'expected_content_type' => 'application/pdf',
                'expected_behavior' => 'A client portal user should be able to generate the business plan and budget PDF preview.',
                'missing_fixture' => 'No client portal monitor user with a client assignment is available.',
            ],
            [
                'key' => 'portal.dd_plan.preview',
                'name' => 'DD acquisition plan preview',
                'area' => 'Plan preview',
                'method' => 'GET',
                'url' => route('portal.dd-plan.preview', absolute: false),
                'route_name' => 'portal.dd-plan.preview',
                'user' => $ddUser,
                'expected_statuses' => [200],
                'expected_content_type' => 'application/pdf',
                'expected_behavior' => 'A client portal user on a due-diligence engagement should be able to open the acquisition plan PDF preview.',
                'missing_fixture' => 'No due-diligence client portal monitor user with a DD engagement is available.',
            ],
            [
                'key' => 'portal.entrepreneur.plan.preview',
                'name' => 'Entrepreneur business plan preview',
                'area' => 'Plan preview',
                'method' => 'GET',
                'url' => route('portal.entrepreneur.plan.preview', absolute: false),
                'route_name' => 'portal.entrepreneur.plan.preview',
                'user' => $entrepreneurUser,
                'expected_statuses' => [200],
                'expected_content_type' => 'application/pdf',
                'expected_behavior' => 'An entrepreneur monitor user should be able to open the business plan PDF preview when their package includes plan and budget access.',
                'missing_fixture' => 'No entrepreneur monitor user with an entrepreneur profile is available.',
            ],
            [
                'key' => 'portal.documents.show',
                'name' => 'Client document view',
                'area' => 'Documents',
                'method' => 'GET',
                'url' => $document instanceof Document
                    ? route('portal.documents.show', $document, absolute: false)
                    : null,
                'route_name' => 'portal.documents.show',
                'user' => $documentUser,
                'expected_statuses' => [200],
                'expected_behavior' => 'A client portal user should be able to view a clean client-visible uploaded document.',
                'missing_fixture' => 'No clean client-visible document with an assigned client portal monitor user is available.',
                'subject' => $document instanceof Document ? [
                    'type' => 'document',
                    'id' => (string) $document->getKey(),
                    'label' => $document->original_filename,
                ] : null,
                'document' => $document,
            ],
            [
                'key' => 'advisor.templates.preview',
                'name' => 'Advisor template preview',
                'area' => 'Documents',
                'method' => 'GET',
                'url' => $template instanceof Template
                    ? route('advisor.templates.preview', $template, absolute: false)
                    : null,
                'route_name' => 'advisor.templates.preview',
                'user' => $superAdmin,
                'expected_statuses' => [200],
                'expected_behavior' => 'A super administrator should be able to open a safe template preview. DOCX uploads should render the download-only notice, while PDFs/images can render inline.',
                'missing_fixture' => 'No active or archived template with a clean PDF, image, or DOCX upload is available.',
                'subject' => $template instanceof Template ? [
                    'type' => 'template',
                    'id' => (string) $template->getKey(),
                    'label' => $template->title,
                ] : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function recordDefinition(OperationalHealthCheckRun $run, array $definition): void
    {
        $user = $definition['user'] ?? null;
        $url = $definition['url'] ?? null;

        if (($definition['missing_fixture'] ?? null) !== null && (! $user instanceof User || ! is_string($url) || $url === '')) {
            $this->createResult($run, [
                ...$this->baseResult($definition),
                'status' => OperationalHealthCheckResult::STATUS_SKIPPED,
                'issue_summary' => 'Monitor fixture missing: '.(string) $definition['missing_fixture'],
                'issue_detail' => $this->issueDetail($definition, null, null, (string) $definition['missing_fixture']),
            ]);

            return;
        }

        if (! is_string($url) || trim($url) === '') {
            $this->createResult($run, [
                ...$this->baseResult($definition),
                'status' => OperationalHealthCheckResult::STATUS_SKIPPED,
                'issue_summary' => 'Monitor fixture missing: no URL could be resolved for this check.',
                'issue_detail' => $this->issueDetail($definition, null, null, 'No URL could be resolved.'),
            ]);

            return;
        }

        $probe = $this->dispatchInternalRequest(
            method: (string) ($definition['method'] ?? 'GET'),
            url: $url,
            user: $user instanceof User ? $user : null,
        );

        $expectedStatuses = array_map('intval', (array) ($definition['expected_statuses'] ?? [200]));
        $actualStatus = is_int($probe['status'] ?? null) ? $probe['status'] : null;
        $expectedContentType = is_string($definition['expected_content_type'] ?? null)
            ? strtolower((string) $definition['expected_content_type'])
            : null;
        $actualContentType = is_string($probe['content_type'] ?? null)
            ? strtolower((string) $probe['content_type'])
            : null;
        $statusPassed = $actualStatus !== null && in_array($actualStatus, $expectedStatuses, true);
        $contentTypePassed = $expectedContentType === null
            || ($actualContentType !== null && str_starts_with($actualContentType, $expectedContentType));
        $exceptionClass = is_string($probe['exception_class'] ?? null) ? $probe['exception_class'] : null;
        $exceptionMessage = is_string($probe['exception_message'] ?? null) ? $probe['exception_message'] : null;
        $status = $statusPassed && $contentTypePassed && $exceptionClass === null
            ? OperationalHealthCheckResult::STATUS_PASSED
            : OperationalHealthCheckResult::STATUS_FAILED;

        $issueSummary = $status === OperationalHealthCheckResult::STATUS_PASSED
            ? null
            : $this->issueSummary($definition, $expectedStatuses, $actualStatus, $expectedContentType, $actualContentType, $probe);
        $documentStorageDiagnostic = $status === OperationalHealthCheckResult::STATUS_FAILED
            && $actualStatus === 404
            && ($definition['document'] ?? null) instanceof Document
            ? $this->documentStorageDiagnostic($definition['document'])
            : null;

        $this->createResult($run, [
            ...$this->baseResult($definition),
            'status' => $status,
            'actual_status' => $actualStatus,
            'actual_content_type' => $actualContentType,
            'response_time_ms' => is_int($probe['duration_ms'] ?? null) ? $probe['duration_ms'] : null,
            'issue_summary' => $issueSummary,
            'issue_detail' => $status === OperationalHealthCheckResult::STATUS_PASSED
                ? null
                : $this->issueDetail($definition, $actualStatus, $actualContentType, $issueSummary, $probe, $documentStorageDiagnostic),
            'exception_class' => $exceptionClass,
            'exception_message' => $exceptionMessage,
            'context' => [
                'redirect_url' => $probe['redirect_url'] ?? null,
                'body_excerpt' => $probe['body_excerpt'] ?? null,
                'expected_content_type' => $expectedContentType,
                'internal_request' => true,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function baseResult(array $definition): array
    {
        $user = $definition['user'] ?? null;
        $subject = is_array($definition['subject'] ?? null) ? $definition['subject'] : [];

        return [
            'check_key' => (string) $definition['key'],
            'name' => (string) $definition['name'],
            'area' => (string) $definition['area'],
            'method' => (string) ($definition['method'] ?? 'GET'),
            'url' => $definition['url'] ?? null,
            'route_name' => $definition['route_name'] ?? null,
            'expected_statuses' => array_map('intval', (array) ($definition['expected_statuses'] ?? [200])),
            'expected_content_type' => $definition['expected_content_type'] ?? null,
            'actor_user_id' => $user instanceof User ? $user->getKey() : null,
            'actor_role' => $user instanceof User ? $user->fsaRole() : null,
            'actor_label' => $user instanceof User ? $user->name.' <'.$user->email.'>' : 'public',
            'workflow_subject_type' => $subject['type'] ?? null,
            'workflow_subject_id' => $subject['id'] ?? null,
            'workflow_subject_label' => $subject['label'] ?? null,
            'expected_behavior' => $definition['expected_behavior'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createResult(OperationalHealthCheckRun $run, array $attributes): OperationalHealthCheckResult
    {
        $status = (string) $attributes['status'];

        if ($status !== OperationalHealthCheckResult::STATUS_PASSED) {
            $fingerprint = $this->fingerprint($attributes);
            $attributes = [
                ...$attributes,
                ...$this->recurrencePayload(
                    checkKey: (string) $attributes['check_key'],
                    fingerprint: $fingerprint,
                    status: $status,
                ),
                'fingerprint' => $fingerprint,
            ];
        }

        /** @var OperationalHealthCheckResult $result */
        $result = $run->results()->create($attributes);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fingerprint(array $attributes): string
    {
        $basis = implode('|', [
            (string) ($attributes['check_key'] ?? ''),
            (string) ($attributes['status'] ?? ''),
            (string) ($attributes['actual_status'] ?? 'none'),
            (string) ($attributes['exception_class'] ?? 'none'),
            Str::lower(Str::limit((string) ($attributes['issue_summary'] ?? ''), 180, '')),
        ]);

        return hash('sha256', $basis);
    }

    /**
     * @return array<string, mixed>
     */
    private function recurrencePayload(string $checkKey, string $fingerprint, string $status): array
    {
        $now = now();
        $latestForCheck = OperationalHealthCheckResult::query()
            ->where('check_key', $checkKey)
            ->latest()
            ->first();
        $firstSeen = OperationalHealthCheckResult::query()
            ->where('fingerprint', $fingerprint)
            ->oldest()
            ->value('created_at');

        return [
            'consecutive_failures' => $latestForCheck instanceof OperationalHealthCheckResult
                && $latestForCheck->fingerprint === $fingerprint
                && $latestForCheck->status === $status
                    ? ((int) $latestForCheck->consecutive_failures) + 1
                    : 1,
            'failures_last_7_days' => OperationalHealthCheckResult::query()
                ->where('fingerprint', $fingerprint)
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->count() + 1,
            'failures_last_30_days' => OperationalHealthCheckResult::query()
                ->where('fingerprint', $fingerprint)
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->count() + 1,
            'first_seen_at' => $firstSeen !== null ? Carbon::parse($firstSeen) : $now,
            'last_seen_at' => $now,
        ];
    }

    private function finaliseRun(OperationalHealthCheckRun $run, CarbonInterface $startedAt): void
    {
        $results = $run->results()->get();
        $failed = $results->where('status', OperationalHealthCheckResult::STATUS_FAILED)->count();
        $skipped = $results->where('status', OperationalHealthCheckResult::STATUS_SKIPPED)->count();
        $warnings = $results->where('status', OperationalHealthCheckResult::STATUS_WARNING)->count();
        $finishedAt = now();

        $run->forceFill([
            'status' => $failed > 0
                ? OperationalHealthCheckRun::STATUS_FAILED
                : (($warnings + $skipped) > 0
                    ? OperationalHealthCheckRun::STATUS_WARNING
                    : OperationalHealthCheckRun::STATUS_PASSED),
            'duration_ms' => (int) max(0, $startedAt->diffInMilliseconds($finishedAt, false)),
            'total_checks' => $results->count(),
            'passed_checks' => $results->where('status', OperationalHealthCheckResult::STATUS_PASSED)->count(),
            'warning_checks' => $warnings,
            'failed_checks' => $failed,
            'skipped_checks' => $skipped,
            'finished_at' => $finishedAt,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatchInternalRequest(string $method, string $url, ?User $user): array
    {
        $started = hrtime(true);
        $session = app('session')->driver();
        $originalSessionId = $session->getId();
        $originalSessionData = $session->all();
        $originalUser = Auth::guard('web')->user();

        $session->setId(Str::random(40));
        $session->replace([]);
        $session->start();

        if ($user instanceof User) {
            $session->put(MfaChallenger::SESSION_USER_ID, (string) $user->getAuthIdentifier());
            $session->put(MfaChallenger::SESSION_CONFIRMED_AT, now()->getTimestamp());
            app('auth')->forgetGuards();
            Auth::guard('web')->setUser($user);
        }

        $request = Request::create($url, strtoupper($method), [], [], [], [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_VERSION' => $this->inertiaVersion(),
            'HTTP_USER_AGENT' => 'FutureShiftAdvisory OperationalHealthCheck/1.0',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $request->setLaravelSession($session);

        if ($user instanceof User) {
            $request->setUserResolver(static fn (?string $guard = null): User => $user);
        }

        // Each probe has its own stable device identity, rather than inheriting the
        // browser session's fingerprint and tripping the super-admin step-up guard.
        app(StepUpEvaluator::class)->rememberDevice($request);

        $kernel = app(HttpKernel::class);

        try {
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);

            return $this->responsePayload($response, $started);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'status' => 500,
                'duration_ms' => $this->elapsedMs($started),
                'content_type' => null,
                'redirect_url' => null,
                'body_excerpt' => null,
                'exception_class' => $exception::class,
                'exception_message' => Str::limit($exception->getMessage(), 500, ''),
            ];
        } finally {
            app('auth')->forgetGuards();

            if ($originalUser instanceof User) {
                Auth::guard('web')->setUser($originalUser);
            }

            $session->setId($originalSessionId);
            $session->replace($originalSessionData);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function responsePayload(Response $response, int $started): array
    {
        $content = null;
        if (method_exists($response, 'getContent')) {
            $content = $response->getContent();
        }

        return [
            'status' => $response->getStatusCode(),
            'duration_ms' => $this->elapsedMs($started),
            'content_type' => $response->headers->get('Content-Type'),
            'redirect_url' => $response->headers->get('Location'),
            'body_excerpt' => is_string($content) ? $this->bodyExcerpt($content) : null,
            'exception_class' => null,
            'exception_message' => null,
        ];
    }

    private function inertiaVersion(): string
    {
        $assetVersion = null;
        if (config('app.asset_url')) {
            $assetVersion = hash('xxh128', (string) config('app.asset_url'));
        }

        $manifest = public_path('build/manifest.json');
        if ($assetVersion === null && file_exists($manifest)) {
            $assetVersion = (string) hash_file('xxh128', $manifest);
        }

        $mixManifest = public_path('mix-manifest.json');
        if ($assetVersion === null && file_exists($mixManifest)) {
            $assetVersion = (string) hash_file('xxh128', $mixManifest);
        }

        $releaseVersion = $this->releaseVersion->current();

        if ($releaseVersion === '') {
            return (string) $assetVersion;
        }

        return $assetVersion !== null
            ? $releaseVersion.'-'.$assetVersion
            : $releaseVersion;
    }

    private function elapsedMs(int $started): int
    {
        return (int) max(0, round((hrtime(true) - $started) / 1_000_000));
    }

    private function bodyExcerpt(string $content): ?string
    {
        $content = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $content);
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($content)));

        return $text === '' ? null : Str::limit($text, 500, '');
    }

    private function documentStorageDiagnostic(Document $document): ?string
    {
        if (! is_string($document->stored_path) || trim($document->stored_path) === '') {
            return 'Confirmed cause: the client-visible document record has no secure storage path.';
        }

        try {
            Storage::disk('secure_local')->get($document->stored_path);
        } catch (UnableToReadFile $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'mac is invalid')) {
                return 'Confirmed cause: the secure document file is present but cannot be decrypted because its encryption MAC is invalid. Re-upload the original document; do not reuse this stored file.';
            }

            return 'Confirmed cause: the secure document file cannot be read. '.Str::limit($exception->getMessage(), 300, '');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, int>  $expectedStatuses
     * @param  array<string, mixed>  $probe
     */
    private function issueSummary(
        array $definition,
        array $expectedStatuses,
        ?int $actualStatus,
        ?string $expectedContentType,
        ?string $actualContentType,
        array $probe,
    ): string {
        $name = (string) $definition['name'];

        if (is_string($probe['exception_class'] ?? null)) {
            return "{$name} threw {$probe['exception_class']}: ".Str::limit((string) ($probe['exception_message'] ?? 'No message'), 220, '');
        }

        $expected = implode(', ', $expectedStatuses);
        if ($actualStatus === null || ! in_array($actualStatus, $expectedStatuses, true)) {
            $summary = "{$name} returned HTTP ".($actualStatus ?? 'none')."; expected {$expected}.";
            $redirect = $probe['redirect_url'] ?? null;

            return is_string($redirect) && $redirect !== ''
                ? "{$summary} Redirect target: {$redirect}."
                : $summary;
        }

        return "{$name} returned content type ".($actualContentType ?? 'none')."; expected {$expectedContentType}.";
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $probe
     */
    private function issueDetail(
        array $definition,
        ?int $actualStatus,
        ?string $actualContentType,
        ?string $issueSummary,
        array $probe = [],
        ?string $documentStorageDiagnostic = null,
    ): string {
        $expectedStatuses = implode(', ', array_map('strval', (array) ($definition['expected_statuses'] ?? [200])));
        $actor = $definition['user'] instanceof User
            ? $definition['user']->name.' <'.$definition['user']->email.'> as '.$definition['user']->fsaRole()
            : 'public';
        $parts = [
            $issueSummary ?: 'The check did not run.',
            'Check: '.(string) $definition['key'].' / '.(string) $definition['name'].'.',
            'Request: '.(string) ($definition['method'] ?? 'GET').' '.(string) ($definition['url'] ?? 'unresolved').'.',
            "Actor: {$actor}.",
            "Expected HTTP status: {$expectedStatuses}. Actual HTTP status: ".($actualStatus ?? 'none').'.',
        ];

        if (is_string($definition['expected_content_type'] ?? null)) {
            $parts[] = 'Expected content type: '.$definition['expected_content_type'].'. Actual content type: '.($actualContentType ?? 'none').'.';
        }

        if ($documentStorageDiagnostic !== null) {
            $parts[] = $documentStorageDiagnostic;
        }

        foreach ([
            'redirect_url' => 'Redirect target',
            'body_excerpt' => 'Response excerpt',
        ] as $key => $label) {
            $value = $probe[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $parts[] = "{$label}: {$value}";
            }
        }

        return implode(' ', $parts);
    }

    private function superAdminUser(): ?User
    {
        $configured = $this->configuredUser('super_admin_email');
        if ($configured instanceof User) {
            return $configured;
        }

        return User::query()
            ->where(function (Builder $query): void {
                $query->where('user_type', User::TYPE_SUPER_ADMIN)
                    ->orWhere('primary_role', User::TYPE_SUPER_ADMIN);
            })
            ->oldest('id')
            ->first();
    }

    private function clientPortalUser(?EngagementType $engagementType = null): ?User
    {
        $configured = $this->configuredUser('client_email');
        if ($configured instanceof User && $this->userHasClientAssignment($configured, $engagementType)) {
            return $configured;
        }

        if (! Schema::hasTable('client_team')) {
            return null;
        }

        return User::query()
            ->whereIn('user_type', [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM])
            ->whereExists(function ($query) use ($engagementType): void {
                $query->selectRaw('1')
                    ->from('client_team')
                    ->whereColumn('client_team.user_id', 'users.id');

                if ($engagementType instanceof EngagementType) {
                    $query->join('clients', 'clients.id', '=', 'client_team.client_id')
                        ->where('clients.engagement_type', $engagementType->value)
                        ->whereExists(function ($inner): void {
                            $inner->selectRaw('1')
                                ->from('dd_engagements')
                                ->whereColumn('dd_engagements.client_id', 'clients.id');
                        });
                }
            })
            ->oldest('id')
            ->first();
    }

    private function entrepreneurUser(): ?User
    {
        $configured = $this->configuredUser('entrepreneur_email');
        if ($configured instanceof User && $configured->entrepreneurProfile()->exists()) {
            return $configured;
        }

        return User::query()
            ->where('user_type', User::TYPE_ENTREPRENEUR)
            ->whereHas('entrepreneurProfile')
            ->oldest('id')
            ->first();
    }

    private function configuredUser(string $key): ?User
    {
        $email = config("operational_health.users.{$key}");

        return is_string($email) && trim($email) !== ''
            ? User::query()->whereRaw('LOWER(email) = ?', [Str::lower(trim($email))])->first()
            : null;
    }

    private function userHasClientAssignment(User $user, ?EngagementType $engagementType = null): bool
    {
        if (! Schema::hasTable('client_team')) {
            return false;
        }

        $query = DB::table('client_team')
            ->where('client_team.user_id', $user->getKey());

        if ($engagementType instanceof EngagementType) {
            $query->join('clients', 'clients.id', '=', 'client_team.client_id')
                ->where('clients.engagement_type', $engagementType->value);
        }

        return $query->exists();
    }

    private function clientPortalUserForClient(string $clientId): ?User
    {
        if (! Schema::hasTable('client_team')) {
            return null;
        }

        return User::query()
            ->whereIn('user_type', [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM])
            ->whereExists(function ($query) use ($clientId): void {
                $query->selectRaw('1')
                    ->from('client_team')
                    ->whereColumn('client_team.user_id', 'users.id')
                    ->where('client_team.client_id', $clientId);
            })
            ->oldest('id')
            ->first();
    }

    private function clientDocumentCandidate(): ?Document
    {
        if (! Schema::hasTable('documents')) {
            return null;
        }

        return Document::query()
            ->whereNotNull('client_id')
            ->where('scanner_result', Document::SCANNER_CLEAN)
            ->latest()
            ->limit(100)
            ->get()
            ->first(fn (Document $document): bool => $document->isVisibleToClients()
                && is_string($document->client_id)
                && $this->clientPortalUserForClient($document->client_id) instanceof User);
    }

    private function templateCandidate(): ?Template
    {
        if (! Schema::hasTable('templates')) {
            return null;
        }

        return Template::query()
            ->library()
            ->latest()
            ->limit(100)
            ->get()
            ->first(function (Template $template): bool {
                $upload = data_get($template->structure, 'uploaded_file');
                if (! is_array($upload) || ($upload['scanner_result'] ?? null) !== Document::SCANNER_CLEAN) {
                    return false;
                }

                $mime = (string) ($upload['mime_type'] ?? '');

                return str_contains($mime, 'pdf')
                    || str_starts_with($mime, 'image/')
                    || str_contains($mime, 'wordprocessingml.document');
            });
    }
}
