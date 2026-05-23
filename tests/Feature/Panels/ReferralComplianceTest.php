<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\CoachSpecialisation;
use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Consent;
use App\Models\PanelMember;
use App\Models\Referral;
use App\Models\User;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Panels\Coach\CoachPanel;
use App\Services\Panels\PanelOnboarding;
use App\Services\Panels\ReferralConsentManager;
use App\Services\Panels\ReferralLifecycle;
use App\Services\Pdf\PdfRenderer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class ReferralComplianceTest extends TestCase
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

    public function test_broker_referral_cannot_be_sent_without_fresh_conflict_and_active_consent(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor('compliance-broker-advisor@example.test');
        $broker = $this->activeBroker('compliance-broker@example.test');
        $lifecycle = app(ReferralLifecycle::class);
        $referral = $lifecycle->create($client, $broker, $advisor);

        try {
            $lifecycle->transition($referral, Referral::STAGE_BROKER_REFERRAL_SENT, $advisor);
            $this->fail('Broker referral should require conflict declaration before send.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('A fresh conflict declaration is required before sending this referral.', $e->getMessage());
        }

        $conflict = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::BROKER_REFERRAL,
            existingRelationship: false,
        );

        try {
            app(ReferralConsentManager::class)->prepareForSending(
                referral: $referral,
                advisor: $advisor,
                conflict: $conflict,
                consent: Consent::query()->make([
                    'client_id' => $client->id,
                    'type' => Consent::TYPE_INSURANCE_REFERRAL,
                    'election' => Consent::ELECTION_OPT_OUT,
                ]),
            );
            $this->fail('Broker referral should require active consent before send.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Active client consent is required before sending this referral.', $e->getMessage());
        }

        $consent = app(ReferralConsentManager::class)->grant($client, $advisor, Consent::TYPE_INSURANCE_REFERRAL);
        $referral = app(ReferralConsentManager::class)->prepareForSending($referral, $advisor, $conflict, $consent);
        $referral = $lifecycle->transition($referral, Referral::STAGE_BROKER_REFERRAL_SENT, $advisor);

        $this->assertSame(Referral::STAGE_BROKER_REFERRAL_SENT, $referral->stage);
        $this->assertSame($conflict->id, $referral->conflict_declaration_id);
        $this->assertSame($consent->id, $referral->consent_id);
    }

    public function test_stale_conflict_blocks_coach_referral_send(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor('stale-conflict-advisor@example.test');
        $coach = $this->activeCoach('stale-conflict-coach@example.test');
        $referral = app(CoachPanel::class)->createReferral(
            client: $client,
            coach: $coach,
            advisor: $advisor,
            specialisation: CoachSpecialisation::BUSINESS_EXECUTIVE->value,
            subjectType: CoachPanel::SUBJECT_OWNER,
        );
        $conflict = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::COACH_REFERRAL,
            existingRelationship: false,
        );
        $conflict->forceFill([
            'declared_at' => now()->subDays(ConflictDeclarer::FRESH_FOR_DAYS + 1),
        ])->save();
        $consent = app(ReferralConsentManager::class)->grant($client, $advisor, Consent::TYPE_COACH_REFERRAL);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A fresh conflict declaration is required before sending this referral.');

        app(ReferralConsentManager::class)->prepareForSending($referral, $advisor, $conflict, $consent);
    }

    public function test_revoking_consent_withdraws_active_referrals_and_blocks_reuse(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor('revoke-consent-advisor@example.test');
        $coach = $this->activeCoach('revoke-consent-coach@example.test');
        $lifecycle = app(ReferralLifecycle::class);
        $referral = app(CoachPanel::class)->createReferral(
            client: $client,
            coach: $coach,
            advisor: $advisor,
            specialisation: CoachSpecialisation::BUSINESS_EXECUTIVE->value,
            subjectType: CoachPanel::SUBJECT_OWNER,
        );
        $conflict = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::COACH_REFERRAL,
            existingRelationship: false,
        );
        $consent = app(ReferralConsentManager::class)->grant($client, $advisor, Consent::TYPE_COACH_REFERRAL);
        $referral = app(ReferralConsentManager::class)->prepareForSending($referral, $advisor, $conflict, $consent);
        $referral = $lifecycle->transition($referral, Referral::STAGE_COACH_REFERRAL_SENT, $advisor);

        $withdrawn = app(ReferralConsentManager::class)->revoke($consent, $advisor);

        $this->assertSame(1, $withdrawn);
        $this->assertSame(Referral::STAGE_WITHDRAWN, $referral->refresh()->stage);
        $this->assertNotNull($referral->closed_at);
        $this->assertSame(Consent::ELECTION_OPT_OUT, $consent->refresh()->election);
        $this->assertNotNull($consent->revoked_at);

        $newReferral = app(CoachPanel::class)->createReferral(
            client: $client,
            coach: $coach,
            advisor: $advisor,
            specialisation: CoachSpecialisation::BUSINESS_EXECUTIVE->value,
            subjectType: CoachPanel::SUBJECT_OWNER,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Active client consent is required before sending this referral.');

        app(ReferralConsentManager::class)->prepareForSending($newReferral, $advisor, $conflict, $consent->refresh());
    }

    private function activeBroker(string $email): PanelMember
    {
        $advisor = $this->advisor('approver-'.$email);
        $user = $this->panelUser($email, User::TYPE_BROKER);
        $onboarding = app(PanelOnboarding::class);
        $member = $onboarding->submitApplication($user, PanelMember::TYPE_BROKER, [
            'fixture' => true,
            'fsp_number' => 'FSP100001',
        ]);
        $agreement = $onboarding->approve($member, $advisor);
        $onboarding->signAgreement($agreement, $user);

        return $member->refresh()->load('user');
    }

    private function activeCoach(string $email): PanelMember
    {
        $advisor = $this->advisor('approver-'.$email);
        $user = $this->panelUser($email, User::TYPE_COACH);
        $onboarding = app(PanelOnboarding::class);
        $member = $onboarding->submitApplication($user, PanelMember::TYPE_COACH, ['fixture' => true]);
        $member = app(CoachPanel::class)->vet($member, $advisor, [CoachSpecialisation::BUSINESS_EXECUTIVE->value]);
        $agreement = $onboarding->approve($member, $advisor);
        $onboarding->signAgreement($agreement, $user);

        return $member->refresh()->load('user');
    }

    private function panelUser(string $email, string $type): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => $type,
            'primary_role' => $type,
        ]);
        $user->assignRole($type);

        return $user;
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

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientWithAdvisor(string $advisorEmail): array
    {
        $advisor = $this->advisor($advisorEmail);
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Referral Compliance Limited',
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
}
