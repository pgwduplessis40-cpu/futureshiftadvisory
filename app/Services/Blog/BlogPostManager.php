<?php

declare(strict_types=1);

namespace App\Services\Blog;

use App\Models\BlogPost;
use App\Models\User;
use App\Services\Audit\AuditWriter;
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
            $post = BlogPost::query()->create([
                ...$this->workingAttributes($attributes),
                'status' => BlogPost::STATUS_DRAFT,
                'author_id' => $actor->getAuthIdentifier(),
            ]);

            $this->audit->record('blog_post.created', subject: $post, actor: $actor, after: $this->metadata($post));

            return $post;
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

    public function publish(BlogPost $post, User $actor): BlogPost
    {
        return DB::transaction(function () use ($post, $actor): BlogPost {
            $locked = $this->locked($post);
            $this->ensurePublishable($locked);
            $before = $this->metadata($locked);
            $now = now();

            $locked->forceFill([
                'status' => BlogPost::STATUS_PUBLISHED,
                'published_title' => $locked->title,
                'published_description' => $locked->description,
                'published_body' => $locked->body,
                'published_at' => $locked->published_at ?? $now,
                'published_revision_at' => $now,
            ])->save();

            $this->audit->record('blog_post.published', subject: $locked, actor: $actor, before: $before, after: $this->metadata($locked));

            return $locked;
        });
    }

    public function unpublish(BlogPost $post, User $actor): BlogPost
    {
        return DB::transaction(function () use ($post, $actor): BlogPost {
            $locked = $this->locked($post);
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
        if ($post->hasBeenPublished() && $post->slug !== $slug) {
            throw ValidationException::withMessages([
                'slug' => 'The slug is frozen after first publication.',
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
     * @return array{slug:string,status:string,published_at:string|null,published_revision_at:string|null}
     */
    private function metadata(BlogPost $post): array
    {
        return [
            'slug' => $post->slug,
            'status' => $post->status,
            'published_at' => $post->published_at?->toIso8601String(),
            'published_revision_at' => $post->published_revision_at?->toIso8601String(),
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
