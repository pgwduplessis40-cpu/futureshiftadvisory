<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Enums\EntrepreneurStage;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\MessageThread;
use App\Models\PlanAssessment;
use App\Models\RatingFramework;
use App\Models\ReadinessAssessment;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CalendarRoleFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_advisor_calendar_entry_redirects_to_the_advisor_workspace(): void
    {
        $advisor = $this->user(User::TYPE_ADVISOR);

        $this->actingAsMfa($advisor)
            ->get(route('calendar.index'))
            ->assertRedirect(route('advisor.calendar.index', absolute: false));
    }

    public function test_roles_without_calendar_records_receive_a_clear_empty_calendar(): void
    {
        foreach ([
            [User::TYPE_ENTREPRENEUR, 'Entrepreneur calendar'],
            [User::TYPE_ENTREPRENEUR_MENTOR, 'Mentor calendar'],
            [User::TYPE_BROKER, 'Broker calendar'],
            [User::TYPE_COACH, 'Coach calendar'],
        ] as [$userType, $title]) {
            $user = $this->user($userType);

            $this->actingAsMfa($user)
                ->get(route('calendar.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page): Assert => $page
                    ->component('calendar/Index')
                    ->where('title', $title)
                    ->where('events', [])
                    ->where('leavePeriods', [])
                    ->where('canManageLeavePeriods', false)
                );
        }
    }

    public function test_unknown_portal_role_receives_the_generic_empty_calendar(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => 'observer',
            'primary_role' => 'observer',
        ]);

        $this->actingAsMfa($user)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('calendar/Index')
                ->where('title', 'Calendar')
                ->where('events', [])
                ->where('canManageLeavePeriods', false)
            );
    }

    public function test_entrepreneur_and_mentor_calendars_show_activity_from_the_assigned_workspace(): void
    {
        $entrepreneur = $this->user(User::TYPE_ENTREPRENEUR);
        $mentor = $this->user(User::TYPE_ENTREPRENEUR_MENTOR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $mentor->getKey(),
            'name' => 'Calendar Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ASSESSMENT,
            'concept_summary' => 'A service business with evidence-backed growth plans.',
        ]);
        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'title' => 'Calendar founder plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_FINALISED,
            'current_phase' => 5,
            'created_by_user_id' => $entrepreneur->getKey(),
            'completed_at' => now()->subDay(),
            'living_plan_next_update_at' => now()->addWeek(),
        ]);
        $framework = RatingFramework::query()->create([
            'version' => 1,
            'status' => RatingFramework::STATUS_PUBLISHED,
            'production_ready' => true,
            'grade_bands' => RatingFramework::DEFAULT_GRADE_BANDS,
            'published_at' => now(),
            'published_by_user_id' => $mentor->getKey(),
            'created_by_user_id' => $mentor->getKey(),
        ]);
        $assessment = PlanAssessment::query()->create([
            'business_plan_id' => $plan->getKey(),
            'round' => 2,
            'rating_framework_id' => $framework->getKey(),
            'ai_scores' => [],
            'advisor_scores' => [],
            'mentor_notes' => [],
            'document_support' => [],
            'overall_grade' => 'strong',
            'finalised_at' => now()->subHours(2),
            'finalised_by_user_id' => $mentor->getKey(),
        ]);
        AdvisoryReadinessSignal::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'business_plan_id' => $plan->getKey(),
            'plan_assessment_id' => $assessment->getKey(),
            'score' => 88.5,
            'surfaced_at' => now()->subHour(),
            'advisor_notified_at' => now()->subHour(),
        ]);
        ReadinessAssessment::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'responses' => [],
            'score' => 82.5,
            'outcome' => ReadinessAssessment::OUTCOME_READY,
            'assessed_by_user_id' => $entrepreneur->getKey(),
            'assessed_at' => now()->subHours(3),
        ]);
        IdeaValidation::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'evaluated_by_user_id' => $entrepreneur->getKey(),
            'problem' => 'Founders need an evidence-backed planning path.',
            'target_customer' => 'Growing service businesses.',
            'solution' => 'A guided planning workspace.',
            'value_proposition' => 'Better decisions with fewer blind spots.',
            'demand_signal' => 'Customer interviews confirm the problem.',
            'revenue_model' => 'Monthly advisory subscription.',
            'ai_evaluation' => ['summary' => 'Ready for mentor review.'],
            'viability_alerts' => [],
            'evaluated_at' => now()->subHours(4),
            'advisor_gate_passed_at' => now()->subHours(3),
            'advisor_gate_passed_by_user_id' => $mentor->getKey(),
        ]);
        Document::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'category' => Document::CATEGORY_PLAN_ATTACHMENT,
            'original_filename' => 'market-evidence.pdf',
            'stored_path' => 'documents/market-evidence.pdf',
            'byte_size' => 12,
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', 'market evidence'),
            'uploaded_by_user_id' => $entrepreneur->getKey(),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
        MessageThread::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'created_by_user_id' => $entrepreneur->getKey(),
            'subject' => 'Plan review',
            'last_activity_at' => now()->subMinutes(30),
        ]);

        $expectedTitles = [
            'Profile created: Calendar Founder',
            'Business plan started',
            'Business plan completed',
            'Living plan update due',
            'Assessment round 2 finalised',
            'Advisory readiness signal',
            'Readiness assessment completed',
            'Idea validation evaluated',
            'Advisor gate passed',
            'market-evidence.pdf',
            'Message thread activity',
        ];

        $this->actingAsMfa($entrepreneur)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('title', 'Entrepreneur calendar')
                ->where('events', fn (mixed $events): bool => $this->calendarContainsTitles(collect($events)->all(), $expectedTitles)
                    && collect($events)->contains(fn (array $event): bool => $event['href'] === route('portal.entrepreneur.plan.show', absolute: false))
                    && collect($events)->contains(fn (array $event): bool => $event['href'] === route('portal.documents.show', Document::query()->firstOrFail(), absolute: false))));

        $this->actingAsMfa($mentor)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('title', 'Mentor calendar')
                ->where('events', fn (mixed $events): bool => $this->calendarContainsTitles(collect($events)->all(), $expectedTitles)
                    && collect($events)->every(fn (array $event): bool => $event['href'] === route('advisor.entrepreneurs.show', $profile, absolute: false))));
    }

    private function user(string $userType): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => $userType,
            'primary_role' => $userType,
        ]);
        $user->assignRole($userType);

        return $user;
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<int, string>  $titles
     */
    private function calendarContainsTitles(array $events, array $titles): bool
    {
        $eventTitles = collect($events)->pluck('title');

        return collect($titles)->every(fn (string $title): bool => $eventTitles->contains($title));
    }
}
