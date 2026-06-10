<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Enums\EngagementType;
use App\Enums\ReportType;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\Document;
use App\Models\LearningRollback;
use App\Models\LearningUpdate;
use App\Models\LearningUpdateDecision;
use App\Models\LearningUpdateImplementation;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\Template;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\ScanResult;
use App\Services\Learning\ApprovalFlow;
use App\Services\Learning\Rollback;
use App\Services\Templates\TemplateImplementer;
use App\Services\Templates\TemplateSuggestionLayer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TemplateLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->app->bind(AiClient::class, FakeAiClient::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_template_suggestion_layer_creates_dormant_draft_and_governed_candidate(): void
    {
        Carbon::setTestNow('2026-05-26 09:00:00');
        $report = $this->sourceReport('Acme Sensitive Limited');

        $run = app(TemplateSuggestionLayer::class)->run(windowDays: 30, maxCandidates: 3, windowEnd: now());

        $template = Template::query()->firstOrFail();
        $update = LearningUpdate::query()->where('layer_id', TemplateSuggestionLayer::LAYER_ID)->firstOrFail();
        $serializedTemplate = json_encode([
            'title' => $template->title,
            'body' => $template->body,
            'structure' => $template->structure,
        ], JSON_THROW_ON_ERROR);

        $this->assertSame(1, $run->candidates_created);
        $this->assertSame(Template::STATUS_DRAFT, $template->status);
        $this->assertSame('report:'.$report->id, $template->source_reference);
        $this->assertSame(0, Template::query()->usable()->count());
        $this->assertStringNotContainsString('Acme', $serializedTemplate);
        $this->assertSame(LearningUpdate::STATUS_DETECTED, $update->status);
        $this->assertSame('activate_template', $update->proposed_change['action']);
        $this->assertFalse($update->proposed_change['automatic_application']);
        $this->assertTrue($update->proposed_change['requires_approval']);
        $this->assertSame($template->id, $update->proposed_change['template_id']);
        $this->assertSame('report:'.$report->id, $update->evidence['source_reference']);
        $this->assertSame('fake-ai-client', $update->evidence['model']);
        $this->assertTrue($update->evidence['client_pii_excluded']);
        $this->assertContains('report:'.$report->id, collect($update->evidence['attributions'])->pluck('source_reference')->all());

        $this->actingAsMfa($this->userWithRole(User::TYPE_SUPER_ADMIN))
            ->get(route('admin.learning-updates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('cards', 1)
                ->where('cards.0.layer_id', TemplateSuggestionLayer::LAYER_ID)
                ->where('cards.0.proposed_change.action', 'activate_template'));
    }

    public function test_approval_does_not_activate_until_template_implementer_runs_and_rollback_restores_draft(): void
    {
        Carbon::setTestNow('2026-05-26 09:00:00');
        [$template, $update] = $this->draftSuggestion();
        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN);

        app(ApprovalFlow::class)->decide(
            update: $update,
            decision: LearningUpdateDecision::DECISION_APPROVE,
            actor: $admin,
            reason: 'Template looks reusable.',
        );

        $this->assertSame(Template::STATUS_DRAFT, $template->refresh()->status);
        $this->assertNull($template->learning_update_implementation_id);

        Carbon::setTestNow(now()->addDays(8));

        $implementation = app(TemplateImplementer::class)->implement($update->refresh(), $admin);
        $template->refresh();
        $update->refresh();

        $this->assertSame(Template::STATUS_ACTIVE, $template->status);
        $this->assertSame($implementation->id, $template->learning_update_implementation_id);
        $this->assertSame(LearningUpdate::STATUS_IMPLEMENTED, $update->status);
        $this->assertSame(Template::class, $implementation->target_type);
        $this->assertSame($template->id, $implementation->target_id);
        $this->assertSame([
            'status' => Template::STATUS_DRAFT,
            'learning_update_implementation_id' => null,
        ], $implementation->before_state);
        $this->assertSame([
            'status' => Template::STATUS_ACTIVE,
            'learning_update_implementation_id' => $implementation->id,
        ], $implementation->after_state);

        $rollback = app(Rollback::class)->rollback($implementation, 'Template needs more review.', $admin);

        $this->assertInstanceOf(LearningRollback::class, $rollback);
        $this->assertSame(Template::STATUS_DRAFT, $template->refresh()->status);
        $this->assertNull($template->learning_update_implementation_id);
        $this->assertTrue($rollback->restored_state['restored']);
        $this->assertSame(LearningUpdate::STATUS_ROLLED_BACK, $update->refresh()->status);
    }

    public function test_rejected_template_suggestion_leaves_draft_unusable(): void
    {
        Carbon::setTestNow('2026-05-26 09:00:00');
        [$template, $update] = $this->draftSuggestion();
        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN);

        app(ApprovalFlow::class)->decide(
            update: $update,
            decision: LearningUpdateDecision::DECISION_REJECT,
            actor: $admin,
            reason: 'Too narrow.',
        );

        $this->assertSame(LearningUpdate::STATUS_REJECTED, $update->refresh()->status);
        $this->assertSame(Template::STATUS_DRAFT, $template->refresh()->status);
        $this->assertSame(0, Template::query()->usable()->count());
        $this->assertSame(0, LearningUpdateImplementation::query()->count());
    }

    public function test_template_library_view_and_manage_permissions_are_separate(): void
    {
        $advisor = $this->userWithRole(User::TYPE_ADVISOR);
        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN);
        $active = $this->template('Reusable report checklist', Template::STATUS_ACTIVE);
        $this->template('Dormant draft', Template::STATUS_DRAFT);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.templates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/templates/Index')
                ->has('templates', 1)
                ->where('templates.0.id', $active->id)
                ->where('canManage', false));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.templates.show', $active))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/templates/Show')
                ->where('canManage', false));

        $this->actingAsMfa($advisor)
            ->post(route('advisor.templates.store'), [
                'category' => Template::CATEGORY_REPORT,
                'title' => 'Advisor mutation attempt',
                'body' => 'This should be blocked.',
                'status' => Template::STATUS_ACTIVE,
            ])
            ->assertForbidden();

        $this->actingAsMfa($admin)
            ->post(route('advisor.templates.store'), [
                'category' => Template::CATEGORY_EMAIL,
                'title' => 'Follow-up email',
                'body' => 'Thanks for meeting today.',
                'status' => Template::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('templates', [
            'title' => 'Follow-up email',
            'status' => Template::STATUS_ACTIVE,
        ]);
    }

    public function test_admin_can_upload_and_download_proposal_template_file(): void
    {
        Storage::fake('secure_local');
        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN);
        $upload = UploadedFile::fake()->create(
            'FSA_Report_Proposal_Template.docx',
            24,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $this->actingAsMfa($admin)
            ->post(route('advisor.templates.store'), [
                'category' => Template::CATEGORY_PROPOSAL,
                'title' => 'FSA Report Proposal Template',
                'body' => '',
                'status' => Template::STATUS_ACTIVE,
                'file' => $upload,
            ])
            ->assertRedirect();

        $template = Template::query()->firstOrFail();
        $uploadedFile = data_get($template->structure, 'uploaded_file');

        $this->assertSame(Template::CATEGORY_PROPOSAL, $template->category);
        $this->assertSame('uploaded_file', $template->structure['source_kind']);
        $this->assertSame('FSA_Report_Proposal_Template.docx', $uploadedFile['original_name']);
        Storage::disk('secure_local')->assertExists($uploadedFile['stored_path']);

        $this->actingAsMfa($admin)
            ->get(route('advisor.templates.index', ['status' => Template::STATUS_ACTIVE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('templates.0.category', Template::CATEGORY_PROPOSAL)
                ->where('templates.0.uploaded_file.original_name', 'FSA_Report_Proposal_Template.docx')
                ->where('templates.0.download_url', route('advisor.templates.download', $template, absolute: false)));

        $this->actingAsMfa($admin)
            ->get(route('advisor.templates.download', $template))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_infected_template_upload_is_rejected_and_not_persisted(): void
    {
        Storage::fake('secure_local');
        $admin = $this->userWithRole(User::TYPE_SUPER_ADMIN);

        // Every uploaded file must be virus-scanned before persistence (spec §4).
        $this->app->bind(FileScanner::class, fn (): FileScanner => new class implements FileScanner
        {
            public function scan(mixed $stream): ScanResult
            {
                return ScanResult::infected('Eicar-Test-Signature');
            }
        });

        $upload = UploadedFile::fake()->create(
            'Infected_Template.docx',
            24,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $this->actingAsMfa($admin)
            ->post(route('advisor.templates.store'), [
                'category' => Template::CATEGORY_PROPOSAL,
                'title' => 'Infected proposal template',
                'body' => '',
                'status' => Template::STATUS_ACTIVE,
                'file' => $upload,
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Template::query()->count());
        $this->assertSame(0, Document::query()->count());
        $this->assertEmpty(Storage::disk('secure_local')->allFiles());
    }

    /**
     * @return array{0: Template, 1: LearningUpdate}
     */
    private function draftSuggestion(): array
    {
        $this->sourceReport('Acme Sensitive Limited');
        app(TemplateSuggestionLayer::class)->run(windowDays: 30, maxCandidates: 1, windowEnd: now());

        return [
            Template::query()->firstOrFail(),
            LearningUpdate::query()->where('layer_id', TemplateSuggestionLayer::LAYER_ID)->firstOrFail(),
        ];
    }

    private function sourceReport(string $legalName): Report
    {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000000',
            'legal_name' => $legalName,
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);

        /** @var Report $report */
        $report = Report::query()->create([
            'client_id' => $client->getKey(),
            'type' => ReportType::Advisor,
            'title' => 'Advisor report - '.$legalName,
            'generated_at' => now()->subDay(),
            'metadata' => ['client_name' => $legalName],
            'review_status' => 'not_required',
        ]);

        ReportSection::query()->create([
            'report_id' => $report->getKey(),
            'client_id' => $client->getKey(),
            'key' => 'cashflow',
            'title' => $legalName.' cashflow',
            'body' => $legalName.' has sensitive cashflow and margin notes that must not enter the reusable template.',
            'position' => 1,
            'lens' => 'diagnostic',
            'attributions' => [['claim' => 'Fixture', 'source_reference' => 'test:template-report']],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_NONE,
            'document_support_note' => 'Document support: none.',
            'data_quality_note' => 'Data quality note: fixture.',
        ]);

        return $report->refresh()->load('sections');
    }

    private function template(string $title, string $status): Template
    {
        /** @var Template $template */
        $template = Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => $title,
            'body' => 'Reusable body.',
            'structure' => ['sections' => []],
            'status' => $status,
            'version' => 1,
        ]);

        return $template;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => $role,
            'primary_role' => $role,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
