<?php

declare(strict_types=1);

namespace App\Services\Board;

use App\Models\BoardPost;
use App\Models\Document;
use App\Models\InspirationRotationSchedule;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Storage\SecureFileWriter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class InspirationBoard
{
    public const ROTATION_TIMEZONE = 'Pacific/Auckland';

    public function __construct(
        private readonly SecureFileWriter $files,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * The post to feature on portal dashboards: the pinned published post, else
     * the most recently published one.
     */
    public function featured(): ?BoardPost
    {
        return BoardPost::query()
            ->released()
            ->where(function ($query): void {
                $query
                    ->whereNull('featured_at')
                    ->orWhere('featured_at', '<=', now());
            })
            ->orderByDesc('pinned')
            ->orderByDesc('featured_at')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return Collection<int, BoardPost>
     */
    public function feed(int $limit = 30): Collection
    {
        return BoardPost::query()
            ->released()
            ->orderByDesc('pinned')
            ->orderByRaw('scheduled_at is null')
            ->orderByDesc('scheduled_at')
            ->orderByDesc('featured_at')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, BoardPost>
     */
    public function library(int $limit = 200): Collection
    {
        return BoardPost::query()
            ->with('createdBy', 'imageDocument')
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, InspirationRotationSchedule>
     */
    public function rotationSchedules(int $limit = 50): Collection
    {
        return InspirationRotationSchedule::query()
            ->with(['posts' => fn ($query) => $query->select(['board_posts.id', 'board_posts.title', 'board_posts.body'])])
            ->withCount('posts')
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{type:string, title?:?string, body?:?string, attribution?:?string, scheduled_at?:mixed}  $data
     */
    public function create(array $data, ?UploadedFile $image, User $actor): BoardPost
    {
        $type = $data['type'];

        $imageAttributes = [];
        if ($type === BoardPost::TYPE_IMAGE) {
            if (! $image instanceof UploadedFile) {
                throw new RuntimeException('An image file is required for image posts.');
            }

            $document = $this->files->write($image, $actor, Document::CATEGORY_INSPIRATION_IMAGE);

            $imageAttributes = [
                'image_document_id' => $document->getKey(),
                'image_path' => $document->stored_path,
                'image_mime' => $document->mime_type,
                'image_filename' => $document->original_filename,
            ];
        }

        return DB::transaction(function () use ($type, $data, $imageAttributes, $actor): BoardPost {
            $post = BoardPost::query()->create([
                'type' => $type,
                'title' => $this->trimToNull($data['title'] ?? null),
                'body' => $this->trimToNull($data['body'] ?? null),
                'attribution' => $this->trimToNull($data['attribution'] ?? null),
                'status' => BoardPost::STATUS_DRAFT,
                'pinned' => false,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'created_by_user_id' => $actor->getAuthIdentifier(),
                ...$imageAttributes,
            ]);

            $this->audit->record('board_post.created', subject: $post, actor: $actor, after: [
                'type' => $post->type,
                'status' => $post->status,
                'scheduled_at' => $post->scheduled_at?->toIso8601String(),
            ]);

            return $post;
        });
    }

    /**
     * @param  array{title?:?string, body?:?string, attribution?:?string, scheduled_at?:mixed}  $data
     */
    public function update(BoardPost $post, array $data, User $actor): BoardPost
    {
        $controlledByRotation = $post->rotationSchedules()
            ->where('status', InspirationRotationSchedule::STATUS_SCHEDULED)
            ->exists();
        $this->ensureScheduledDateIsEditable($post, $data);

        $before = [
            'title' => $post->title,
            'body' => $post->body,
            'attribution' => $post->attribution,
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
        ];

        $attributes = [
            'title' => $this->trimToNull($data['title'] ?? $post->title),
            'body' => $this->trimToNull($data['body'] ?? $post->body),
            'attribution' => $this->trimToNull($data['attribution'] ?? $post->attribution),
            'scheduled_at' => array_key_exists('scheduled_at', $data) ? $data['scheduled_at'] : $post->scheduled_at,
        ];

        if ($post->isPublished() && ! $controlledByRotation) {
            $attributes['featured_at'] = $attributes['scheduled_at'] ?? now();
            $attributes['featured_source'] = BoardPost::FEATURE_SOURCE_MANUAL;
        }

        $post->forceFill($attributes)->save();

        $this->audit->record('board_post.updated', subject: $post, actor: $actor, before: $before, after: [
            'type' => $post->type,
            'title' => $post->title,
            'body' => $post->body,
            'attribution' => $post->attribution,
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
        ]);

        return $post;
    }

    public function publish(BoardPost $post, User $actor): BoardPost
    {
        if ($post->isImage() && $post->image_path === null) {
            throw new RuntimeException('Image posts require a stored, scanned image before publishing.');
        }

        if ($post->isImage() && ! $this->imageDocumentIsClean($post)) {
            throw new RuntimeException('Image posts cannot be published until malware scanning is complete.');
        }

        $post->forceFill([
            'status' => BoardPost::STATUS_PUBLISHED,
            'published_at' => $post->published_at ?? now(),
            'featured_at' => $post->scheduled_at ?? now(),
            'featured_source' => BoardPost::FEATURE_SOURCE_MANUAL,
        ])->save();

        $this->audit->record('board_post.published', subject: $post, actor: $actor, after: [
            'type' => $post->type,
            'published_at' => $post->published_at?->toIso8601String(),
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
        ]);

        return $post;
    }

    /**
     * @param  list<string>  $postIds
     */
    public function createRotation(
        string $name,
        CarbonInterface $startAt,
        int $cadenceDays,
        array $postIds,
        User $actor,
    ): InspirationRotationSchedule {
        $postIds = array_values(array_unique(array_filter($postIds, 'is_string')));
        if ($postIds === []) {
            throw new RuntimeException('Select at least one published quote for the rotation.');
        }

        if ($startAt->lt(now())) {
            throw new RuntimeException('A rotation schedule must start now or in the future.');
        }

        $endsAt = $startAt->copy()->addDays($cadenceDays * (count($postIds) - 1));
        $name = $this->trimToNull($name) ?? 'Rotation starting '.$startAt->format('j M Y');

        try {
            return DB::transaction(function () use ($cadenceDays, $actor, $endsAt, $name, $postIds, $startAt): InspirationRotationSchedule {
                $overlapExists = InspirationRotationSchedule::query()
                    ->where('status', InspirationRotationSchedule::STATUS_SCHEDULED)
                    ->where('starts_at', '<=', $endsAt)
                    ->where('ends_at', '>=', $startAt)
                    ->lockForUpdate()
                    ->exists();

                if ($overlapExists) {
                    throw new RuntimeException('This rotation overlaps an existing schedule. Choose a start date after the current schedule ends.');
                }

                $availablePosts = BoardPost::query()
                    ->released()
                    ->whereIn('id', $postIds)
                    ->where('type', BoardPost::TYPE_QUOTE)
                    ->where('pinned', false)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(fn (BoardPost $post): string => (string) $post->getKey());

                if ($availablePosts->count() !== count($postIds)) {
                    throw new RuntimeException('One or more selected quotes are no longer available for rotation. Refresh the page and try again.');
                }

                $schedule = InspirationRotationSchedule::query()->create([
                    'name' => $name,
                    'status' => InspirationRotationSchedule::STATUS_SCHEDULED,
                    'starts_at' => $startAt,
                    'ends_at' => $endsAt,
                    'cadence_days' => $cadenceDays,
                    'created_by_user_id' => $actor->getAuthIdentifier(),
                ]);

                foreach ($postIds as $index => $postId) {
                    /** @var BoardPost $post */
                    $post = $availablePosts->get($postId);
                    $scheduledAt = $startAt->copy()->addDays($cadenceDays * $index);

                    $schedule->posts()->attach($post->getKey(), [
                        'position' => $index + 1,
                        'scheduled_at' => $scheduledAt,
                        'featured_at' => null,
                    ]);
                }

                $this->audit->record('inspiration_rotation.created', subject: $schedule, actor: $actor, after: [
                    'name' => $schedule->name,
                    'starts_at' => $schedule->starts_at?->toIso8601String(),
                    'ends_at' => $schedule->ends_at?->toIso8601String(),
                    'cadence_days' => $schedule->cadence_days,
                    'post_count' => count($postIds),
                ]);

                return $schedule;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'inspiration_rotation_schedule_windows_no_overlap')) {
                throw new RuntimeException('This rotation overlaps an existing schedule. Choose a start date after the current schedule ends.', previous: $exception);
            }

            throw $exception;
        }
    }

    public function cancelRotation(InspirationRotationSchedule $schedule, User $actor): int
    {
        if ($schedule->status === InspirationRotationSchedule::STATUS_CANCELLED) {
            return 0;
        }

        return DB::transaction(function () use ($actor, $schedule): int {
            $schedule->load('posts');
            $now = now();
            $featuredCount = $schedule->posts
                ->filter(fn (BoardPost $post): bool => $post->pivot->featured_at !== null)
                ->count();

            $schedule->forceFill([
                'status' => InspirationRotationSchedule::STATUS_CANCELLED,
                'cancelled_at' => $now,
            ])->save();

            $this->audit->record('inspiration_rotation.cancelled', subject: $schedule, actor: $actor, after: [
                'featured_count' => $featuredCount,
                'unfeatured_count' => $schedule->posts->count() - $featuredCount,
            ]);

            return $featuredCount;
        });
    }

    public function releaseDueRotations(?CarbonInterface $at = null): int
    {
        $at ??= now();

        return DB::transaction(function () use ($at): int {
            $schedules = InspirationRotationSchedule::query()
                ->where('status', InspirationRotationSchedule::STATUS_SCHEDULED)
                ->where('starts_at', '<=', $at)
                ->with(['posts' => function ($query) use ($at): void {
                    $query
                        ->where('board_posts.status', BoardPost::STATUS_PUBLISHED)
                        ->where('inspiration_rotation_schedule_posts.scheduled_at', '<=', $at)
                        ->whereNull('inspiration_rotation_schedule_posts.featured_at');
                }])
                ->lockForUpdate()
                ->get();

            $featured = 0;
            foreach ($schedules as $schedule) {
                foreach ($schedule->posts as $post) {
                    $scheduledAt = CarbonImmutable::parse((string) $post->pivot->scheduled_at);

                    $schedule->posts()->updateExistingPivot($post->getKey(), [
                        'featured_at' => $at,
                    ]);
                    $post->forceFill([
                        'featured_at' => $scheduledAt,
                        'featured_source' => BoardPost::FEATURE_SOURCE_ROTATION,
                    ])->save();

                    $this->audit->record('board_post.rotation_featured', subject: $post, after: [
                        'rotation_schedule_id' => $schedule->getKey(),
                        'scheduled_at' => $scheduledAt->toIso8601String(),
                    ]);
                    $featured++;
                }
            }

            return $featured;
        }, attempts: 3);
    }

    public function selectWeeklyFallbackQuote(?CarbonInterface $at = null): ?BoardPost
    {
        $at ??= now();
        $weekStart = $this->weeklyFallbackWindowStart($at);

        return DB::transaction(function () use ($at, $weekStart): ?BoardPost {
            $rotationActive = InspirationRotationSchedule::query()
                ->where('status', InspirationRotationSchedule::STATUS_SCHEDULED)
                ->where('starts_at', '<=', $at)
                ->where('ends_at', '>=', $at)
                ->exists();

            if ($rotationActive) {
                return null;
            }

            $manualPostPinned = BoardPost::query()
                ->released()
                ->where('pinned', true)
                ->exists();

            if ($manualPostPinned) {
                return null;
            }

            $alreadySelected = BoardPost::query()
                ->where('featured_source', BoardPost::FEATURE_SOURCE_FALLBACK)
                ->where('featured_at', '>=', $weekStart)
                ->exists();

            if ($alreadySelected) {
                return null;
            }

            $post = BoardPost::query()
                ->released()
                ->where('type', BoardPost::TYPE_QUOTE)
                ->whereDoesntHave('rotationSchedules', function ($query) use ($at): void {
                    $query
                        ->where('status', InspirationRotationSchedule::STATUS_SCHEDULED)
                        ->where('ends_at', '>=', $at);
                })
                ->inRandomOrder()
                ->lockForUpdate()
                ->first();

            if (! $post instanceof BoardPost) {
                return null;
            }

            $post->forceFill([
                'featured_at' => $at,
                'featured_source' => BoardPost::FEATURE_SOURCE_FALLBACK,
            ])->save();

            $this->audit->record('board_post.weekly_fallback_featured', subject: $post, after: [
                'featured_at' => $post->featured_at?->toIso8601String(),
            ]);

            return $post;
        }, attempts: 3);
    }

    public function archive(BoardPost $post, User $actor): BoardPost
    {
        $post->forceFill([
            'status' => BoardPost::STATUS_ARCHIVED,
            'pinned' => false,
        ])->save();

        $this->audit->record('board_post.archived', subject: $post, actor: $actor, after: [
            'type' => $post->type,
        ]);

        return $post;
    }

    public function pin(BoardPost $post, User $actor): BoardPost
    {
        if (! $post->isReleased()) {
            throw new RuntimeException('Only released posts can be pinned.');
        }

        return DB::transaction(function () use ($post, $actor): BoardPost {
            BoardPost::query()->where('pinned', true)->update(['pinned' => false]);

            $post->forceFill(['pinned' => true])->save();

            $this->audit->record('board_post.pinned', subject: $post, actor: $actor, after: [
                'type' => $post->type,
            ]);

            return $post;
        });
    }

    public function unpin(BoardPost $post, User $actor): BoardPost
    {
        $post->forceFill(['pinned' => false])->save();

        $this->audit->record('board_post.unpinned', subject: $post, actor: $actor, after: [
            'type' => $post->type,
        ]);

        return $post;
    }

    /**
     * Client-facing payload — never leaks the encrypted storage path.
     *
     * @return array<string, mixed>
     */
    public function portalPayload(BoardPost $post): array
    {
        return [
            'id' => $post->id,
            'type' => $post->type,
            'title' => $post->title,
            'body' => $post->body,
            'attribution' => $post->attribution,
            'image_url' => $post->isImage()
                ? route('portal.inspiration-board.image', $post, absolute: false)
                : null,
            'published_at' => $post->published_at?->toIso8601String(),
        ];
    }

    private function trimToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array{scheduled_at?:mixed}  $data
     */
    private function ensureScheduledDateIsEditable(BoardPost $post, array $data): void
    {
        if (! array_key_exists('scheduled_at', $data)) {
            return;
        }

        $hasScheduledRotation = $post->rotationSchedules()
            ->where('status', InspirationRotationSchedule::STATUS_SCHEDULED)
            ->exists();

        if (! $hasScheduledRotation) {
            return;
        }

        $incomingValue = $data['scheduled_at'];
        $incoming = $incomingValue === null || $incomingValue === ''
            ? null
            : ($incomingValue instanceof CarbonInterface
                ? $incomingValue
                : CarbonImmutable::parse((string) $incomingValue, self::ROTATION_TIMEZONE));

        if (($post->scheduled_at === null && $incoming === null)
            || ($post->scheduled_at !== null && $incoming !== null && $post->scheduled_at->equalTo($incoming))) {
            return;
        }

        throw new RuntimeException('This quote is controlled by an active rotation. Cancel the rotation before changing its release date.');
    }

    private function weeklyFallbackWindowStart(CarbonInterface $at): CarbonImmutable
    {
        $local = CarbonImmutable::instance($at)->setTimezone(self::ROTATION_TIMEZONE);
        $windowStart = $local->startOfWeek(CarbonInterface::MONDAY)->setTime(6, 0);

        if ($local->lt($windowStart)) {
            $windowStart = $windowStart->subWeek();
        }

        return $windowStart->utc();
    }

    private function imageDocumentIsClean(BoardPost $post): bool
    {
        $document = $post->relationLoaded('imageDocument')
            ? $post->imageDocument
            : (
                $post->image_document_id === null
                    ? null
                    : Document::query()->find((string) $post->image_document_id)
            );

        if (! $document instanceof Document) {
            return $post->image_path !== null;
        }

        return $document->scanner_result === Document::SCANNER_CLEAN;
    }
}
