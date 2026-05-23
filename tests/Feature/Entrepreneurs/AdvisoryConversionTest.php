<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ConflictDeclaration;
use App\Models\DdEngagement;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\Entrepreneurs\AdvisorEntrepreneurCapacity;
use App\Services\Entrepreneurs\AdvisoryConversion;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AdvisoryConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_conversion_prepopulates_standard_advisory_client_from_entrepreneur_data(): void
    {
        [$advisor, $profile, $plan] = $this->entrepreneurPlan('convert-founder@example.test');

        $client = app(AdvisoryConversion::class)->convert($profile, $advisor, $plan);

        $this->assertSame(EngagementType::STANDARD_ADVISORY, $client->engagement_type);
        $this->assertSame($profile->name, $client->legal_name);
        $this->assertSame($profile->user_id, $client->primary_contact_user_id);
        $this->assertSame('entrepreneur', $client->registry_sources['source']);
        $this->assertSame($profile->id, $client->registry_sources['entrepreneur_profile_id']);
        $this->assertSame($plan->id, $client->registry_sources['business_plan_id']);
        $this->assertSame('Retail', data_get($client->registry_sources, 'founding_advisory_payload.industry'));
        $this->assertSame($client->id, $plan->refresh()->client_id);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
        ]);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->id,
            'user_id' => $profile->user_id,
            'role' => 'primary_contact',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.advisory_converted',
            'subject_id' => $client->id,
        ]);
    }

    public function test_capacity_counts_all_active_entrepreneur_stages_and_blocks_at_limit(): void
    {
        $advisor = $this->advisor('capacity-wo92@example.test');
        $this->profiles($advisor, 24, EntrepreneurStage::BUILDING_PHASE_3);

        $summary = app(AdvisorEntrepreneurCapacity::class)->summary($advisor);

        $this->assertSame(24, $summary['active_count']);
        $this->assertTrue($summary['warning']);
        $this->assertFalse($summary['blocked']);

        $this->profiles($advisor, 6, EntrepreneurStage::ADVISORY_READY, 24);
        $summary = app(AdvisorEntrepreneurCapacity::class)->summary($advisor);

        $this->assertSame(30, $summary['active_count']);
        $this->assertTrue($summary['blocked']);

        $this->expectException(ValidationException::class);
        app(AdvisorEntrepreneurCapacity::class)->ensureCanAdd($advisor);
    }

    public function test_dd_built_plan_hands_off_to_new_advisory_client(): void
    {
        [$advisor, $plan] = $this->ddPlan();

        $client = app(AdvisoryConversion::class)->handoffDdPlan($plan, $advisor);

        $this->assertSame(EngagementType::STANDARD_ADVISORY, $client->engagement_type);
        $this->assertSame('Sourced from DD Business Plan', $client->registry_sources['source_label']);
        $this->assertSame($plan->dd_engagement_id, $client->registry_sources['dd_engagement_id']);
        $this->assertSame($plan->id, $client->registry_sources['business_plan_id']);
        $this->assertSame($client->id, $plan->refresh()->client_id);
        $this->assertSame(BusinessPlan::STATUS_FOUNDING, $plan->status);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.dd_plan_handoff_converted',
            'subject_id' => $client->id,
        ]);
    }

    /**
     * @return array{0: User, 1: EntrepreneurProfile, 2: BusinessPlan}
     */
    private function entrepreneurPlan(string $email): array
    {
        $advisor = $this->advisor('advisor-'.$email);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Converted Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ADVISORY_READY,
            'concept_summary' => 'Founder has validated retail concept.',
        ]);
        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'title' => 'Business plan: '.$profile->name,
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_FINALISED,
            'current_phase' => 5,
            'founding_advisory_payload' => [
                'industry' => 'Retail',
                'validated_customer' => 'Regional retail operators',
            ],
            'created_by_user_id' => $advisor->id,
            'completed_at' => now(),
        ]);

        return [$advisor, $profile->refresh()->load('user', 'advisoryReadinessSignals'), $plan];
    }

    /**
     * @return array{0: User, 1: BusinessPlan}
     */
    private function ddPlan(): array
    {
        $advisor = $this->advisor('dd-plan-handoff@example.test');
        $buyer = Client::query()->create([
            'engagement_type' => EngagementType::DUE_DILIGENCE,
            'legal_name' => 'Buyer Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->id,
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $buyer->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::DUE_DILIGENCE->value],
        ]);
        $conflict = ConflictDeclaration::query()->create([
            'client_id' => $buyer->id,
            'advisor_id' => $advisor->id,
            'declaration' => ['conflict' => false],
            'declared_at' => now(),
        ]);
        $engagement = DdEngagement::query()->create([
            'client_id' => $buyer->id,
            'target_name' => 'Target Retail Limited',
            'target_details' => [
                'nzbn' => '9429000000000',
                'industry' => 'Retail',
            ],
            'status' => DdEngagement::STATUS_ACQUISITION_PROCEEDING,
            'conflict_declaration_id' => $conflict->id,
            'created_by_user_id' => $advisor->id,
            'disclaimer_acknowledged_at' => now(),
        ]);
        $plan = BusinessPlan::query()->create([
            'client_id' => $buyer->id,
            'dd_engagement_id' => $engagement->id,
            'title' => 'Acquisition plan: '.$engagement->target_name,
            'source_type' => BusinessPlan::SOURCE_DUE_DILIGENCE,
            'status' => BusinessPlan::STATUS_FOUNDING,
            'current_phase' => 5,
            'founding_advisory_payload' => [
                'business_plan_id' => 'fixture',
                'industry' => 'Retail',
                'phases' => [],
            ],
            'created_by_user_id' => $advisor->id,
            'completed_at' => now(),
        ]);

        return [$advisor, $plan->refresh()->load('ddEngagement.client')];
    }

    private function advisor(string $email): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function profiles(User $advisor, int $count, EntrepreneurStage $stage, int $offset = 0): void
    {
        for ($i = 0; $i < $count; $i++) {
            EntrepreneurProfile::query()->create([
                'assigned_advisor_id' => $advisor->id,
                'name' => 'Capacity Founder '.($offset + $i),
                'email' => 'capacity-'.($offset + $i).'@example.test',
                'stage' => $stage,
            ]);
        }
    }
}
