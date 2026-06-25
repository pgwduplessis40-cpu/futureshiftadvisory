<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Consent;
use App\Models\PanelAgreement;
use App\Models\PanelMember;
use App\Models\Referral;
use App\Models\ReverseReferral;
use App\Models\User;
use App\Notifications\BrokerFspLapsedNotification;
use App\Notifications\PanelApplicationInformationRequestedNotification;
use App\Notifications\PanelApplicationResubmittedNotification;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Integration\Fsp\Contracts\FspClient;
use App\Services\Panels\PanelOnboarding;
use App\Services\Panels\ReferralConsentManager;
use App\Services\Panels\ReferralLifecycle;
use App\Services\Pdf\PdfRenderer;
use App\Services\Security\InviteIssuer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

final class BrokerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');

        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });
    }

    public function test_fsp_is_validated_at_broker_approval_and_stamped_on_agreement(): void
    {
        $advisor = $this->advisor();
        $broker = $this->broker('valid-broker@example.test');
        $onboarding = app(PanelOnboarding::class);

        $member = $onboarding->submitApplication($broker, PanelMember::TYPE_BROKER, [
            'trading_name' => 'Valid Broker',
            'fsp_number' => 'FSP100001',
        ]);

        $agreement = $onboarding->approve($member, $advisor);

        $this->assertSame(PanelMember::FSP_STATUS_CURRENT, $member->refresh()->fsp_status);
        $this->assertSame('FSP100001', $member->fsp_number);
        $this->assertNotNull($member->fsp_last_checked_at);
        $this->assertSame(PanelAgreement::STATUS_PENDING_SIGNATURE, $agreement->status);
        $this->assertSame('FSP100001', $agreement->terms['broker_clauses']['fsp_number']);
        $this->assertTrue($agreement->terms['broker_clauses']['lapse_auto_suspends_portal_access']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'panel.broker_fsp_checked',
            'subject_id' => $member->id,
        ]);

        $lapsed = $onboarding->submitApplication($this->broker('lapsed-broker@example.test'), PanelMember::TYPE_BROKER, [
            'trading_name' => 'Lapsed Broker',
            'fsp_number' => 'FSP999999',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Broker FSP registration must be current before approval.');

        $onboarding->approve($lapsed, $advisor);
    }

    public function test_periodic_fsp_reverification_suspends_lapsed_broker_and_alerts_advisors(): void
    {
        Notification::fake();

        $advisor = $this->advisor('fsp-alert-advisor@example.test');
        $brokerMember = $this->activeBroker('fsp-lapse-broker@example.test');
        $brokerMember->forceFill([
            'fsp_last_checked_at' => now()->subDays(31),
        ])->save();

        $this->app->instance(FspClient::class, new class implements FspClient
        {
            public function lookup(string $fspNumber): array
            {
                return [
                    'fsp_number' => $fspNumber,
                    'status' => 'lapsed',
                    'authorised_for_insurance' => false,
                    'source_badge' => 'test_lapse',
                ];
            }
        });

        $this
            ->artisan('panels:broker-fsp-reverify', ['--days' => 30])
            ->expectsOutput('Checked 1 broker FSP registrations: 0 current, 1 suspended.')
            ->assertSuccessful();

        $brokerMember->refresh();
        $this->assertSame(PanelMember::STATUS_SUSPENDED, $brokerMember->status);
        $this->assertSame(PanelMember::FSP_STATUS_LAPSED, $brokerMember->fsp_status);
        $this->assertNotNull($brokerMember->suspended_at);
        Notification::assertSentTo($advisor, BrokerFspLapsedNotification::class);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'panel.broker_fsp_lapsed',
            'subject_id' => $brokerMember->id,
        ]);
    }

    public function test_broker_referral_stages_follow_insurance_lifecycle(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor();
        $brokerMember = $this->activeBroker('stage-broker@example.test');
        $lifecycle = app(ReferralLifecycle::class);
        $referral = $lifecycle->create($client, $brokerMember, $advisor, [
            'need' => 'Commercial cover review',
        ]);
        $referral = $this->prepareToSend($referral, $advisor, $client);

        try {
            $lifecycle->transition($referral, Referral::STAGE_ACCEPTED, $advisor);
            $this->fail('Broker referrals should not allow the shared accepted stage.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Referral stage transition is not allowed.', $e->getMessage());
        }

        $referral = $lifecycle->transition($referral, Referral::STAGE_BROKER_REFERRAL_SENT, $advisor);
        $referral = $lifecycle->transition($referral, Referral::STAGE_BROKER_ACKNOWLEDGED, $brokerMember->user);
        $referral = $lifecycle->transition($referral, Referral::STAGE_BROKER_QUOTE_REQUESTED, $brokerMember->user);
        $referral = $lifecycle->transition($referral, Referral::STAGE_BROKER_COVER_PLACED, $brokerMember->user);

        $this->assertSame(Referral::STAGE_BROKER_COVER_PLACED, $referral->stage);
        $this->assertNotNull($referral->sent_at);
        $this->assertNotNull($referral->closed_at);
        $this->assertContains(Referral::STAGE_BROKER_DECLINED, Referral::brokerStages());
        $this->assertContains(Referral::STAGE_BROKER_NO_RESPONSE, Referral::brokerStages());
    }

    public function test_broker_dashboard_surfaces_panel_and_referral_activity(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor();
        $brokerMember = $this->activeBroker('dashboard-broker@example.test');
        $lifecycle = app(ReferralLifecycle::class);
        $referral = $lifecycle->create($client, $brokerMember, $advisor, [
            'need' => 'Commercial cover review',
        ]);
        $referral = $this->prepareToSend($referral, $advisor, $client);
        $referral = $lifecycle->transition($referral, Referral::STAGE_BROKER_REFERRAL_SENT, $advisor);
        $lifecycle->message($referral, $advisor, 'Client is ready for a broker introduction.');

        ReverseReferral::query()->create([
            'panel_member_id' => $brokerMember->getKey(),
            'target_type' => ReverseReferral::TARGET_PROSPECT,
            'name' => 'Reverse Lead',
            'email' => 'reverse@example.test',
            'company' => 'Reverse Co',
            'submitted_at' => now(),
        ]);

        $this->actingAsMfa($brokerMember->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('broker/Dashboard')
                ->where('dashboard.panel.company', 'Active Broker')
                ->where('dashboard.panel.fspStatus', PanelMember::FSP_STATUS_CURRENT)
                ->where('dashboard.summary.totalReferrals', 1)
                ->where('dashboard.summary.activeReferrals', 1)
                ->where('dashboard.summary.reverseReferrals', 1)
                ->where('dashboard.referrals.0.clientName', 'Broker Stage Client Limited')
                ->where('dashboard.referrals.0.stage', Referral::STAGE_BROKER_REFERRAL_SENT)
                ->where('dashboard.messages.0.body', 'Client is ready for a broker introduction.')
                ->where('dashboard.reverseReferrals.0.company', 'Reverse Co')
                ->where('dashboard.referrals.0.availableActions.0.stage', Referral::STAGE_BROKER_ACKNOWLEDGED)
                ->where('dashboard.referrals.0.stageUpdateUrl', route('broker.referrals.stage', $referral, absolute: false))
            );
    }

    public function test_broker_can_apply_from_portal_and_sign_panel_agreement(): void
    {
        $advisor = $this->advisor('broker-self-service-approver@example.test');
        $broker = $this->broker('broker-self-service@example.test');

        $this->actingAsMfa($broker)
            ->post(route('panel.application.store'), [
                'company' => 'Self Service Brokers Limited',
                'fsp_number' => 'FSP100001',
                'regions' => 'Auckland, Wellington',
                'specialties' => 'Insurance, Risk',
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $member = PanelMember::query()
            ->where('user_id', $broker->getKey())
            ->where('panel_type', PanelMember::TYPE_BROKER)
            ->firstOrFail();

        $this->assertSame(PanelMember::STATUS_APPLICATION_PENDING, $member->status);
        $this->assertSame('Self Service Brokers Limited', $member->application['company']);
        $this->assertSame(['Auckland', 'Wellington'], $member->application['regions']);

        $agreement = app(PanelOnboarding::class)->approve($member, $advisor);

        $this->actingAsMfa($broker)
            ->post(route('panel.agreements.sign', $agreement))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame(PanelAgreement::STATUS_SIGNED, $agreement->refresh()->status);
        $this->assertSame(PanelMember::STATUS_ACTIVE, $member->refresh()->status);
        $this->assertNotNull($agreement->pdf_path);

        $downloadUrl = route('panel.agreements.download', $agreement, absolute: false);
        $this->actingAsMfa($broker)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('broker/Dashboard')
                ->where('dashboard.agreement.downloadUrl', $downloadUrl));

        $download = $this->actingAsMfa($broker)
            ->get($downloadUrl)
            ->assertOk();
        $this->assertStringContainsString('%PDF-1.4', $download->streamedContent());

        $this->assertDatabaseHas('audit_events', [
            'action' => 'panel.agreement_signed',
            'subject_id' => $agreement->id,
        ]);
    }

    public function test_broker_application_reuses_invited_member_for_same_email(): void
    {
        $email = 'broker-reuses-invite@example.test';
        $issued = app(InviteIssuer::class)->issue(
            email: $email,
            targetUserType: User::TYPE_BROKER,
            targetRole: User::TYPE_BROKER,
        );
        $broker = $this->broker($email);
        $member = PanelMember::query()->create([
            'invite_token_id' => $issued->invite->getKey(),
            'panel_type' => PanelMember::TYPE_BROKER,
            'status' => PanelMember::STATUS_INVITED,
            'application' => [
                'company' => 'Cornerstone Connect',
                'broker_name' => 'Rinus Janse van Rensburg',
                'industry' => 'Life insurance',
            ],
        ]);

        $this->actingAsMfa($broker)
            ->post(route('panel.application.store'), [
                'company' => 'Cornerstone Connect Limited',
                'fsp_number' => 'FSP100001',
                'regions' => 'Hamilton',
                'specialties' => 'Business insurance',
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $member->refresh();
        $issued->invite->refresh();

        $this->assertDatabaseCount('panel_members', 1);
        $this->assertSame((string) $broker->getKey(), (string) $member->user_id);
        $this->assertSame(PanelMember::STATUS_APPLICATION_PENDING, $member->status);
        $this->assertSame('Cornerstone Connect Limited', $member->application['company']);
        $this->assertSame(['Business insurance'], $member->application['specialties']);
        $this->assertSame((string) $broker->getKey(), (string) $issued->invite->accepted_by_user_id);
        $this->assertNotNull($issued->invite->accepted_at);
    }

    public function test_broker_can_update_own_referral_stage_from_dashboard_action(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor();
        $brokerMember = $this->activeBroker('dashboard-action-broker@example.test');
        $lifecycle = app(ReferralLifecycle::class);
        $referral = $lifecycle->create($client, $brokerMember, $advisor, [
            'need' => 'Commercial cover review',
        ]);
        $referral = $this->prepareToSend($referral, $advisor, $client);
        $referral = $lifecycle->transition($referral, Referral::STAGE_BROKER_REFERRAL_SENT, $advisor);
        $referral->forceFill(['stage' => Referral::STAGE_SENT])->save();
        $referral->refresh();

        $this->actingAsMfa($brokerMember->user)
            ->patch(route('broker.referrals.stage', $referral), [
                'stage' => Referral::STAGE_BROKER_ACKNOWLEDGED,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('referrals', [
            'id' => $referral->id,
            'stage' => Referral::STAGE_BROKER_ACKNOWLEDGED,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'referral.stage_changed',
            'subject_id' => $referral->id,
        ]);
    }

    public function test_broker_can_update_own_profile_details(): void
    {
        $brokerMember = $this->activeBroker('profile-update-broker@example.test');
        $this->assertSame(PanelMember::FSP_STATUS_CURRENT, $brokerMember->fsp_status);

        $this->actingAsMfa($brokerMember->user)
            ->patch(route('panel.application.update'), [
                'company' => 'Updated Broker Limited',
                'fsp_number' => 'fsp200002',
                'regions' => 'Christchurch, Dunedin',
                'specialties' => 'Life insurance, Risk review',
            ])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('status', 'panel-profile-updated');

        $brokerMember->refresh();

        $this->assertSame('Updated Broker Limited', $brokerMember->application['company']);
        $this->assertSame('FSP200002', $brokerMember->application['fsp_number']);
        $this->assertSame('FSP200002', $brokerMember->fsp_number);
        $this->assertSame(['Christchurch', 'Dunedin'], $brokerMember->application['regions']);
        $this->assertSame(['Life insurance', 'Risk review'], $brokerMember->application['specialties']);
        $this->assertSame(PanelMember::FSP_STATUS_UNKNOWN, $brokerMember->fsp_status);
        $this->assertNull($brokerMember->fsp_last_checked_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'panel.profile_updated',
            'subject_id' => $brokerMember->id,
        ]);

        $this->actingAsMfa($brokerMember->user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('broker/Dashboard')
                ->where('dashboard.panel.company', 'Updated Broker Limited')
                ->where('dashboard.panel.profileUpdateUrl', route('panel.application.update', absolute: false))
                ->where('dashboard.panel.fspNumber', 'FSP200002')
                ->where('dashboard.panel.fspStatus', PanelMember::FSP_STATUS_UNKNOWN)
                ->where('dashboard.panel.regions.0', 'Christchurch')
                ->where('dashboard.panel.specialties.1', 'Risk review'));
    }

    public function test_broker_profile_update_after_information_request_resubmits_application(): void
    {
        Notification::fake();

        $advisor = $this->advisor('resubmission-advisor@example.test');
        $broker = $this->broker('resubmitting-broker@example.test');
        $member = app(PanelOnboarding::class)->submitApplication($broker, PanelMember::TYPE_BROKER, [
            'company' => 'Resubmitting Brokers Limited',
            'fsp_number' => 'FSP100001',
            'regions' => ['Hamilton'],
            'specialties' => ['Insurance'],
        ]);

        app(PanelOnboarding::class)->requestMoreInformation(
            $member,
            $advisor,
            'Please confirm the current FSP number before approval.',
        );

        Notification::assertSentTo($broker, PanelApplicationInformationRequestedNotification::class);

        $this->actingAsMfa($broker)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('broker/Dashboard')
                ->where('dashboard.panel.status', PanelMember::STATUS_INFORMATION_REQUESTED)
                ->where('dashboard.panel.review.reason', 'Please confirm the current FSP number before approval.'));

        $this->actingAsMfa($broker)
            ->patch(route('panel.application.update'), [
                'company' => 'Resubmitting Brokers Limited',
                'fsp_number' => 'fsp200002',
                'regions' => 'Hamilton, Tauranga',
                'specialties' => 'Insurance, Kiwisaver',
            ])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('status', 'panel-application-resubmitted');

        $member->refresh();

        $this->assertSame(PanelMember::STATUS_APPLICATION_PENDING, $member->status);
        $this->assertSame('resubmitted', $member->application['review']['decision']);
        $this->assertSame('Please confirm the current FSP number before approval.', $member->application['review']['previous_reason']);
        $this->assertSame('FSP200002', $member->fsp_number);
        $this->assertSame(PanelMember::FSP_STATUS_UNKNOWN, $member->fsp_status);
        Notification::assertSentTo($advisor, PanelApplicationResubmittedNotification::class);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'panel.application_resubmitted',
            'subject_id' => $member->id,
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('panelOperations.approvals.summary.broker', 1)
                ->where('panelOperations.approvals.items.0.id', $member->id)
                ->where('panelOperations.approvals.items.0.status', PanelMember::STATUS_APPLICATION_PENDING));
    }

    private function activeBroker(string $email): PanelMember
    {
        $broker = $this->broker($email);
        $onboarding = app(PanelOnboarding::class);
        $member = $onboarding->submitApplication($broker, PanelMember::TYPE_BROKER, [
            'trading_name' => 'Active Broker',
            'fsp_number' => 'FSP100001',
        ]);
        $agreement = $onboarding->approve($member, $this->advisor('approver-'.$email));
        $onboarding->signAgreement($agreement, $broker);

        return $member->refresh()->load('user');
    }

    private function broker(string $email): User
    {
        $broker = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_BROKER,
            'primary_role' => User::TYPE_BROKER,
        ]);
        $broker->assignRole(User::TYPE_BROKER);

        return $broker;
    }

    private function advisor(string $email = 'broker-portal-advisor@example.test'): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientWithAdvisor(): array
    {
        $advisor = $this->advisor('broker-stage-advisor@example.test');
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Broker Stage Client Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client];
    }

    private function prepareToSend(Referral $referral, User $advisor, Client $client): Referral
    {
        $conflict = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::BROKER_REFERRAL,
            existingRelationship: false,
        );
        $consent = app(ReferralConsentManager::class)->grant($client, $advisor, Consent::TYPE_INSURANCE_REFERRAL);

        return app(ReferralConsentManager::class)->prepareForSending($referral, $advisor, $conflict, $consent);
    }
}
