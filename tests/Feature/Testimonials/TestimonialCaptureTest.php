<?php

declare(strict_types=1);

namespace Tests\Feature\Testimonials;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Testimonials\TestimonialCapture;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TestimonialCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_nps_trigger_only_creates_request_for_scores_of_eight_or_above(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $client = $this->client('Promoter Candidate Limited', $advisor);
        $service = app(TestimonialCapture::class);

        $this->assertNull($service->requestFromNps($client, 7, $advisor));
        $this->assertSame(0, Testimonial::query()->count());

        $testimonial = $service->requestFromNps($client, 8, $advisor);

        $this->assertInstanceOf(Testimonial::class, $testimonial);
        $this->assertSame(Testimonial::STATUS_REQUESTED, $testimonial->status);
        $this->assertSame(8, $testimonial->source_score);
        $this->assertDatabaseHas('audit_events', ['action' => 'testimonial.requested']);
    }

    public function test_named_marketing_consent_adds_testimonial_to_library(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $client = $this->client('Named Advocate Limited', $advisor);
        $service = app(TestimonialCapture::class);
        $testimonial = $service->requestFromNps($client, 9, $advisor);

        $captured = $service->captureConsent(
            testimonial: $testimonial,
            marketingConsent: true,
            displayMode: Testimonial::DISPLAY_NAMED,
            quote: 'Future Shift made the decisions clearer.',
            submitter: $advisor,
            displayName: 'Taylor from Named Advocate',
        );

        $this->assertSame(Testimonial::STATUS_CONSENTED, $captured->status);
        $this->assertTrue($captured->marketing_consent);
        $this->assertSame('Taylor from Named Advocate', $captured->display_name);
        $this->assertSame(1, $service->library(includeAnonymous: false)->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'testimonial.consent_captured']);
    }

    public function test_anonymous_consent_is_kept_without_display_name_and_library_can_filter_it(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $client = $this->client('Anonymous Advocate Limited', $advisor);
        $service = app(TestimonialCapture::class);
        $testimonial = $service->requestFromNps($client, 10, $advisor);

        $captured = $service->captureConsent(
            testimonial: $testimonial,
            marketingConsent: true,
            displayMode: Testimonial::DISPLAY_ANONYMOUS,
            quote: 'The process was calm and evidence-led.',
            submitter: $advisor,
        );

        $this->assertNull($captured->display_name);
        $this->assertSame(1, $service->library()->count());
        $this->assertSame(0, $service->library(includeAnonymous: false)->count());
    }

    public function test_declined_marketing_consent_is_excluded_from_library(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $client = $this->client('Declined Advocate Limited', $advisor);
        $service = app(TestimonialCapture::class);
        $testimonial = $service->requestFromNps($client, 9, $advisor);

        $declined = $service->captureConsent(
            testimonial: $testimonial,
            marketingConsent: false,
            displayMode: Testimonial::DISPLAY_ANONYMOUS,
            quote: null,
            submitter: $advisor,
        );

        $this->assertSame(Testimonial::STATUS_DECLINED, $declined->status);
        $this->assertFalse($declined->marketing_consent);
        $this->assertSame(0, $service->library()->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'testimonial.declined']);
    }

    public function test_advisor_routes_capture_and_display_testimonial_library(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $client = $this->client('Route Advocate Limited', $advisor);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.testimonials.nps', $client), [
                'score' => 9,
            ])
            ->assertRedirect();

        $testimonial = Testimonial::query()->firstOrFail();

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.testimonials.capture', $testimonial), [
                'marketing_consent' => true,
                'display_mode' => Testimonial::DISPLAY_NAMED,
                'display_name' => 'Route Advocate',
                'quote' => 'A practical advisory experience.',
            ])
            ->assertRedirect();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.testimonials.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/testimonials/Index')
                ->has('testimonials', 1)
                ->where('testimonials.0.display_name', 'Route Advocate')
                ->where('testimonials.0.source_score', 9),
            );
    }

    private function advisor(): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $user->assignRole(User::TYPE_ADVISOR);

        return $user;
    }

    private function client(string $name, User $advisor): Client
    {
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [],
        ]);

        return $client;
    }
}
