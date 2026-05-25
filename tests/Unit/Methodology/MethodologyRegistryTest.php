<?php

declare(strict_types=1);

namespace Tests\Unit\Methodology;

use App\Services\Dashboards\ClientEngagementScorer;
use App\Support\Methodology\MethodologyEntry;
use App\Support\Methodology\MethodologyRegistry;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tests\TestCase;

final class MethodologyRegistryTest extends TestCase
{
    public function test_loads_seed_entries_and_returns_typed_entry(): void
    {
        $registry = new MethodologyRegistry;

        $entries = $registry->all();
        $entry = $registry->get('engagement.score');

        $this->assertArrayHasKey('engagement.score', $entries);
        $this->assertInstanceOf(MethodologyEntry::class, $entry);
        $this->assertSame('Client Engagement Score', $entry->name);
        $this->assertSame(ClientEngagementScorer::class, $entry->owningService);
        $this->assertArrayHasKey('engagement.score', $registry->byArea('engagement'));
        $this->assertArrayHasKey('engagement.score', $registry->byFeature('advisor.dashboard.engagement'));
        $this->assertSame('Advisor dashboard engagement panel', $registry->featureLabel('advisor.dashboard.engagement'));
    }

    public function test_default_catalogue_entries_have_marked_owners_and_registered_features(): void
    {
        $registry = new MethodologyRegistry;

        foreach ($registry->all() as $id => $entry) {
            $owner = $entry->owningService;

            $this->assertSame($id, $entry->id);
            $this->assertNotSame('', trim($entry->area), "Entry [{$id}] has an area.");
            $this->assertNotSame('', trim($entry->name), "Entry [{$id}] has a name.");
            $this->assertNotSame('', trim($entry->summary), "Entry [{$id}] has a summary.");
            $this->assertNotSame('', trim($entry->formula), "Entry [{$id}] has a formula.");
            $this->assertNotEmpty($entry->inputs, "Entry [{$id}] lists inputs.");
            $this->assertNotEmpty($entry->whereUsed, "Entry [{$id}] lists where it is used.");
            $this->assertTrue(class_exists($owner), "Owner [{$owner}] exists for [{$id}].");
            $this->assertTrue(is_subclass_of($owner, ProvidesMethodology::class), "Owner [{$owner}] is marked for [{$id}].");
            $this->assertContains($entry->id, $owner::methodologyIds(), "Owner [{$owner}] declares [{$id}].");

            foreach ($entry->whereUsed as $featureKey) {
                $this->assertNotSame('', $registry->featureLabel($featureKey));
            }
        }
    }

    public function test_default_catalogue_config_refs_resolve_to_live_config_values(): void
    {
        $registry = new MethodologyRegistry;

        foreach ($registry->all() as $entry) {
            $parameters = $registry->resolvedParameters($entry);

            foreach ($entry->configRefs as $configRef) {
                $this->assertArrayHasKey($configRef, $parameters, "Config ref [{$configRef}] resolves.");
            }
        }
    }

    public function test_resolved_parameters_returns_live_config_values(): void
    {
        Config::set('dashboards.engagement.weights.questionnaire_pct', 0.44);

        $parameters = (new MethodologyRegistry)->resolvedParameters('engagement.score');

        $this->assertSame(0.44, $parameters['dashboards.engagement.weights']['questionnaire_pct']);
        $this->assertSame(75, $parameters['dashboards.engagement.thresholds']['green']);
        $this->assertSame(30, $parameters['dashboards.engagement.comms_decay_days']);
    }

    public function test_duplicate_ids_fail_registry_loading(): void
    {
        Config::set('methodologies.entries', [
            $this->validEntry('demo.method'),
            $this->validEntry('demo.method'),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate methodology id [demo.method].');

        (new MethodologyRegistry)->all();
    }

    public function test_malformed_entries_fail_registry_loading(): void
    {
        Config::set('methodologies.entries', [
            $this->validEntry('Demo.Method'),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a lowercase dotted slug');

        (new MethodologyRegistry)->all();
    }

    public function test_resolved_parameters_rejects_non_allowlisted_config_ref(): void
    {
        Config::set('methodologies.entries', [
            $this->validEntry('demo.method', ['mail.from.address']),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not allowlisted');

        (new MethodologyRegistry)->resolvedParameters('demo.method');
    }

    public function test_resolved_parameters_rejects_sensitive_config_ref(): void
    {
        Config::set('methodologies.entries', [
            $this->validEntry('demo.method', ['services.stripe.secret']),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('matches a sensitive pattern');

        (new MethodologyRegistry)->resolvedParameters('demo.method');
    }

    public function test_resolved_parameters_rejects_missing_allowlisted_config_ref(): void
    {
        Config::set('methodologies.entries', [
            $this->validEntry('demo.method', ['dashboards.engagement.missing_threshold']),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        (new MethodologyRegistry)->resolvedParameters('demo.method');
    }

    /**
     * @param  array<int, string>  $configRefs
     * @return array<string, mixed>
     */
    private function validEntry(string $id, array $configRefs = []): array
    {
        return [
            'id' => $id,
            'area' => 'Demo',
            'name' => 'Demo method',
            'summary' => 'A demo methodology entry.',
            'formula' => 'Demo formula.',
            'inputs' => ['Demo input'],
            'config_refs' => $configRefs,
            'where_used' => ['advisor.dashboard.engagement'],
            'sources' => [],
            'owning_service' => ClientEngagementScorer::class,
            'version' => 'test',
            'internal_only' => true,
        ];
    }
}
