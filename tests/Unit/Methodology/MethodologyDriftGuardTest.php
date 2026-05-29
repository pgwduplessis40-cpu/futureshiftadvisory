<?php

declare(strict_types=1);

namespace Tests\Unit\Methodology;

use App\Services\Analysis\AnalysisRunner;
use App\Services\Dashboards\BusinessHealthRadarBuilder;
use App\Services\Dashboards\BusinessHealthSnapshotWriter;
use App\Services\Dashboards\PaymentStatusReport;
use App\Services\DataQuality\DataQualityInsufficientException;
use App\Services\DataQuality\DataQualityScore;
use App\Services\DataQuality\DataQualitySignal;
use App\Services\DataQuality\Gate;
use App\Services\DataQuality\QuestionnaireCompletenessResult;
use App\Services\Dd\AcquisitionPlanRequirements;
use App\Services\Dd\DataRoom;
use App\Services\Dd\DdAdviceReportGenerator;
use App\Services\Dd\DdDisclaimer;
use App\Services\Dd\DdOnboarding;
use App\Services\Dd\PlanBuilder;
use App\Services\Dd\PostAcquisition;
use App\Services\Dd\Workstreams\DdEvidenceAssembler;
use App\Services\Dd\Workstreams\DdNzCheckProvider;
use App\Services\Dd\Workstreams\DdWorkstreamModule;
use App\Services\Dd\Workstreams\DdWorkstreamRunner;
use App\Services\Entrepreneurs\AdvisorEntrepreneurCapacity;
use App\Services\Entrepreneurs\AdvisoryConversion;
use App\Services\Entrepreneurs\Benchmarking;
use App\Services\Entrepreneurs\Guidance;
use App\Services\Entrepreneurs\LivingPlan;
use App\Services\Entrepreneurs\PlanDocuments;
use App\Services\Integration\IntegrationHealthBander;
use App\Services\Panels\Coach\CoachPanel;
use App\Services\Payments\AuthorityCapture;
use App\Services\Payments\Gateway;
use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentWebhookVerifier;
use App\Services\Payments\ReceiptGenerator;
use App\Services\Payments\ScheduleBuilder;
use App\Services\Pv\DiscountRateResult;
use App\Services\Pv\IndustryWaccRefresher;
use App\Services\Pv\PvWaterfallReportChart;
use App\Services\Pv\ValuationMultipleProvider;
use App\Services\Pv\ValuationMultipleRefresher;
use App\Services\Reports\ReportComposer;
use App\Services\Wellbeing\WellbeingCheckinService;
use App\Support\Methodology\MethodologyRegistry;
use App\Support\Methodology\ProvidesMethodology;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use Tests\TestCase;

final class MethodologyDriftGuardTest extends TestCase
{
    private const ID_PATTERN = MethodologyRegistry::ID_PATTERN;

    private const DESIGNATED_NAMESPACE_ROOTS = [
        'App\\Services\\Analytics\\',
        'App\\Services\\Dashboards\\',
        'App\\Services\\DataQuality\\',
        'App\\Services\\Dd\\',
        'App\\Services\\Entrepreneurs\\',
        'App\\Services\\Fees\\',
        'App\\Services\\Panels\\Coach\\',
        'App\\Services\\Payments\\',
        'App\\Services\\Pv\\',
        'App\\Services\\Wellbeing\\',
    ];

    private const DESIGNATED_CLASSES = [
        AnalysisRunner::class,
        IntegrationHealthBander::class,
        ReportComposer::class,
    ];

