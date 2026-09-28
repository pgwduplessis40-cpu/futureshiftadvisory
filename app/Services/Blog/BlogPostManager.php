<?php

declare(strict_types=1);

namespace App\Services\Blog;

use App\Models\BlogPost;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BlogPostManager
{
    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array{title?:string|null,slug?:string|null,description?:string|null,body?:string|null}  $attributes
     */
    public function create(array $attributes, User $actor): BlogPost
    {
        return DB::transaction(function () use ($attributes, $actor): BlogPost {
            return $this->createLocked($attributes, $actor);
        });
    }

    /**
     * @param  list<array{title:string,slug:string,description:string,body:string}>  $imports
     * @param  array<int, CarbonInterface>  $scheduledAt
     * @return Collection<int, BlogPost>
     */
    public function createMany(array $imports, User $actor, array $scheduledAt = []): Collection
    {
        return DB::transaction(function () use ($imports, $actor, $scheduledAt): Collection {
            $posts = collect();

            foreach ($imports as $index => $attributes) {
                $post = $this->createLocked($attributes, $actor);
                $schedule = $scheduledAt[$index] ?? null;

                if ($schedule instanceof CarbonInterface) {
                    $this->scheduleLocked($post, $schedule, $actor);
                }

                $posts->push($post);
            }

            return $posts;
        });
    }

    /**
     * @param  array{title?:string|null,slug?:string|null,description?:string|null,body?:string|null}  $attributes
     */
    public function saveWorking(BlogPost $post, array $attributes, User $actor): BlogPost
    {
        return DB::transaction(function () use ($post, $attributes, $actor): BlogPost {
            $locked = $this->locked($post);
            $working = $this->workingAttributes($attributes, $locked);
            $this->ensureSlugIsUnlocked($locked, $working['slug']);
            $before = $this->metadata($locked);

            $locked->forceFill($working)->save();
            $this->audit->record('blog_post.working_saved', subject: $locked, actor: $actor, before: $before, after: $this->metadata($locked));

            return $locked;
        });
    }

    /**
     * @param  array{title?:string|null,slug?:string|null,description?:string|null,body?:string|null}  $attributes
     */
    public function publish(BlogPost $post, User $actor, array $attributes = []): BlogPost
    {
        return DB::transaction(function () use ($post, $actor, $attributes): BlogPost {
            $locked = $this->locked($post);
            $working = $this->workingAttributes($attributes, $locked);
            $this->ensureSlugIsUnlocked($locked, $working['slug']);
            $before = $this->metadata($locked);
            $locked->forceFill($working);
            $this->ensurePublishable($locked);
            $now = now();

            $locked->forceFill([
                'status' => BlogPost::STATUS_PUBLISHED,
                'published_title' => $working['title'],
                'published_description' => $working['description'],
                'published_body' => $working['body'],
                'published_at' => $locked->published_at ?? $now,
                'published_revision_at' => $now,
                'scheduled_title' => null,
                'scheduled_description' => null,
                'scheduled_body' => null,
                'scheduled_at' => null,
            ])->save();

            $this->audit->record('blog_post.published', subject: $locked, actor: $actor, before: $before, after: $this->metadata($locked));

            return $locked;
        });
    }

    /**
     * @param  array{title?:string|null,slug?:string|null,description?:string|null,body?:string|null}  $attributes
     */
    public function schedule(BlogPost $post, CarbonInterface $scheduledAt, User $actor, array $attributes = []): BlogPost
    {
        return DB::transaction(function () use ($post, $scheduledAt, $actor, $attributes): BlogPost {
            $locked = $this->locked($post);
            $working = $this->workingAttributes($attributes, $locked);
            $this->ensureSlugIsUnlocked($locked, $working['slug']);
            $locked->forceFill($working);
            $this->ensurePublishable($locked);

            return $this->scheduleLocked($locked, $scheduledAt, $actor);
        });
    }

    public function cancelSchedule(BlogPost $post, User $actor): BlogPost
    {
        return DB::transaction(function () use ($post, $actor): BlogPost {
            $locked = $this->locked($post);
            $this->ensureScheduled($locked);
            $before = $this->metadata($locked);

            $locked->forceFill([
                'status' => BlogPost::STATUS_DRAFT,
                'scheduled_title' => null,
                'scheduled_description' => null,
                'scheduled_body' => null,
                'scheduled_at' => null,
            ])->save();

            $this->audit->record('blog_post.schedule_cancelled', subject: $locked, actor: $actor, before: $before, after: $this->metadata($locked));

            return $locked;
        });
    }

    public function publishDue(?CarbonInterface $at = null): int
    {
        $at ??= now();

        return DB::transaction(function () use ($at): int {
            $posts = BlogPost::query()
                ->where('status', BlogPost::STATUS_SCHEDULED)
                ->where('scheduled_at', '<=', $at)
                ->lockForUpdate()
                ->get();

            foreach ($posts as $post) {
                $this->ensureScheduledSnapshot($post);
                $before = $this->metadata($post);
                $publishedAt = $post->published_at ?? $post->scheduled_at ?? $at;

                $post->forceFill([
                    'status' => BlogPost::STATUS_PUBLISHED,
                    'published_title' => $post->scheduled_title,
                    'published_description' => $post->scheduled_description,
                    'published_body' => $post->scheduled_body,
                    'published_at' => $publishedAt,
                    'published_revision_at' => $at,
                    'scheduled_title' => null,
                    'scheduled_description' => null,
                    'scheduled_body' => null,
                    'scheduled_at' => null,
                ])->save();

                $this->audit->record('blog_post.scheduled_published', subject: $post, actor: null, before: $before, after: $this->metadata($post));
            }

            return $posts->count();
        });
    }

    public function unpublish(BlogPost $post, User $actor): BlogPost
    {
        return DB::transaction(function () use ($post, $actor): BlogPost {
            $locked = $this->locked($post);
            if ($locked->isScheduled()) {
                throw ValidationException::withMessages([
                    'post' => 'Cancel the publication schedule instead.',
                ]);
            }
            $before = $this->metadata($locked);

            $locked->forceFill(['status' => BlogPost::STATUS_DRAFT])->save();
            $this->audit->record('blog_post.unpublished', subject: $locked, actor: $actor, before: $before, after: $this->metadata($locked));

            return $locked;
        });
    }

    public function delete(BlogPost $post, User $actor): void
    {
        DB::transaction(function () use ($post, $actor): void {
            $locked = $this->locked($post);
            $before = $this->metadata($locked);
            $locked->delete();

            $this->audit->record('blog_post.deleted', subject: $locked, actor: $actor, before: $before);
        });
    }

    /**
     * @param  array{title?:string|null,slug?:string|null,description?:string|null,body?:string|null}  $attributes
     * @return array{title:string,slug:string,description:string,body:string}
     */
    private function workingAttributes(array $attributes, ?BlogPost $existing = null): array
    {
        // `null` is a deliberate draft value (Laravel converts an empty input
        // string to null). Check key presence rather than using `??`, otherwise
        // an editor can never clear an existing field to return a post to draft.
        $title = $this->normalise(array_key_exists('title', $attributes) ? $attributes['title'] : $existing?->title);
        $slug = $this->normalise(array_key_exists('slug', $attributes) ? $attributes['slug'] : $existing?->slug);

        if ($slug === '' && $title !== '') {
            $slug = Str::slug($title);
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'description' => $this->normalise(array_key_exists('description', $attributes) ? $attributes['description'] : $existing?->description),
            'body' => $this->normaliseMarkdown(array_key_exists('body', $attributes) ? $attributes['body'] : $existing?->body),
        ];
    }

    private function ensureSlugIsUnlocked(BlogPost $post, string $slug): void
    {
        if ($post->isSlugLocked() && $post->slug !== $slug) {
            throw ValidationException::withMessages([
                'slug' => 'The slug is frozen after scheduling or first publication.',
            ]);
        }
    }

    /**
     * @param  array{title?:string|null,slug?:string|null,description?:string|null,body?:string|null}  $attributes
     */
    private function createLocked(array $attributes, User $actor): BlogPost
    {
        $post = BlogPost::query()->create([
            ...$this->workingAttributes($attributes),
            'status' => BlogPost::STATUS_DRAFT,
            'author_id' => $actor->getAuthIdentifier(),
        ]);

        $this->audit->record('blog_post.created', subject: $post, actor: $actor, after: $this->metadata($post));

        return $post;
    }

    private function scheduleLocked(BlogPost $post, CarbonInterface $scheduledAt, User $actor): BlogPost
    {
        if ($post->isPublished()) {
            throw ValidationException::withMessages([
                'post' => 'Unpublish this post before scheduling it.',
            ]);
        }

        if ($scheduledAt->lte(now())) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Choose a future publication date and time.',
            ]);
        }

        $before = $this->metadata($post);
        $post->forceFill([
            'status' => BlogPost::STATUS_SCHEDULED,
            'scheduled_title' => $post->title,
            'scheduled_description' => $post->description,
            'scheduled_body' => $post->body,
            'scheduled_at' => $scheduledAt,
        ])->save();

        $this->audit->record('blog_post.scheduled', subject: $post, actor: $actor, before: $before, after: $this->metadata($post));

        return $post;
    }

    private function ensureScheduled(BlogPost $post): void
    {
        if (! $post->isScheduled()) {
            throw ValidationException::withMessages([
                'post' => 'This post does not have an active publication schedule.',
            ]);
        }
    }

    private function ensureScheduledSnapshot(BlogPost $post): void
    {
        if ($post->scheduled_at === null
            || $post->scheduled_title === null
            || $post->scheduled_description === null
            || $post->scheduled_body === null) {
            throw ValidationException::withMessages([
                'post' => 'The scheduled post is missing its approved publication snapshot.',
            ]);
        }
    }

    private function ensurePublishable(BlogPost $post): void
    {
        $errors = [];
        foreach (['slug', 'title', 'description', 'body'] as $field) {
            if (trim((string) $post->getAttribute($field)) === '') {
                $errors[$field] = "A {$field} is required before publishing.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function locked(BlogPost $post): BlogPost
    {
        return BlogPost::query()
            ->lockForUpdate()
            ->findOrFail($post->getKey());
    }

    /**
     * @return array{slug:string,status:string,published_at:string|null,published_revision_at:string|null,scheduled_at:string|null}
     */
    private function metadata(BlogPost $post): array
    {
        return [
            'slug' => $post->slug,
            'status' => $post->status,
            'published_at' => $post->published_at?->toIso8601String(),
            'published_revision_at' => $post->published_revision_at?->toIso8601String(),
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
        ];
    }

    private function normalise(?string $value): string
    {
        return trim((string) $value);
    }

    private function normaliseMarkdown(?string $value): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
    }
}
