<?php

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\LearningUpdate;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Learning\ServiceActivationLearningAggregator;
use App\Support\RequestContext;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class ServiceActivationLearningAggregatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_governed_candidate_only_for_a_material_funnel_regression(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');
        app(RequestContext::class)->apply('system', []);

        $package = $this->package();

        foreach (range(1, 10) as $index) {
            $this->activation(
                $this->client(),
                $package,
                now()->subDays(42),
                selected: true,
            );
        }

        foreach (range(1, 10) as $index) {
            $this->activation(
                $this->client(),
                $package,
                now()->subDays(14),
                selected: $index <= 2,
            );
        }

        $created = app(ServiceActivationLearningAggregator::class)->run(now());

        $this->assertSame(1, $created);
        $candidate = LearningUpdate::query()->firstOrFail();
        $this->assertSame('service_activation_funnel', data_get($candidate->source, 'type'));
        $this->assertSame('package_selection', data_get($candidate->source, 'stage'));
        $this->assertSame(10, data_get($candidate->evidence, 'current.requests'));
        $this->assertSame(2, data_get($candidate->evidence, 'current.package_selected'));
        $this->assertEqualsWithDelta(0.8, (float) data_get($candidate->evidence, 'rate_decline'), 0.0001);
        $this->assertSame(LearningUpdate::STATUS_DETECTED, $candidate->status);

        $this->assertSame(0, app(ServiceActivationLearningAggregator::class)->run(now()));
        $this->assertSame(0, app(ServiceActivationLearningAggregator::class)->run(now()->addDay()));
        $this->assertSame(1, LearningUpdate::query()->count());
    }

    private function client(): Client
    {
        $user = User::factory()->create();

        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Activation Funnel Test Limited',
            'entity_type' => 'NZ Limited Company',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
        ]);
    }

    private function package(): ServiceRatePackage
    {
        return ServiceRatePackage::query()->create([
            'service_type' => ServiceActivation::SERVICE_DUE_DILIGENCE,
            'package_name' => 'Funnel test package',
            'client_label' => 'Explore buying a business',
            'billing_model' => 'fixed_fee',
            'fixed_fee' => 1000,
            'currency' => 'NZD',
            'scope_description' => 'Test package for funnel aggregation.',
            'is_active' => true,
            'effective_from' => now()->subYear(),
        ]);
    }

    private function activation(
        Client $client,
        ServiceRatePackage $package,
        DateTimeInterface $createdAt,
        bool $selected,
    ): void {
        $activation = ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'service_rate_package_id' => $selected ? $package->getKey() : null,
            'service_type' => ServiceActivation::SERVICE_DUE_DILIGENCE,
            'client_label' => 'Explore buying a business',
            'status' => $selected ? ServiceActivation::STATUS_PACKAGE_SELECTED : ServiceActivation::STATUS_REQUESTED,
            'payment_status' => ServiceActivation::PAYMENT_NOT_REQUIRED,
        ]);
        $activation->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();
    }
}