    private const EXCLUDED_CLASSES = [
        BusinessHealthSnapshotWriter::class => 'Delegates radar row construction and only persists snapshots.',
        PaymentStatusReport::class => 'Summarises payment lifecycle state, not a formula owner.',
        DataQualityInsufficientException::class => 'Exception class.',
        DataQualityScore::class => 'DTO returned by the scorer.',
        DataQualitySignal::class => 'DTO returned by the scorer.',
        Gate::class => 'Policy gate around data quality, not a scoring method.',
        QuestionnaireCompletenessResult::class => 'DTO returned by the completeness calculator.',
        DataRoom::class => 'Data-room file workflow.',
        AcquisitionPlanRequirements::class => 'Plan requirement checklist for the DD portal workflow.',
        DdAdviceReportGenerator::class => 'Readiness orchestration before delegating to the report methodology.',
        DdDisclaimer::class => 'Static disclaimer copy.',
        DdOnboarding::class => 'DD workflow orchestration.',
        PlanBuilder::class => 'Builds plan text around DD artefacts.',
        PostAcquisition::class => 'Workflow migration into advisory after DD.',
        DdEvidenceAssembler::class => 'Evidence assembly helper.',
        DdNzCheckProvider::class => 'External check provider.',
        DdWorkstreamModule::class => 'Analysis module adapter.',
        DdWorkstreamRunner::class => 'Workstream runner/orchestrator.',
        AdvisorEntrepreneurCapacity::class => 'Capacity gate, not a methodology surface in this track.',
        AdvisoryConversion::class => 'Conversion workflow from entrepreneur to advisory client.',
        Benchmarking::class => 'Privacy-gated cohort report; excluded until a client-safe aggregate methodology is surfaced.',
        Guidance::class => 'AI guidance workflow; formula-like predictive score is not exposed by the methodology surface yet.',
        LivingPlan::class => 'Plan section workflow.',
        \App\Services\Entrepreneurs\PlanBuilder::class => 'Plan scaffolding workflow.',
        PlanDocuments::class => 'Document verification helper.',
        CoachPanel::class => 'Coach referral workflow orchestration.',
        AuthorityCapture::class => 'Payment-authority capture workflow.',
        Gateway::class => 'Gateway adapter.',
        PaymentAuthorityRequest::class => 'DTO.',
        PaymentAuthorityToken::class => 'DTO.',
        PaymentChargeRequest::class => 'DTO.',
        PaymentChargeResult::class => 'DTO.',
        PaymentGatewayException::class => 'Exception class.',
        PaymentWebhookVerifier::class => 'Security verification, not methodology disclosure.',
        ReceiptGenerator::class => 'Receipt rendering workflow.',
        ScheduleBuilder::class => 'Payment schedule persistence workflow.',
        DiscountRateResult::class => 'DTO returned by the discount-rate resolver.',
        IndustryWaccRefresher::class => 'Data refresh job.',
        PvWaterfallReportChart::class => 'Report chart renderer.',
        ValuationMultipleProvider::class => 'Multiple lookup provider, not a formula owner.',
        ValuationMultipleRefresher::class => 'Data refresh job.',
        WellbeingCheckinService::class => 'Check-in persistence workflow.',
    ];

    public function test_marked_classes_and_registry_entries_round_trip(): void
    {
        $registry = new MethodologyRegistry;
        $entries = $registry->all();

        foreach ($this->markedConcreteClasses() as $class) {
            $ids = $class::methodologyIds();

            $this->assertNotEmpty($ids, "Marked class [{$class}] must declare at least one methodology id.");
            $this->assertSame(array_values(array_unique($ids)), array_values($ids), "Marked class [{$class}] has duplicate methodology ids.");

            foreach ($ids as $id) {
                $this->assertIsString($id, "Marked class [{$class}] ids must be strings.");
                $this->assertMatchesRegularExpression(self::ID_PATTERN, $id, "Marked class [{$class}] id [{$id}] is malformed.");
                $this->assertArrayHasKey($id, $entries, "Marked class [{$class}] declares dangling id [{$id}].");
            }
        }

        foreach ($entries as $entry) {
            $owner = $entry->owningService;
            $reflection = new ReflectionClass($owner);

            $this->assertFalse($reflection->isEnum(), "Methodology [{$entry->id}] owner must not be an enum.");
            $this->assertFalse(str_starts_with($owner, 'App\\Models\\'), "Methodology [{$entry->id}] owner must not be a model.");
            $this->assertFalse(str_starts_with($owner, 'App\\Enums\\'), "Methodology [{$entry->id}] owner must not be an enum.");
            $this->assertTrue($reflection->implementsInterface(ProvidesMethodology::class), "Methodology [{$entry->id}] owner [{$owner}] is not marked.");
            $this->assertContains($entry->id, $owner::methodologyIds(), "Methodology [{$entry->id}] is not declared by owner [{$owner}].");
        }
    }

    public function test_designated_calculation_classes_are_marked_or_explicitly_excluded(): void
    {
        foreach (array_keys(self::EXCLUDED_CLASSES) as $class) {
            $this->assertTrue(class_exists($class), "Methodology scan exclusion [{$class}] no longer exists.");
        }

        $failures = [];

        foreach ($this->designatedConcreteClasses() as $class) {
            $reflection = new ReflectionClass($class);

            if ($reflection->implementsInterface(ProvidesMethodology::class)) {
                continue;
            }

            if (array_key_exists($class, self::EXCLUDED_CLASSES)) {
                continue;
            }

            $failures[] = $class;
        }

        $this->assertSame([], $failures, 'Designated calculation classes must be marked or explicitly excluded.');
    }

    public function test_referenced_methodology_ids_resolve_to_registry_entries(): void
    {
        $registry = new MethodologyRegistry;

        foreach ($this->referencedMethodologyIds() as $reference => $id) {
            $this->assertArrayHasKey($id, $registry->all(), "Methodology reference [{$reference}] points at unknown id [{$id}].");
        }
    }

