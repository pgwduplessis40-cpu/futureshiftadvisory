<?php

declare(strict_types=1);

namespace Tests\Feature\Questionnaire;

use App\Models\Questionnaire;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Questionnaires\QuestionnairePayload;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StandardAdvisoryQuestionnaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class QuestionnaireAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_draft_edit_preview_and_publish_questionnaire_version(): void
    {
        $this->seed([RoleSeeder::class, StandardAdvisoryQuestionnaireSeeder::class]);
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.questionnaires.store'), ['set' => 'standard_advisory'])
            ->assertRedirect();

        $draft = Questionnaire::query()
            ->whereNull('published_at')
            ->where('version', '2')
            ->with('sections.questions')
            ->firstOrFail();

        $payload = app(QuestionnairePayload::class)->schema($draft);
        $sections = $payload['sections'];
        $first = array_shift($sections);
        $sections[] = $first;
        $sections[0]['title'] = 'Reordered Products and Services';

        $this->actingAsMfa($admin)
            ->put(route('admin.questionnaires.update', $draft), [
                'set' => $payload['set'],
                'version' => '2',
                'title' => 'Updated Standard Advisory',
                'sections' => $sections,
            ])
            ->assertRedirect(route('admin.questionnaires.edit', $draft, absolute: false));

        $draft->refresh();
        $this->assertSame('Updated Standard Advisory', $draft->title);
        $this->assertSame('Reordered Products and Services', $draft->sections()->orderBy('order')->firstOrFail()->title);

        $this->actingAsMfa($admin)
            ->get(route('admin.questionnaires.preview', $draft))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/questionnaires/Preview')
                ->where('questionnaire.title', 'Updated Standard Advisory')
            );

        $this->actingAsMfa($admin)
            ->post(route('admin.questionnaires.publish', $draft))
            ->assertRedirect(route('admin.questionnaires.preview', $draft, absolute: false));

        $this->assertNotNull($draft->refresh()->published_at);
        $this->assertDatabaseHas('audit_events', ['action' => 'questionnaire.published']);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        TermsVersion::query()->delete();

        return $user;
    }
}
