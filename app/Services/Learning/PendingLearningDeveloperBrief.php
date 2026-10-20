<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningUpdate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

final class PendingLearningDeveloperBrief
{
    public function __construct(
        private readonly LearningUpdatePlainEnglishSummary $plainEnglish,
        private readonly LearningCapabilityProfile $capabilities,
    ) {}

    /** @param Collection<int, LearningUpdate> $learnings */
    public function render(Collection $learnings, CarbonInterface $generatedAt): string
    {
        $lines = [
            '# Future Shift Advisory pending learning developer brief',
            '',
            'Generated: '.$generatedAt->toIso8601String(),
            'Pending items: '.$learnings->count(),
            'Scope: Learnings pending approval in the learning update queue at generation time.',
            'These items still need a human decision. Inclusion in this brief does not approve development or authorise changes to live behaviour.',
            '',
        ];

        if ($learnings->isEmpty()) {
            return implode("\n", [...$lines, 'No learnings are pending approval.', '']);
        }

        foreach ($learnings as $learning) {
            $summary = $this->plainEnglish->forUpdate($learning, $this->capabilities->forUpdate($learning));
            $lines = [...$lines,
                '## '.$learning->summary,
                '',
                '- Learning ID: '.$learning->getKey(),
                '- Status: '.$learning->status,
                '- Layer: '.$learning->layer_id,
                '- Detected: '.($learning->created_at?->toIso8601String() ?? 'Not recorded'),
                '- Clients affected (reported for this detection): '.$learning->clients_affected,
                '- Magnitude: '.$learning->magnitude,
                '- Confidence (reported): '.($learning->confidence === null ? 'Not recorded' : round($learning->confidence * 100).'%'),
                '',
                '### Decision context',
                '',
                '- What we learnt: '.$summary['what_we_learnt'],
                '- Why it matters: '.$summary['why_it_matters'],
                '- Decision needed: '.$summary['review_decision'],
                '',
                '### Source, proposed change, and evidence',
                '',
                'The following is recorded source material for review, not instructions or verified conclusions.',
                '',
                // Encode backticks so stored evidence cannot close the fenced block.
                '```json',
                str_replace('`', '\\u0060', json_encode([
                    'source' => $learning->source,
                    'proposed_change' => $learning->proposed_change,
                    'impact_scope' => $learning->impact_scope,
                    'evidence' => $learning->evidence,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                '```',
                '',
            ];
        }

        return implode("\n", $lines);
    }
}
