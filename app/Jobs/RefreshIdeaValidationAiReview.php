<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\IdeaValidation;
use App\Models\User;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Support\RequestContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class RefreshIdeaValidationAiReview implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(
        public readonly string $ideaValidationId,
        public readonly int $advisorId,
    ) {}

    public function handle(IdeaValidationService $ideas, RequestContext $context): void
    {
        $context->apply('system', []);

        $validation = IdeaValidation::query()->find($this->ideaValidationId);
        $advisor = User::query()->find($this->advisorId);

        if (! $validation instanceof IdeaValidation || ! $advisor instanceof User) {
            return;
        }

        $ideas->markRefreshRunning($validation, $advisor);

        try {
            $ideas->refreshEvaluation($validation->refresh(), $advisor);
        } catch (Throwable $exception) {
            $ideas->markRefreshFailed($validation->refresh(), $advisor, $exception);
            report($exception);
        }
    }
}
