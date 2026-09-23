<?php

declare(strict_types=1);

namespace App\Services\Blog;

use App\Models\BlogPost;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class BlogPosts
{
    private const DISPLAY_TIMEZONE = 'Pacific/Auckland';

    public function __construct(private readonly BlogMarkdownRenderer $renderer) {}

    /**
     * @return Collection<int, BlogPost>
     */
    public function published(): Collection
    {
        return BlogPost::query()
            ->publiclyPublished()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    public function findPublished(string $slug): ?BlogPost
    {
        return BlogPost::query()
            ->publiclyPublished()
            ->where('slug', $slug)
            ->first();
    }

    /**
     * @return array{slug:string,title:string,description:string,date:string,date_iso:string,date_modified_iso:string}
     */
    public function summary(BlogPost $post): array
    {
        $publishedAt = $this->displayDate($post->published_at);

        return [
            'slug' => $post->slug,
            'title' => (string) $post->published_title,
            'description' => (string) $post->published_description,
            'date' => $publishedAt->format('j F Y'),
            'date_iso' => $publishedAt->toDateString(),
            'date_modified_iso' => $this->displayDate($post->published_revision_at ?? $post->published_at)->toDateString(),
        ];
    }

    /**
     * @return array{slug:string,title:string,description:string,date:string,date_iso:string,date_modified_iso:string,html:string}
     */
    public function publicPayload(BlogPost $post): array
    {
        return $this->summary($post) + [
            'html' => $this->renderer->render((string) $post->published_body),
        ];
    }

    /**
     * @return array{slug:string,title:string,description:string,date:string,date_iso:string,date_modified_iso:string,html:string}
     */
    public function previewPayload(BlogPost $post): array
    {
        $displayDate = $this->displayDate($post->published_at ?? now());

        return [
            'slug' => $post->slug,
            'title' => $post->title,
            'description' => $post->description,
            'date' => $displayDate->format('j F Y'),
            'date_iso' => $displayDate->toDateString(),
            'date_modified_iso' => $this->displayDate($post->published_revision_at ?? now())->toDateString(),
            'html' => $this->renderer->render($post->body),
        ];
    }

    /**
     * @return array{id:string,title:string,slug:string,status:string,published_at:string|null,published_revision_at:string|null,updated_at:string,has_pending_changes:bool}
     */
    public function adminSummary(BlogPost $post): array
    {
        return [
            'id' => (string) $post->getKey(),
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status,
            'published_at' => $post->published_at?->toIso8601String(),
            'published_revision_at' => $post->published_revision_at?->toIso8601String(),
            'updated_at' => $post->updated_at->toIso8601String(),
            'has_pending_changes' => $post->isPublished() && (
                $post->title !== $post->published_title
                || $post->description !== $post->published_description
                || $post->body !== $post->published_body
            ),
        ];
    }

    /**
     * @return array{id:string,title:string,slug:string,description:string,body:string,status:string,published_at:string|null,published_revision_at:string|null,slug_locked:bool}
     */
    public function editorPayload(BlogPost $post): array
    {
        return [
            'id' => (string) $post->getKey(),
            'title' => $post->title,
            'slug' => $post->slug,
            'description' => $post->description,
            'body' => $post->body,
            'status' => $post->status,
            'published_at' => $post->published_at?->toIso8601String(),
            'published_revision_at' => $post->published_revision_at?->toIso8601String(),
            'slug_locked' => $post->hasBeenPublished(),
        ];
    }

    private function displayDate(?CarbonInterface $date): CarbonInterface
    {
        return ($date ?? now())->copy()->setTimezone(self::DISPLAY_TIMEZONE);
    }
}
