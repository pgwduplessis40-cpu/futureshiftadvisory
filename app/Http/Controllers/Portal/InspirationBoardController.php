<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\BoardPost;
use App\Services\Board\InspirationBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class InspirationBoardController extends Controller
{
    public function __construct(private readonly InspirationBoard $board) {}

    public function index(): Response
    {
        return Inertia::render('portal/inspiration-board/Index', [
            'posts' => $this->board->feed()
                ->map(fn (BoardPost $post): array => $this->board->portalPayload($post))
                ->all(),
        ]);
    }

    public function image(Request $request, BoardPost $boardPost): SymfonyResponse
    {
        abort_unless($boardPost->isImage() && is_string($boardPost->image_path), 404);

        $canManage = (bool) $request->user()?->can(Permission::BOARD_MANAGE->value);
        abort_unless($canManage || $boardPost->isPublished(), 404);

        $disk = Storage::disk('secure_local');
        abort_unless($disk->exists($boardPost->image_path), 404);

        $filename = $boardPost->image_filename ?: 'inspiration';
        $disposition = (new ResponseHeaderBag)->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $filename,
            Str::ascii($filename) ?: 'inspiration',
        );

        return response($disk->get($boardPost->image_path), 200, [
            'Content-Disposition' => $disposition,
            'Content-Type' => $boardPost->image_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
