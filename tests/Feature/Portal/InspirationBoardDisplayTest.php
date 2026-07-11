<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\EngagementType;
use App\Models\BoardPost;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Services\Board\InspirationBoard;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspirationBoardDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('secure_local');
        Config::set('filesystems.disks.secure_local.root', storage_path('framework/testing/board-secure'));
    }

    public function test_featured_post_appears_on_client_dashboard(): void
    {
        [$user] = $this->clientUserWithClient();
        $this->publishedQuote('Keep going — steady work compounds.', 'Future Shift');

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/Dashboard')
                ->where('inspirationBoard.type', BoardPost::TYPE_QUOTE)
                ->where('inspirationBoard.body', 'Keep going — steady work compounds.')
                ->where('inspirationBoard.attribution', 'Future Shift')
            );
    }

    public function test_featured_post_appears_on_entrepreneur_dashboard(): void
    {
        $user = $this->entrepreneurUser();
        $this->publishedQuote('You have everything you need to begin.', null);

        $this->actingAsMfa($user)
            ->get(route('portal.entrepreneur.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/entrepreneur/Dashboard')
                ->where('inspirationBoard.body', 'You have everything you need to begin.')
            );
    }

    public function test_feed_lists_only_published_posts(): void
    {
        [$user] = $this->clientUserWithClient();
        $this->publishedQuote('First.', null);
        $this->publishedQuote('Second.', null);
        BoardPost::query()->create([
            'type' => BoardPost::TYPE_MESSAGE,
            'body' => 'A draft that should not be visible.',
            'status' => BoardPost::STATUS_DRAFT,
        ]);

        $this->actingAsMfa($user)
            ->get(route('portal.inspiration-board.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/inspiration-board/Index')
                ->has('posts', 2)
            );
    }

    public function test_future_scheduled_posts_are_hidden_until_due(): void
    {
        [$user] = $this->clientUserWithClient();
        $this->publishedQuote('Visible now.', null);
        BoardPost::query()->create([
            'type' => BoardPost::TYPE_QUOTE,
            'body' => 'Visible later.',
            'status' => BoardPost::STATUS_PUBLISHED,
            'pinned' => false,
            'published_at' => now(),
            'scheduled_at' => now()->addWeek(),
        ]);

        $this->actingAsMfa($user)
            ->get(route('portal.inspiration-board.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/inspiration-board/Index')
                ->has('posts', 1)
                ->where('posts.0.body', 'Visible now.')
            );

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/Dashboard')
                ->where('inspirationBoard.body', 'Visible now.')
            );
    }

    public function test_image_route_serves_published_image_but_404s_a_draft_for_clients(): void
    {
        $admin = $this->superAdmin();
        [$user] = $this->clientUserWithClient();

        $published = app(InspirationBoard::class)->create(
            ['type' => BoardPost::TYPE_IMAGE, 'title' => 'Published'],
            UploadedFile::fake()->image('published.jpg', 320, 240),
            $admin,
        );
        app(InspirationBoard::class)->publish($published, $admin);

        $draft = app(InspirationBoard::class)->create(
            ['type' => BoardPost::TYPE_IMAGE, 'title' => 'Draft'],
            UploadedFile::fake()->image('draft.jpg', 320, 240),
            $admin,
        );

        $this->actingAsMfa($user)
            ->get(route('portal.inspiration-board.image', $published))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAsMfa($user)
            ->get(route('portal.inspiration-board.image', $draft))
            ->assertNotFound();
    }

    public function test_image_route_404s_future_scheduled_image_for_clients(): void
    {
        $admin = $this->superAdmin();
        [$user] = $this->clientUserWithClient();

        $post = app(InspirationBoard::class)->create(
            ['type' => BoardPost::TYPE_IMAGE, 'title' => 'Scheduled image'],
            UploadedFile::fake()->image('scheduled.jpg', 320, 240),
            $admin,
        );
        $post->forceFill([
            'status' => BoardPost::STATUS_PUBLISHED,
            'published_at' => now(),
            'scheduled_at' => now()->addWeek(),
        ])->save();

        $this->actingAsMfa($user)
            ->get(route('portal.inspiration-board.image', $post))
            ->assertNotFound();

        $this->actingAsMfa($admin)
            ->get(route('portal.inspiration-board.image', $post))
            ->assertOk();
    }

    private function publishedQuote(string $body, ?string $attribution): BoardPost
    {
        return BoardPost::query()->create([
            'type' => BoardPost::TYPE_QUOTE,
            'body' => $body,
            'attribution' => $attribution,
            'status' => BoardPost::STATUS_PUBLISHED,
            'pinned' => false,
            'published_at' => now(),
        ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);
        app(RequestContext::class)->apply('system', []);

        return $user;
    }

    private function entrepreneurUser(): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $user->assignRole(User::TYPE_ENTREPRENEUR);

        return $user;
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUserWithClient(): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'Board Viewer',
            'email' => 'board.viewer@example.com',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000031',
            'legal_name' => 'Inspiration Board Test Limited',
            'trading_name' => 'Board Co',
            'entity_type' => 'NZ Limited Company',
            'gst_registered' => true,
            'filing_status' => 'registered',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$user, $client];
    }
}
