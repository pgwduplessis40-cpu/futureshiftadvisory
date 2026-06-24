<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\TermsAcceptance;
use App\Models\TermsEnforcement;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Pdf\PdfRenderer;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TermsVersionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

final class TermsVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_version_seeder_imports_the_14_clause_source_document(): void
    {
        $this->seed(TermsVersionSeeder::class);

        $version = TermsVersion::query()->where('version', '1')->with('clauses')->firstOrFail();

        $this->assertSame('Future Shift Advisory Terms and Conditions', $version->title);
        $this->assertTrue($version->material);
        $this->assertCount(14, $version->clauses);
        $this->assertEqualsCanonicalizing(
            [1, 5, 6, 10, 12],
            $version->clauses->where('material', true)->pluck('clause_number')->all(),
        );
    }

    public function test_super_admin_can_draft_and_update_terms_version(): void
    {
        $this->seed([RoleSeeder::class, TermsVersionSeeder::class]);
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.terms.store'))
            ->assertRedirect();

        $draft = TermsVersion::query()
            ->whereNull('published_at')
            ->where('version', '2')
            ->with('clauses')
            ->firstOrFail();

        $clauses = $draft->clauses
            ->map(fn ($clause): array => [
                'id' => $clause->id,
                'clause_number' => $clause->clause_number,
                'title' => $clause->title,
                'body' => $clause->clause_number === 1 ? 'Updated clause one body.' : $clause->body,
                'material' => $clause->clause_number === 2 ? true : $clause->material,
            ])
            ->values()
            ->all();

        $this->actingAsMfa($admin)
            ->put(route('admin.terms.update', $draft), [
                'version' => '2',
                'title' => 'Updated Terms',
                'material' => true,
                'notice_period_days' => 45,
                'reviewer_reference' => 'Review Firm, 2026-05-21',
                'clauses' => $clauses,
            ])
            ->assertRedirect(route('admin.terms.edit', $draft, absolute: false));

        $draft->refresh();

        $this->assertSame('Updated Terms', $draft->title);
        $this->assertSame(45, $draft->notice_period_days);
        $this->assertSame('Updated clause one body.', $draft->clauses()->where('clause_number', 1)->firstOrFail()->body);
        $this->assertTrue((bool) $draft->clauses()->where('clause_number', 2)->firstOrFail()->material);
    }

    public function test_terms_history_surfaces_document_and_clause_classification(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $published = $this->termsVersion('1', published: true);
        $draft = $this->termsVersion('2');
        $this->acceptTerms($admin, $published);
        $draft->forceFill(['material' => true])->save();
        $draft->clauses()->whereIn('clause_number', [2, 3])->update(['material' => true]);

        $this->actingAsMfa($admin)
            ->get(route('admin.terms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/terms/Index')
                ->has('versions', 2)
                ->where('versions', function ($versions) use ($draft, $published): bool {
                    $draftPayload = $versions->firstWhere('id', $draft->id);
                    $publishedPayload = $versions->firstWhere('id', $published->id);

                    return $draftPayload['material'] === true
                        && $draftPayload['material_clauses_count'] === 7
                        && $publishedPayload['material_clauses_count'] === 5;
                }),
            );
    }

    public function test_super_admin_can_activate_terms_enforcement_once_after_publish(): void
    {
        $this->seed(RoleSeeder::class);
        $published = $this->termsVersion('1', published: true);
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->get(route('admin.terms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('enforcement.active', false)
                ->where('enforcement.can_activate', true)
                ->where('enforcement.latest_published_version.version', $published->version),
            );

        $this->actingAsMfa($admin)
            ->post(route('admin.terms.enforcement.activate'))
            ->assertRedirect(route('admin.terms.index', absolute: false));

        $this->assertDatabaseHas('terms_enforcements', [
            'scope' => TermsEnforcement::SCOPE_PLATFORM,
            'activated_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'terms.enforcement_activated']);

        $this->actingAsMfa($admin)
            ->get(route('admin.terms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('enforcement.active', true)
                ->where('enforcement.can_activate', false),
            );

        $this->actingAsMfa($admin)
            ->post(route('admin.terms.enforcement.activate'))
            ->assertStatus(422);
    }

    public function test_terms_edit_payload_supports_whole_document_and_per_clause_classification(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $draft = $this->termsVersion('3');
        $draft->clauses()->where('clause_number', 4)->update(['material' => true]);

        $this->actingAsMfa($admin)
            ->get(route('admin.terms.edit', $draft))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/terms/Edit')
                ->where('version.material', false)
                ->where('version.material_clauses_count', 6)
                ->where('version.clauses.3.material', true),
            );
    }

    public function test_terms_download_falls_back_to_plain_pdf_when_browser_renderer_fails(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $version = $this->termsVersion('1', published: true);
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                throw new RuntimeException('Browser renderer unavailable.');
            }
        });

        $response = $this->actingAsMfa($admin)
            ->get(route('admin.terms.download', $version))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
        $this->assertDatabaseHas('audit_events', ['action' => 'terms.downloaded_for_review']);
    }

    public function test_material_publish_sets_prior_active_acceptances_to_expire_and_queues_reacceptance(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $prior = $this->termsVersion('1', published: true);
        $draft = $this->termsVersion('2');
        $this->acceptTerms($admin, $prior);
        $user = User::factory()->withTwoFactor()->create();
        $acceptance = TermsAcceptance::query()->create([
            'user_id' => $user->id,
            'terms_version_id' => $prior->id,
            'accepted_at' => now()->subDay(),
        ]);

        $this->actingAsMfa($admin)
            ->post(route('admin.terms.publish', $draft), [
                'material' => true,
                'notice_period_days' => 30,
                'reviewer_reference' => 'Review Firm',
            ])
            ->assertRedirect(route('admin.terms.preview', $draft, absolute: false));

        $acceptance->refresh();
        $draft->refresh();

        $this->assertTrue($draft->material);
        $this->assertNotNull($draft->published_at);
        $this->assertNotNull($acceptance->expires_at);
        $this->assertTrue($acceptance->expires_at->isSameDay($draft->published_at->copy()->addDays(30)));
        $this->assertNotNull($acceptance->reacceptance_notice_queued_at);
        $this->assertDatabaseHas('audit_events', ['action' => 'terms.reacceptance_queued']);
        $this->assertDatabaseHas('audit_events', ['action' => 'terms.published']);
    }

    public function test_non_material_publish_leaves_prior_acceptances_active_and_audits_publish(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $prior = $this->termsVersion('1', published: true);
        $draft = $this->termsVersion('2');
        $this->acceptTerms($admin, $prior);
        $user = User::factory()->withTwoFactor()->create();
        $acceptance = TermsAcceptance::query()->create([
            'user_id' => $user->id,
            'terms_version_id' => $prior->id,
            'accepted_at' => now()->subDay(),
        ]);

        $this->actingAsMfa($admin)
            ->post(route('admin.terms.publish', $draft), [
                'material' => false,
                'notice_period_days' => 30,
                'reviewer_reference' => null,
            ])
            ->assertRedirect(route('admin.terms.preview', $draft, absolute: false));

        $acceptance->refresh();

        $this->assertNull($acceptance->expires_at);
        $this->assertNull($acceptance->reacceptance_notice_queued_at);
        $this->assertDatabaseMissing('audit_events', ['action' => 'terms.reacceptance_queued']);
        $this->assertDatabaseHas('audit_events', ['action' => 'terms.published']);
    }

    public function test_prior_versions_remain_readable_after_a_new_publish(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $prior = $this->termsVersion('1', published: true);
        $draft = $this->termsVersion('2');
        $this->acceptTerms($admin, $prior);

        $this->actingAsMfa($admin)
            ->post(route('admin.terms.publish', $draft), [
                'material' => false,
                'notice_period_days' => 30,
                'reviewer_reference' => null,
            ])
            ->assertRedirect(route('admin.terms.preview', $draft, absolute: false));

        $this->actingAsMfa($admin)
            ->get(route('admin.terms.preview', $prior))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/terms/Preview')
                ->where('version.version', '1'),
            );
    }

    public function test_only_super_admin_policy_can_publish_terms(): void
    {
        $this->seed(RoleSeeder::class);
        $draft = $this->termsVersion('1');
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $this->assertFalse(Gate::forUser($advisor)->allows('publish', $draft));
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        $publishedTerms = TermsVersion::query()->published()->latest('published_at')->first();
        if ($publishedTerms instanceof TermsVersion) {
            $this->acceptTerms($user, $publishedTerms);
        }

        return $user;
    }

    private function acceptTerms(User $user, TermsVersion $terms): void
    {
        TermsAcceptance::query()->create([
            'user_id' => $user->id,
            'terms_version_id' => $terms->id,
            'accepted_at' => now()->subMinute(),
        ]);
    }

    private function termsVersion(string $version, bool $published = false): TermsVersion
    {
        $terms = TermsVersion::query()->create([
            'version' => $version,
            'title' => 'Terms '.$version,
            'material' => false,
            'published_at' => $published ? now()->subDay() : null,
            'notice_period_days' => 30,
        ]);

        foreach (range(1, 14) as $number) {
            $terms->clauses()->create([
                'clause_number' => $number,
                'title' => 'Clause '.$number,
                'body' => 'Body '.$number,
                'material' => in_array($number, [1, 5, 6, 10, 12], true),
            ]);
        }

        return $terms;
    }
}
