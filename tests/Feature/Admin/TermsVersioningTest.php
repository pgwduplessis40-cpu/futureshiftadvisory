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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

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
        $reviewerReference = rtrim(str_repeat('Acceptance workflow evidence. ', 12));

        $this->actingAsMfa($admin)
            ->put(route('admin.terms.update', $draft), [
                'version' => '2',
                'title' => 'Updated Terms',
                'material' => true,
                'notice_period_days' => 45,
                'reviewer_reference' => $reviewerReference,
                'clauses' => $clauses,
            ])
            ->assertRedirect(route('admin.terms.edit', $draft, absolute: false));

        $draft->refresh();

        $this->assertSame('Updated Terms', $draft->title);
        $this->assertSame(45, $draft->notice_period_days);
        $this->assertSame($reviewerReference, $draft->reviewer_reference);
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

    public function test_admin_can_upload_and_download_terms_source_word_document(): void
    {
        Storage::fake('secure_local');
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $draft = $this->termsVersion('1');
        $upload = UploadedFile::fake()->create(
            'Future_Shift_Terms.docx',
            24,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $this->actingAsMfa($admin)
            ->post(route('admin.terms.source-file.store', $draft), [
                'file' => $upload,
            ])
            ->assertRedirect();

        $draft->refresh();
        $sourceFile = $draft->source_file;

        $this->assertIsArray($sourceFile);
        $this->assertSame('Future_Shift_Terms.docx', $sourceFile['original_name']);
        Storage::disk('secure_local')->assertExists($sourceFile['stored_path']);
        $this->assertDatabaseHas('audit_events', ['action' => 'terms.source_file_uploaded']);

        $this->actingAsMfa($admin)
            ->get(route('admin.terms.edit', $draft))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('version.source_file.original_name', 'Future_Shift_Terms.docx')
                ->where('version.source_download_url', route('admin.terms.source-file.download', $draft, absolute: false)));

        $this->actingAsMfa($admin)
            ->get(route('admin.terms.source-file.download', $draft))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_terms_preview_and_pdf_download_render_uploaded_docx_source(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is required to build the uploaded terms DOCX fixture.');
        }

        Storage::fake('secure_local');
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $draft = $this->termsVersion('1');
        $path = 'terms/source-documents/future-shift-terms.docx';
        Storage::disk('secure_local')->put($path, $this->minimalDocx([
            ['Uploaded T&C heading', 'Heading1'],
            ['These are the uploaded terms that must appear in preview and PDF output.'],
        ]));
        $draft->forceFill([
            'source_file' => [
                'stored_path' => $path,
                'original_name' => 'Future_Shift_Terms.docx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'extension' => 'docx',
                'byte_size' => Storage::disk('secure_local')->size($path),
                'uploaded_at' => now()->toIso8601String(),
            ],
        ])->save();

        $this->actingAsMfa($admin)
            ->get(route('admin.terms.preview', $draft))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('version.source_file.original_name', 'Future_Shift_Terms.docx')
                ->where('version.source_preview_html', fn (?string $html): bool => is_string($html)
                    && str_contains($html, 'Uploaded T&amp;C heading')
                    && str_contains($html, 'uploaded terms that must appear')));

        $renderer = new class implements PdfRenderer
        {
            public string $html = '';

            public function render(string $html): string
            {
                $this->html = $html;

                return '%PDF-1.4 terms source';
            }
        };
        $this->app->instance(PdfRenderer::class, $renderer);

        $this->actingAsMfa($admin)
            ->get(route('admin.terms.download', $draft))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('Uploaded T&amp;C heading', $renderer->html);
        $this->assertStringContainsString('uploaded terms that must appear', $renderer->html);
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
        $reviewerReference = rtrim(str_repeat('Independent review note. ', 12));

        $this->actingAsMfa($admin)
            ->post(route('admin.terms.publish', $draft), [
                'material' => true,
                'notice_period_days' => 30,
                'reviewer_reference' => $reviewerReference,
            ])
            ->assertRedirect(route('admin.terms.preview', $draft, absolute: false));

        $acceptance->refresh();
        $draft->refresh();

        $this->assertTrue($draft->material);
        $this->assertSame($reviewerReference, $draft->reviewer_reference);
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

    /**
     * @param  array<int, array{0:string,1?:string}>  $paragraphs
     */
    private function minimalDocx(array $paragraphs): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fsa-test-terms-docx-');
        $this->assertIsString($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML);
        $zip->addFromString('word/document.xml', sprintf(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    %s
    <w:sectPr/>
  </w:body>
</w:document>
XML,
            implode("\n", array_map(
                fn (array $paragraph): string => $this->wordParagraph($paragraph[0], $paragraph[1] ?? null),
                $paragraphs,
            )),
        ));
        $zip->close();

        $bytes = file_get_contents($path);
        @unlink($path);

        $this->assertIsString($bytes);

        return $bytes;
    }

    private function wordParagraph(string $text, ?string $style = null): string
    {
        $styleXml = $style === null
            ? ''
            : sprintf('<w:pPr><w:pStyle w:val="%s"/></w:pPr>', htmlspecialchars($style, ENT_XML1 | ENT_QUOTES, 'UTF-8'));

        return sprintf(
            '<w:p>%s<w:r><w:t>%s</w:t></w:r></w:p>',
            $styleXml,
            htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
        );
    }
}
