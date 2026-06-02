<?php

declare(strict_types=1);

namespace App\Services\Board;

use App\Models\BoardPost;
use App\Models\Document;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Storage\SecureFileWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class InspirationBoard
{
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
            ->published()
            ->orderByDesc('pinned')
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
            ->published()
            ->orderByDesc('pinned')
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
            ->with('createdBy')
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{type:string, title?:?string, body?:?string, attribution?:?string}  $data
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

            if ($document->scanner_result !== Document::SCANNER_CLEAN) {
                throw new RuntimeException('The image could not be virus-scanned cleanly and was not stored.');
            }

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
                'created_by_user_id' => $actor->getAuthIdentifier(),
                ...$imageAttributes,
            ]);

            $this->audit->record('board_post.created', subject: $post, actor: $actor, after: [
                'type' => $post->type,
                'status' => $post->status,
            ]);

            return $post;
        });
    }

    /**
     * @param  array{title?:?string, body?:?string, attribution?:?string}  $data
     */
    public function update(BoardPost $post, array $data, User $actor): BoardPost
    {
        $post->forceFill([
            'title' => $this->trimToNull($data['title'] ?? $post->title),
            'body' => $this->trimToNull($data['body'] ?? $post->body),
            'attribution' => $this->trimToNull($data['attribution'] ?? $post->attribution),
        ])->save();

        $this->audit->record('board_post.updated', subject: $post, actor: $actor, after: [
            'type' => $post->type,
        ]);

        return $post;
    }

    public function publish(BoardPost $post, User $actor): BoardPost
    {
        if ($post->isImage() && $post->image_path === null) {
            throw new RuntimeException('Image posts require a stored, scanned image before publishing.');
        }

        $post->forceFill([
            'status' => BoardPost::STATUS_PUBLISHED,
            'published_at' => $post->published_at ?? now(),
        ])->save();

        $this->audit->record('board_post.published', subject: $post, actor: $actor, after: [
            'type' => $post->type,
            'published_at' => $post->published_at?->toIso8601String(),
        ]);

        return $post;
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
        if (! $post->isPublished()) {
            throw new RuntimeException('Only published posts can be pinned.');
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
}
