<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KnowledgeEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_advisor_can_create_and_see_only_their_own_entries(): void
    {
        $advisor = $this->advisor();
        $otherAdvisor = $this->advisor('other@example.com');
        $this->entry($otherAdvisor, 'Hidden pricing note', 'This belongs to another advisor.');

        $this->actingAsMfa($advisor)
            ->post(route('advisor.knowledge.store'), [
                'category' => KnowledgeEntry::CATEGORY_METHODOLOGY,
                'title' => 'Margin review playbook',
                'body' => 'Review gross margin movement before recommending pricing action.',
                'tags' => 'margin, pricing, review',
            ])
            ->assertRedirect();

        $entry = KnowledgeEntry::query()->where('title', 'Margin review playbook')->firstOrFail();

        $this->assertSame($advisor->id, $entry->author_user_id);
        $this->assertSame(['margin', 'pricing', 'review'], $entry->tags);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.knowledge.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/knowledge/Index')
                ->has('entries', 1)
                ->where('entries.0.title', 'Margin review playbook'));
    }

    public function test_search_uses_relevance_ranking(): void
    {
        $advisor = $this->advisor();
        $this->entry(
            $advisor,
            'Cashflow runway method',
            'Use the monthly burn trend to assess resilience.',
            ['runway'],
        );
        $this->entry(
            $advisor,
            'Pricing review note',
            'Cashflow sensitivity should be checked after every price change.',
            ['pricing'],
        );
        $this->entry(
            $advisor,
            'Succession readiness',
            'Owner dependency and second-tier leadership depth.',
            ['succession'],
        );

        $this->actingAsMfa($advisor)
            ->get(route('advisor.knowledge.index', ['q' => 'cashflow']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('entries', 2)
                ->where('entries.0.title', 'Cashflow runway method')
                ->where('entries.1.title', 'Pricing review note'));
    }

    public function test_knowledge_entries_cannot_be_read_or_mutated_by_another_advisor(): void
    {
        $owner = $this->advisor('owner@example.com');
        $otherAdvisor = $this->advisor('other@example.com');
        $entry = $this->entry($owner, 'Private method', 'Only the author should see this.');

        $read = $this->actingAsMfa($otherAdvisor)
            ->get(route('advisor.knowledge.show', $entry));

        $this->assertContains($read->getStatusCode(), [403, 404]);

        $write = $this->actingAsMfa($otherAdvisor)
            ->patch(route('advisor.knowledge.update', $entry), [
                'category' => KnowledgeEntry::CATEGORY_OTHER,
                'title' => 'Mutated',
                'body' => 'Attempted mutation.',
                'tags' => '',
            ]);

        $this->assertContains($write->getStatusCode(), [403, 404]);
        $this->assertSame('Private method', $entry->refresh()->title);
    }

    public function test_junior_advisor_can_view_repository_but_cannot_create_entries(): void
    {
        $junior = $this->advisor('junior@example.com', User::TYPE_JUNIOR_ADVISOR);

        $this->actingAsMfa($junior)
            ->get(route('advisor.knowledge.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/knowledge/Index')
                ->where('canCreate', false));

        $this->actingAsMfa($junior)
            ->get(route('advisor.knowledge.create'))
            ->assertForbidden();
    }

    private function advisor(string $email = 'advisor@example.com', string $type = User::TYPE_ADVISOR): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => $type,
            'primary_role' => $type,
        ]);
        $advisor->assignRole($type);

        return $advisor;
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function entry(User $author, string $title, string $body, array $tags = []): KnowledgeEntry
    {
        app(RequestContext::class)->apply('system', [], (string) $author->getKey());

        return KnowledgeEntry::query()->create([
            'author_user_id' => $author->getKey(),
            'category' => KnowledgeEntry::CATEGORY_METHODOLOGY,
            'title' => $title,
            'body' => $body,
            'tags' => $tags,
        ]);
    }
}
