<?php

declare(strict_types=1);

namespace Tests\Feature\Terms;

use App\Models\TermsAcceptance;
use App\Models\TermsEnforcement;
use App\Models\TermsVersion;
use App\Models\User;
use App\Notifications\TermsDeclinedUrgentNotification;
use App\Services\Pdf\PdfRenderer;
use App\Services\Security\StepUpEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

final class AcceptanceGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_mfa_verified_user_without_terms_acceptance_is_redirected_to_gate(): void
    {
        $this->termsVersion('1', publishedAt: now()->subDay(), material: true);
        $this->activateTermsEnforcement();
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAsMfa($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('terms.pending', absolute: false));

        $this->actingAsMfa($user)
            ->get(route('terms.pending'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('terms/Gate')
                ->where('version.version', '1')
                ->where('hasDeclined', false),
            );
    }

    public function test_published_terms_are_not_compulsory_until_enforcement_is_activated(): void
    {
        $this->termsVersion('1', publishedAt: now()->subDay(), material: true);
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAsMfa($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAsMfa($user)
            ->get(route('terms.pending'))
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_gate_accept_button_is_disabled_until_scroll_end_event(): void
    {
        $page = file_get_contents(resource_path('js/pages/terms/Gate.tsx'));

        $this->assertIsString($page);
        $this->assertStringContainsString("window.addEventListener('scroll-end'", $page);
        $this->assertStringContainsString('disabled={!hasReachedEnd || form.processing}', $page);
        $this->assertStringContainsString('data-testid="terms-accept-button"', $page);
    }

    public function test_acceptance_writes_signed_pdf_to_secure_storage_with_hash_evidence(): void
    {
        Storage::fake('secure_local');
        $renderer = new class implements PdfRenderer
        {
            public string $html = '';

            public function render(string $html): string
            {
                $this->html = $html;

                return "%PDF-1.4\nfake signed terms";
            }
        };
        $this->app->instance(PdfRenderer::class, $renderer);

        $version = $this->termsVersion('1', publishedAt: now()->subDay(), material: true, clauseBody: 'Exact clause text for PDF proof.');
        $this->activateTermsEnforcement();
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAsMfa($user)
            ->post(route('terms.accept'), [
                'scroll_end_confirmed' => true,
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $acceptance = TermsAcceptance::query()->firstOrFail();

        $this->assertSame($user->id, $acceptance->user_id);
        $this->assertSame($version->id, $acceptance->terms_version_id);
        $this->assertNotNull($acceptance->accepted_at);
        $this->assertNull($acceptance->declined_at);
        $this->assertIsString($acceptance->signed_pdf_path);
        $this->assertTrue(Storage::disk('secure_local')->exists($acceptance->signed_pdf_path));
        $this->assertSame("%PDF-1.4\nfake signed terms", Storage::disk('secure_local')->get($acceptance->signed_pdf_path));
        $this->assertNotNull($acceptance->signed_pdf_sha256_envelope);
        $this->assertSame('aes-256-laravel', $acceptance->signed_pdf_envelope_meta['alg']);
        $this->assertSame(strlen("%PDF-1.4\nfake signed terms"), $acceptance->signed_pdf_byte_size);
        $this->assertStringContainsString('Exact clause text for PDF proof.', $renderer->html);
        $this->assertDatabaseHas('audit_events', ['action' => 'terms.accepted']);
    }

    public function test_acceptance_uses_plain_pdf_fallback_when_browser_renderer_fails(): void
    {
        Storage::fake('secure_local');
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                throw new RuntimeException('Browser renderer unavailable.');
            }
        });

        $this->termsVersion('1', publishedAt: now()->subDay(), material: true);
        $this->activateTermsEnforcement();
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAsMfa($user)
            ->post(route('terms.accept'), [
                'scroll_end_confirmed' => true,
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $acceptance = TermsAcceptance::query()->firstOrFail();

        $this->assertIsString($acceptance->signed_pdf_path);
        $this->assertStringStartsWith('%PDF-1.4', Storage::disk('secure_local')->get($acceptance->signed_pdf_path));
    }

    public function test_terms_download_uses_plain_pdf_fallback_when_browser_renderer_fails(): void
    {
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                throw new RuntimeException('Browser renderer unavailable.');
            }
        });

        $this->termsVersion('1', publishedAt: now()->subDay(), material: true);
        $user = User::factory()->withTwoFactor()->create();

        $response = $this->actingAsMfa($user)
            ->get(route('terms.download'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
    }

    public function test_acceptance_requires_scroll_end_confirmation(): void
    {
        Storage::fake('secure_local');
        $this->termsVersion('1', publishedAt: now()->subDay(), material: true);
        $this->activateTermsEnforcement();
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAsMfa($user)
            ->post(route('terms.accept'), [
                'scroll_end_confirmed' => false,
            ])
            ->assertSessionHasErrors('scroll_end_confirmed');

        $this->assertDatabaseCount('terms_acceptances', 0);
    }

    public function test_decline_suspends_user_and_sends_urgent_notifications(): void
    {
        Notification::fake();
        $version = $this->termsVersion('1', publishedAt: now()->subDay(), material: true);
        $this->activateTermsEnforcement();
        $user = User::factory()->withTwoFactor()->create();
        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAsMfa($user)
            ->post(route('terms.decline'))
            ->assertRedirect(route('terms.declined', absolute: false));

        $user->refresh();
        $acceptance = TermsAcceptance::query()->firstOrFail();

        $this->assertSame($version->id, $acceptance->terms_version_id);
        $this->assertNull($acceptance->accepted_at);
        $this->assertNotNull($acceptance->declined_at);
        $this->assertNotNull($user->suspended_at);
        $this->assertSame('terms_declined', $user->suspended_reason);
        $this->assertDatabaseHas('audit_events', ['action' => 'terms.declined']);

        Notification::assertSentTo($advisor, TermsDeclinedUrgentNotification::class);
        Notification::assertSentTo($superAdmin, TermsDeclinedUrgentNotification::class);
        Notification::assertNotSentTo($user, TermsDeclinedUrgentNotification::class);
    }

    public function test_declined_user_is_held_on_declined_page_and_can_later_accept(): void
    {
        Storage::fake('secure_local');
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\naccepted after decline";
            }
        });

        $this->termsVersion('1', publishedAt: now()->subDay(), material: true);
        $user = User::factory()->withTwoFactor()->create([
            'suspended_at' => now()->subMinute(),
            'suspended_reason' => 'terms_declined',
        ]);

        $this->actingAsMfa($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('terms.declined', absolute: false));

        $this->actingAsMfa($user)
            ->post(route('terms.accept'), [
                'scroll_end_confirmed' => true,
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $user->refresh();

        $this->assertNull($user->suspended_at);
        $this->assertNull($user->suspended_reason);
        $this->assertDatabaseHas('terms_acceptances', [
            'user_id' => $user->id,
            'accepted_at' => TermsAcceptance::query()->where('user_id', $user->id)->whereNotNull('accepted_at')->value('accepted_at'),
        ]);
    }

    public function test_material_republish_allows_prior_acceptance_until_expiry_then_forces_gate(): void
    {
        $prior = $this->termsVersion('1', publishedAt: now()->subDays(10), material: true);
        $this->termsVersion('2', publishedAt: now()->subDay(), material: true);
        $this->activateTermsEnforcement();
        $user = User::factory()->withTwoFactor()->create();
        TermsAcceptance::query()->create([
            'user_id' => $user->id,
            'terms_version_id' => $prior->id,
            'accepted_at' => now()->subDays(9),
            'expires_at' => now()->addDays(30),
            'reacceptance_notice_queued_at' => now()->subDay(),
        ]);

        $this->actingAsMfa($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->travel(31)->days();

        $this->actingAsMfa($user)
            ->withSession([StepUpEvaluator::SESSION_LAST_ACTIVITY_AT => now()->getTimestamp()])
            ->get(route('dashboard'))
            ->assertRedirect(route('terms.pending', absolute: false));
    }

    public function test_non_material_republish_does_not_force_gate_for_existing_active_acceptance(): void
    {
        $prior = $this->termsVersion('1', publishedAt: now()->subDays(10), material: true);
        $this->termsVersion('2', publishedAt: now()->subDay(), material: false);
        $this->activateTermsEnforcement();
        $user = User::factory()->withTwoFactor()->create();
        TermsAcceptance::query()->create([
            'user_id' => $user->id,
            'terms_version_id' => $prior->id,
            'accepted_at' => now()->subDays(9),
        ]);

        $this->actingAsMfa($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    private function termsVersion(
        string $version,
        ?\DateTimeInterface $publishedAt = null,
        bool $material = false,
        string $clauseBody = 'Body',
    ): TermsVersion {
        $terms = TermsVersion::query()->create([
            'version' => $version,
            'title' => 'Terms '.$version,
            'material' => $material,
            'published_at' => $publishedAt,
            'notice_period_days' => 30,
        ]);

        foreach (range(1, 14) as $number) {
            $terms->clauses()->create([
                'clause_number' => $number,
                'title' => 'Clause '.$number,
                'body' => $number === 7 ? $clauseBody : 'Body '.$number,
                'material' => in_array($number, [1, 5, 6, 10, 12], true),
            ]);
        }

        return $terms;
    }

    private function activateTermsEnforcement(): void
    {
        TermsEnforcement::query()->create([
            'scope' => TermsEnforcement::SCOPE_PLATFORM,
            'activated_at' => now()->subMinute(),
        ]);
    }
}
