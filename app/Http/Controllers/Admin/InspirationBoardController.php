<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoardPost;
use App\Models\Document;
use App\Models\InspirationRotationSchedule;
use App\Models\User;
use App\Services\Board\InspirationBoard;
use App\Services\Storage\Exceptions\InfectedFileException;
use App\Services\Storage\Exceptions\SecureFileStorageException;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class InspirationBoardController extends Controller
{
    public function __construct(private readonly InspirationBoard $board) {}

    public function index(): Response
    {
        return Inertia::render('admin/inspiration-board/Index', [
            'posts' => $this->board->library()
                ->map(fn (BoardPost $post): array => $this->adminPayload($post))
                ->all(),
            'rotationSchedules' => $this->board->rotationSchedules()
                ->map(fn (InspirationRotationSchedule $schedule): array => $this->rotationSchedulePayload($schedule))
                ->all(),
            'storeUrl' => route('admin.inspiration-board.store', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(BoardPost::TYPES)],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn (): bool => $request->input('type') !== BoardPost::TYPE_IMAGE)],
            'attribution' => ['nullable', 'string', 'max:200'],
            'scheduled_at' => ['nullable', 'date'],
            'image' => [
                Rule::requiredIf(fn (): bool => $request->input('type') === BoardPost::TYPE_IMAGE),
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp,gif',
                'max:8192',
            ],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if (array_key_exists('scheduled_at', $validated)) {
            $validated['scheduled_at'] = $this->parseScheduledAt($validated['scheduled_at']);
        }

        $image = $request->file('image');

        try {
            $this->board->create(
                $validated,
                $image instanceof UploadedFile ? $image : null,
                $user,
            );
        } catch (InfectedFileException) {
            return back()->withErrors(['image' => 'The image was rejected because malware was detected.']);
        } catch (SecureFileStorageException $exception) {
            report($exception);

            return back()->withErrors(['image' => 'The image could not be stored securely. Please try again or contact support.']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['image' => $exception->getMessage()]);
        }

        return to_route('admin.inspiration-board.index')->with('status', 'board-post-created');
    }

    public function update(Request $request, BoardPost $boardPost): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:2000'],
            'attribution' => ['nullable', 'string', 'max:200'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        if (array_key_exists('scheduled_at', $validated)) {
            $validated['scheduled_at'] = $this->parseScheduledAt($validated['scheduled_at']);
        }

        try {
            $this->board->update($boardPost, $validated, $this->actor($request));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['scheduled_at' => $exception->getMessage()]);
        }

        return back()->with('status', 'board-post-updated');
    }

    public function scheduleRotation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'start_at' => ['required', 'date'],
            'cadence_days' => ['required', 'integer', 'min:1', 'max:365'],
            'post_ids' => ['required', 'array', 'min:1', 'max:100'],
            'post_ids.*' => ['required', 'uuid', 'distinct'],
        ]);

        try {
            $this->board->createRotation(
                (string) ($validated['name'] ?? ''),
                CarbonImmutable::parse((string) $validated['start_at'], InspirationBoard::ROTATION_TIMEZONE),
                (int) $validated['cadence_days'],
                $validated['post_ids'],
                $this->actor($request),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['post_ids' => $exception->getMessage()]);
        }

        return back()->with('status', 'board-rotation-scheduled');
    }

    public function cancelRotation(Request $request, InspirationRotationSchedule $rotationSchedule): RedirectResponse
    {
        $this->board->cancelRotation($rotationSchedule, $this->actor($request));

        return back()->with('status', 'board-rotation-cancelled');
    }

    public function publish(Request $request, BoardPost $boardPost): RedirectResponse
    {
        try {
            $this->board->publish($boardPost, $this->actor($request));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['post' => $exception->getMessage()]);
        }

        return back()->with('status', 'board-post-published');
    }

    public function archive(Request $request, BoardPost $boardPost): RedirectResponse
    {
        $this->board->archive($boardPost, $this->actor($request));

        return back()->with('status', 'board-post-archived');
    }

    public function pin(Request $request, BoardPost $boardPost): RedirectResponse
    {
        $this->board->pin($boardPost, $this->actor($request));

        return back()->with('status', 'board-post-pinned');
    }

    public function unpin(Request $request, BoardPost $boardPost): RedirectResponse
    {
        $this->board->unpin($boardPost, $this->actor($request));

        return back()->with('status', 'board-post-unpinned');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPayload(BoardPost $post): array
    {
        return [
            'id' => $post->id,
            'type' => $post->type,
            'title' => $post->title,
            'body' => $post->body,
            'attribution' => $post->attribution,
            'status' => $post->status,
            'pinned' => $post->pinned,
            'image_url' => $post->isImage()
                ? route('portal.inspiration-board.image', $post, absolute: false)
                : null,
            'image_filename' => $post->image_filename,
            'image_scanner_result' => $post->imageDocument?->scanner_result,
            'image_is_quarantined' => $post->imageDocument instanceof Document
                && $post->imageDocument->scanner_result !== Document::SCANNER_CLEAN,
            'published_at' => $post->published_at?->toIso8601String(),
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
            'featured_at' => $post->featured_at?->toIso8601String(),
            'created_by' => $post->createdBy?->name,
            'created_at' => $post->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rotationSchedulePayload(InspirationRotationSchedule $schedule): array
    {
        $now = now();
        $phase = $schedule->status === InspirationRotationSchedule::STATUS_CANCELLED
            ? 'cancelled'
            : ($schedule->ends_at?->lt($now)
                ? 'completed'
                : ($schedule->starts_at?->gt($now) ? 'upcoming' : 'active'));

        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'status' => $schedule->status,
            'phase' => $phase,
            'starts_at' => $schedule->starts_at?->toIso8601String(),
            'ends_at' => $schedule->ends_at?->toIso8601String(),
            'cadence_days' => $schedule->cadence_days,
            'post_count' => $schedule->posts_count,
            'posts' => $schedule->posts
                ->map(fn (BoardPost $post): array => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'body' => $post->body,
                    'position' => (int) $post->pivot->position,
                    'scheduled_at' => CarbonImmutable::parse((string) $post->pivot->scheduled_at)->toIso8601String(),
                ])
                ->all(),
        ];
    }

    private function parseScheduledAt(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, InspirationBoard::ROTATION_TIMEZONE);
    }
}
