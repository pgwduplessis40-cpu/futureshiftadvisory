<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoardPost;
use App\Models\User;
use App\Services\Board\InspirationBoard;
use App\Services\Storage\Exceptions\InfectedFileException;
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

        $image = $request->file('image');

        try {
            $this->board->create(
                $validated,
                $image instanceof UploadedFile ? $image : null,
                $user,
            );
        } catch (InfectedFileException) {
            return back()->withErrors(['image' => 'The image was rejected because malware was detected.']);
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
        ]);

        $this->board->update($boardPost, $validated, $this->actor($request));

        return back()->with('status', 'board-post-updated');
    }

    public function publish(Request $request, BoardPost $boardPost): RedirectResponse
    {
        $this->board->publish($boardPost, $this->actor($request));

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
            'published_at' => $post->published_at?->toIso8601String(),
            'created_by' => $post->createdBy?->name,
            'created_at' => $post->created_at?->toIso8601String(),
        ];
    }
}