    public function test_portal_and_export_sources_do_not_expose_methodology_ids(): void
    {
        $forbiddenReferences = [];

        foreach ($this->forbiddenDisclosureFiles() as $file) {
            $contents = file_get_contents($file->getPathname());

            if (is_string($contents) && str_contains($contents, 'methodology_id')) {
                $forbiddenReferences[] = $file->getPathname();
            }
        }

        $this->assertSame([], $forbiddenReferences, 'Portal/client/export sources must not expose internal methodology ids.');
        $this->assertMethodSourceDoesNotContainMethodologyId(BusinessHealthRadarBuilder::class, 'portalPayload');
        $this->assertMethodSourceDoesNotContainMethodologyId(BusinessHealthRadarBuilder::class, 'healthFindingsPayload');
    }

    /**
     * @return array<int, class-string>
     */
    private function markedConcreteClasses(): array
    {
        return array_values(array_filter(
            $this->appConcreteClasses(),
            static fn (string $class): bool => (new ReflectionClass($class))->implementsInterface(ProvidesMethodology::class),
        ));
    }

    /**
     * @return array<int, class-string>
     */
    private function designatedConcreteClasses(): array
    {
        $classes = array_filter(
            $this->appConcreteClasses(),
            static fn (string $class): bool => self::isDesignatedNamespaceClass($class),
        );

        return array_values(array_unique([
            ...$classes,
            ...self::DESIGNATED_CLASSES,
        ]));
    }

    private static function isDesignatedNamespaceClass(string $class): bool
    {
        foreach (self::DESIGNATED_NAMESPACE_ROOTS as $root) {
            if (str_starts_with($class, $root)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, class-string>
     */
    private function appConcreteClasses(): array
    {
        $classes = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            $class = $this->classFromAppFile($file);

            if ($class === null || ! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (! $reflection->isInstantiable()) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return class-string|null
     */
    private function classFromAppFile(SplFileInfo $file): ?string
    {
        $path = $file->getPathname();
        $appPath = app_path();

        if (! str_starts_with($path, $appPath)) {
            return null;
        }

        $relative = substr($path, strlen($appPath) + 1);
        $class = 'App\\'.str_replace(['/', '\\'], '\\', substr($relative, 0, -4));

        /** @var class-string $class */
        return $class;
    }

    /**
     * @return array<string, string>
     */
    private function referencedMethodologyIds(): array
    {
        $references = [];
        $patterns = [
            '/[\'"]methodology_id[\'"]\s*=>\s*[\'"](?P<id>[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*)[\'"]/',
            '/\bmethodology_id\s*:\s*[\'"](?P<id>[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*)[\'"]/',
        ];

        foreach ([app_path(), resource_path('js')] as $root) {
            foreach ($this->phpAndTypescriptFiles($root) as $file) {
                $contents = file_get_contents($file->getPathname());

                if (! is_string($contents)) {
                    continue;
                }

                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER);

                    foreach ($matches as $match) {
                        $references[$file->getPathname().':'.$match[0]] = $match['id'];
                    }
                }
            }
        }

        return $references;
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function forbiddenDisclosureFiles(): array
    {
        $roots = [
            app_path('Http/Controllers/Portal'),
            app_path('Http/Controllers/Public'),
            app_path('Services/Pdf'),
            app_path('Services/Portal'),
            app_path('Services/Pptx'),
            app_path('Services/Reports'),
            resource_path('js/pages/portal'),
        ];

        return collect($roots)
            ->filter(fn (string $root): bool => is_dir($root))
            ->flatMap(fn (string $root): array => $this->phpAndTypescriptFiles($root))
            ->values()
            ->all();
    }

    private function assertMethodSourceDoesNotContainMethodologyId(string $class, string $method): void
    {
        $reflection = new ReflectionMethod($class, $method);
        $source = file($reflection->getFileName());

        $this->assertIsArray($source);

        $methodSource = implode('', array_slice(
            $source,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));

        $this->assertStringNotContainsString('methodology_id', $methodSource, "{$class}::{$method} must remain client safe.");
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function phpFiles(string $root): array
    {
        return $this->filesWithExtensions($root, ['php']);
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function phpAndTypescriptFiles(string $root): array
    {
        return $this->filesWithExtensions($root, ['php', 'ts', 'tsx']);
    }

    /**
     * @param  array<int, string>  $extensions
     * @return array<int, SplFileInfo>
     */
    private function filesWithExtensions(string $root, array $extensions): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if (in_array($file->getExtension(), $extensions, true)) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
