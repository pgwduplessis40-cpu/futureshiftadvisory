<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BoardPost;
use App\Models\Document;
use App\Models\InspirationRotationSchedule;
use App\Models\User;
use App\Services\Board\InspirationBoard;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\ScanResult;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class InspirationBoardManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');
        Config::set('filesystems.disks.secure_local.root', storage_path('framework/testing/board-secure'));
    }

    public function test_super_admin_can_create_publish_and_pin(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.inspiration-board.store'), [
                'type' => BoardPost::TYPE_QUOTE,
                'body' => 'Keep going — steady work compounds.',
                'attribution' => 'FSA',
            ])
            ->assertRedirect(route('admin.inspiration-board.index', absolute: false));

        $post = BoardPost::query()->firstOrFail();
        $this->assertSame(BoardPost::TYPE_QUOTE, $post->type);
        $this->assertSame(BoardPost::STATUS_DRAFT, $post->status);
        $this->assertFalse($post->pinned);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'board_post.created',
            'subject_id' => $post->id,
        ]);

        $this->actingAsMfa($admin)
            ->post(route('admin.inspiration-board.publish', $post))
            ->assertRedirect();

        $post->refresh();
        $this->assertSame(BoardPost::STATUS_PUBLISHED, $post->status);
        $this->assertNotNull($post->published_at);

        // A second published post, then pin each — pinning must keep only one pinned.
        $second = app(InspirationBoard::class)->create(
            ['type' => BoardPost::TYPE_MESSAGE, 'body' => 'You are not alone in this.'],
            null,
            $admin,
        );
        app(InspirationBoard::class)->publish($second, $admin);

        $this->actingAsMfa($admin)->post(route('admin.inspiration-board.pin', $post))->assertRedirect();
        $this->actingAsMfa($admin)->post(route('admin.inspiration-board.pin', $second))->assertRedirect();

        $this->assertFalse($post->refresh()->pinned);
        $this->assertTrue($second->refresh()->pinned);
        $this->assertSame(1, BoardPost::query()->where('pinned', true)->count());
    }

    public function test_super_admin_can_edit_quote_details_and_schedule(): void
    {
        $admin = $this->superAdmin();
        $scheduledAt = now(InspirationBoard::ROTATION_TIMEZONE)->addDays(5)->setSecond(0);

        $post = BoardPost::query()->create([
            'type' => BoardPost::TYPE_QUOTE,
            'body' => 'Old quote',
            'status' => BoardPost::STATUS_DRAFT,
            'pinned' => false,
            'created_by_user_id' => $admin->getKey(),
        ]);

        $this->actingAsMfa($admin)
            ->patch(route('admin.inspiration-board.update', $post), [
                'title' => 'Updated title',
                'body' => 'Updated quote',
                'attribution' => 'Future Shift',
                'scheduled_at' => $scheduledAt->toDateTimeString(),
            ])
            ->assertRedirect();

        $post->refresh();

        $this->assertSame('Updated title', $post->title);
        $this->assertSame('Updated quote', $post->body);
        $this->assertSame('Future Shift', $post->attribution);
        $this->assertTrue($post->scheduled_at?->equalTo($scheduledAt));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'board_post.updated',
            'subject_id' => $post->id,
        ]);
    }

    public function test_super_admin_can_schedule_selected_published_quotes_with_custom_day_cadence(): void
    {
        $admin = $this->superAdmin();
        $startAt = now(InspirationBoard::ROTATION_TIMEZONE)->addDay()->setSecond(0);

        $first = BoardPost::query()->create([
            'type' => BoardPost::TYPE_QUOTE,
            'body' => 'First published quote',
            'status' => BoardPost::STATUS_PUBLISHED,
            'pinned' => false,
            'published_at' => now(),
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);
        $second = BoardPost::query()->create([
            'type' => BoardPost::TYPE_QUOTE,
            'body' => 'Second published quote',
            'status' => BoardPost::STATUS_PUBLISHED,
            'pinned' => false,
            'published_at' => now(),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        $notSelected = BoardPost::query()->create([
            'type' => BoardPost::TYPE_QUOTE,
            'body' => 'Published but not selected',
            'status' => BoardPost::STATUS_PUBLISHED,
            'pinned' => false,
            'published_at' => now(),
        ]);

        $this->actingAsMfa($admin)
            ->post(route('admin.inspiration-board.schedule-rotation'), [
                'name' => 'Founder series',
                'start_at' => $startAt->toDateTimeString(),
                'cadence_days' => 10,
                'post_ids' => [$second->id, $first->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'board-rotation-scheduled');

        $first->refresh();
        $second->refresh();
        $notSelected->refresh();

        $this->assertSame(BoardPost::STATUS_PUBLISHED, $first->status);
        $this->assertSame(BoardPost::STATUS_PUBLISHED, $second->status);
        $this->assertNull($first->scheduled_at);
        $this->assertNull($second->scheduled_at);
        $this->assertSame(BoardPost::STATUS_PUBLISHED, $notSelected->status);

        $schedule = InspirationRotationSchedule::query()->firstOrFail();
        $this->assertSame('Founder series', $schedule->name);
        $this->assertSame(2, $schedule->posts()->count());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'inspiration_rotation.created',
        ]);

        $this->assertSame(1, app(InspirationBoard::class)->releaseDueRotations($startAt));
        $this->assertTrue($second->refresh()->featured_at?->equalTo($startAt));
        $this->assertSame(BoardPost::FEATURE_SOURCE_ROTATION, $second->featured_source);
        $this->assertSame(1, app(InspirationBoard::class)->releaseDueRotations($startAt->copy()->addDays(10)));
        $this->assertTrue($first->refresh()->featured_at?->equalTo($startAt->copy()->addDays(10)));

        $this->actingAsMfa($admin)
            ->post(route('admin.inspiration-board.schedule-rotation'), [
                'name' => 'Overlapping series',
                'start_at' => $startAt->copy()->addDays(5)->toDateTimeString(),
                'cadence_days' => 7,
                'post_ids' => [$notSelected->id],
            ])
            ->assertSessionHasErrors('post_ids');

        $this->assertSame(1, InspirationRotationSchedule::query()->count());
        $this->assertSame(BoardPost::STATUS_PUBLISHED, $notSelected->refresh()->status);
    }

    public function test_super_admin_can_cancel_a_rotation_without_unpublishing_its_quotes(): void
    {
        $admin = $this->superAdmin();
        $startAt = now(InspirationBoard::ROTATION_TIMEZONE)->addWeek()->setSecond(0);
        $post = BoardPost::query()->create([
            'type' => BoardPost::TYPE_QUOTE,
            'body' => 'Future published quote',
            'status' => BoardPost::STATUS_PUBLISHED,
            'pinned' => false,
            'published_at' => now(),
        ]);

        $this->actingAsMfa($admin)
            ->post(route('admin.inspiration-board.schedule-rotation'), [
                'start_at' => $startAt->toDateTimeString(),
                'cadence_days' => 7,
                'post_ids' => [$post->id],
            ])
            ->assertRedirect();

        $schedule = InspirationRotationSchedule::query()->firstOrFail();

        $this->actingAsMfa($admin)
            ->delete(route('admin.inspiration-board.schedule-rotation.cancel', $schedule))
            ->assertRedirect()
            ->assertSessionHas('status', 'board-rotation-cancelled');

        $this->assertSame(InspirationRotationSchedule::STATUS_CANCELLED, $schedule->refresh()->status);
        $this->assertSame(BoardPost::STATUS_PUBLISHED, $post->refresh()->status);
        $this->assertNull($post->scheduled_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'inspiration_rotation.cancelled',
            'subject_id' => $schedule->id,
        ]);
    }

    public function test_image_post_is_scanned_stored_and_referenced(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.inspiration-board.store'), [
                'type' => BoardPost::TYPE_IMAGE,
                'title' => 'Sunrise over the Waikato',
                'image' => UploadedFile::fake()->image('sunrise.jpg', 640, 480),
            ])
            ->assertRedirect();

        $post = BoardPost::query()->where('type', BoardPost::TYPE_IMAGE)->firstOrFail();
        $this->assertNotNull($post->image_document_id);
        $this->assertNotNull($post->image_path);
        $this->assertNotNull($post->image_mime);

        $this->assertDatabaseHas('documents', [
            'id' => $post->image_document_id,
            'category' => Document::CATEGORY_INSPIRATION_IMAGE,
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
        Storage::disk('secure_local')->assertExists($post->image_path);
    }

    public function test_scanner_error_image_post_is_saved_as_quarantined_draft_and_cannot_publish(): void
    {
        $this->app->bind(FileScanner::class, fn (): FileScanner => new class implements FileScanner
        {
            public function scan(mixed $stream): ScanResult
            {
                return ScanResult::error('daemon offline', ['engine' => 'fake-clamav']);
            }
        });

        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.inspiration-board.store'), [
                'type' => BoardPost::TYPE_IMAGE,
                'title' => 'Quarantined image',
                'image' => UploadedFile::fake()->image('sunrise.jpg', 640, 480),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $post = BoardPost::query()->where('type', BoardPost::TYPE_IMAGE)->firstOrFail();
        $document = Document::query()->firstOrFail();

        $this->assertSame(BoardPost::STATUS_DRAFT, $post->status);
        $this->assertSame($document->id, $post->image_document_id);
        $this->assertSame(Document::SCANNER_ERROR, $document->scanner_result);
        $this->assertStringStartsWith('quarantine/inspiration_image/', $document->stored_path);

        $this->actingAsMfa($admin)
            ->get(route('portal.inspiration-board.image', $post))
            ->assertNotFound();

        $this->actingAsMfa($admin)
            ->post(route('admin.inspiration-board.publish', $post))
            ->assertSessionHasErrors('post');

        $this->assertSame(BoardPost::STATUS_DRAFT, $post->refresh()->status);
    }

    public function test_image_type_requires_a_file(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.inspiration-board.store'), [
                'type' => BoardPost::TYPE_IMAGE,
                'title' => 'No file',
            ])
            ->assertSessionHasErrors('image');

        $this->assertSame(0, BoardPost::query()->count());
    }

    public function test_non_super_admin_cannot_manage(): void
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $this->actingAsMfa($advisor)
            ->get(route('admin.inspiration-board.index'))
            ->assertForbidden();

        $this->actingAsMfa($advisor)
            ->post(route('admin.inspiration-board.store'), [
                'type' => BoardPost::TYPE_QUOTE,
                'body' => 'Advisors should not manage the board.',
            ])
            ->assertForbidden();

        $this->assertSame(0, BoardPost::query()->count());
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }
}
