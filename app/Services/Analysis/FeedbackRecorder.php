<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\AnalysisFeedback;
use App\Models\AnalysisFinding;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FeedbackRecorder
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function record(
        AnalysisFinding $finding,
        User $advisor,
        string $decision,
        ?int $rating = null,
        ?string $correctedBody = null,
        ?string $note = null,
    ): AnalysisFeedback {
        if (! in_array($decision, AnalysisFeedback::decisions(), true)) {
            throw new InvalidArgumentException("Unsupported analysis feedback decision [{$decision}].");
        }

        return DB::transaction(function () use ($finding, $advisor, $decision, $rating, $correctedBody, $note): AnalysisFeedback {
            $feedback = AnalysisFeedback::query()->create([
                'analysis_finding_id' => $finding->getKey(),
                'advisor_user_id' => $advisor->getKey(),
                'decision' => $decision,
                'rating' => $rating,
                'corrected_body' => $correctedBody,
                'note' => $note,
            ]);

            $feedback->setAttribute('client_id', $finding->client_id);
            $run = $finding->run;

            $this->audit->record(
                action: 'analysis_feedback.recorded',
                subject: $feedback,
                actor: $advisor,
                after: [
                    'analysis_finding_id' => $finding->getKey(),
                    'analysis_run_id' => $finding->analysis_run_id,
                    'client_id' => $finding->client_id,
                    'module' => $run?->module?->value,
                    'lens' => $finding->lens?->value,
                    'decision' => $decision,
                    'rating' => $rating,
                    'has_correction' => is_string($correctedBody) && trim($correctedBody) !== '',
                    'has_note' => is_string($note) && trim($note) !== '',
                ],
            );

            return $feedback;
        });
    }
}
