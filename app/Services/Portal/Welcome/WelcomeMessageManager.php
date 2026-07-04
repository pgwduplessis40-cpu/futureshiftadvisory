<?php

declare(strict_types=1);

namespace App\Services\Portal\Welcome;

use App\Models\User;
use App\Models\WelcomeMessage;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class WelcomeMessageManager
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly WelcomeMessageSanitizer $sanitizer,
    ) {}

    /**
     * The single active welcome message, or null if none has been published.
     */
    public function current(): ?WelcomeMessage
    {
        return WelcomeMessage::query()
            ->active()
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @return Collection<int, WelcomeMessage>
     */
    public function history(int $limit = 20): Collection
    {
        return WelcomeMessage::query()
            ->with('createdBy')
            ->orderByDesc('version')
            ->limit($limit)
            ->get();
    }

    /**
     * Publish a new immutable version and make it the active message. The prior
     * active version is retained as history and deactivated. Every publish is
     * audit-logged — content is never changed silently.
     */
    public function publish(string $body, User $actor): WelcomeMessage
    {
        $body = $this->sanitizer->sanitize($body);

        return DB::transaction(function () use ($body, $actor): WelcomeMessage {
            WelcomeMessage::query()
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $message = WelcomeMessage::query()->create([
                'version' => $this->nextVersion(),
                'body' => $body,
                'is_active' => true,
                'activated_at' => now(),
                'created_by_user_id' => $actor->getAuthIdentifier(),
            ]);

            $this->audit->record('welcome_message.published', subject: $message, actor: $actor, after: [
                'version' => $message->version,
                'characters' => mb_strlen($body),
            ]);

            return $message;
        });
    }

    private function nextVersion(): int
    {
        return (int) (WelcomeMessage::query()->max('version') ?? 0) + 1;
    }
}
