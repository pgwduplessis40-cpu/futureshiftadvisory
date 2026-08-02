<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningUpdate;
use Illuminate\Support\Str;

final class LearningUpdatePlainEnglishSummary
{
    /**
     * @param  array<string, mixed>  $capabilityProfile
     * @return array{what_we_learnt: string, why_it_matters: string, review_decision: string, signals: array<int, string>}
     */
    public function forUpdate(LearningUpdate $update, array $capabilityProfile): array
    {
        return [
            'what_we_learnt' => $this->whatWeLearnt($update),
            'why_it_matters' => $this->whyItMatters($update, $capabilityProfile),
            'review_decision' => $this->reviewDecision($update),
            'signals' => $this->signalSummaries($update),
        ];
    }

    private function whatWeLearnt(LearningUpdate $update): string
    {
        $sourceLabel = $this->label($this->text(data_get($update->source, 'type')) ?: 'learning signal');
        $promptLabel = $this->promptLabel($update);
        $signal = $this->primarySignal($update);

        if (is_array($signal)) {
            $signalLabel = $this->label($this->text(data_get($signal, 'type')) ?: 'quality signal');
            $term = $this->text(data_get($signal, 'term'));
            $message = "The {$sourceLabel} found possible {$signalLabel}";

            if ($term !== '') {
                $message .= ": {$term}";
            }

            $message .= '.';

            if ($promptLabel !== '') {
                $message .= " It was seen in the {$promptLabel} prompt or output.";
            }

            return $message;
        }

        $actionLabel = $this->actionLabel($this->text(data_get($update->proposed_change, 'action')));
        $message = "The {$sourceLabel} signal found a possible {$actionLabel}";

        if ($promptLabel !== '') {
            $message .= " for {$promptLabel}";
        }

        return Str::finish($message, '.');
    }

    /**
     * @param  array<string, mixed>  $capabilityProfile
     */
    private function whyItMatters(LearningUpdate $update, array $capabilityProfile): string
    {
        $capabilities = collect(data_get($capabilityProfile, 'capabilities', []))
            ->map(fn (mixed $capability): string => (string) $capability)
            ->all();
        $haystack = Str::lower(json_encode([
            $update->summary,
            $update->source,
            $update->proposed_change,
            $update->evidence,
        ]) ?: '');

        if (Str::contains($haystack, ['bias', 'praise', 'praise_language'])) {
            return 'Praise-heavy or biased wording can make analysis sound more certain or favourable than the evidence supports, so it needs human review before it influences advice.';
        }

        if (collect(['Finance', 'Financial Planning and Analysis', 'forecasting-time-series-data'])->intersect($capabilities)->isNotEmpty()) {
            return 'This could affect financial analysis or advice, so assumptions, calculations, evidence, and wording need checking before use.';
        }

        if (collect(['Data', 'fact-checker'])->intersect($capabilities)->isNotEmpty()) {
            return 'This could affect what evidence the platform trusts, so source quality, attribution, and client scope need checking first.';
        }

        return 'It may change how the platform supports advisor decisions, so it needs approval before it changes live behaviour.';
    }

    private function reviewDecision(LearningUpdate $update): string
    {
        $action = $this->text(data_get($update->proposed_change, 'action'));

        if (Str::contains($action, ['prompt', 'policy', 'calibration', 'bias'])) {
            return 'Check whether the wording or rule should be changed, kept with a clear reason, deferred for more evidence, or rejected.';
        }

        if (Str::contains($action, ['reference_data', 'registry', 'benchmark', 'funder'])) {
            return 'Check the source evidence and decide whether the reference data should become active now, on a later date, or not at all.';
        }

        if (Str::contains($action, 'template')) {
            return 'Check the template evidence and decide whether it should be made available, held for changes, or rejected.';
        }

        return 'Decide whether to approve the update, approve it for a later date, defer it for more evidence, or reject it.';
    }

    /**
     * @return array<int, string>
     */
    private function signalSummaries(LearningUpdate $update): array
    {
        return collect([
            ...$this->arrayList(data_get($update->evidence, 'signals', [])),
            ...$this->arrayList(data_get($update->proposed_change, 'signals', [])),
        ])
            ->map(fn (mixed $signal): ?string => is_array($signal) ? $this->signalSummary($signal) : null)
            ->filter()
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $signal
     */
    private function signalSummary(array $signal): ?string
    {
        $type = $this->label($this->text(data_get($signal, 'type')) ?: 'signal');
        $term = $this->text(data_get($signal, 'term'));
        $reason = $this->text(data_get($signal, 'reason'));
        $severity = $this->text(data_get($signal, 'severity'));
        $message = Str::ucfirst($type).' signal';

        if ($term !== '') {
            $message .= " for {$term}";
        }

        if ($reason !== '') {
            $message .= ": {$reason}";
        }

        if ($severity !== '') {
            $message .= " Severity: {$severity}";
        }

        return Str::finish($message, '.');
    }

    /**
     * @return array<int, mixed>
     */
    private function arrayList(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function primarySignal(LearningUpdate $update): ?array
    {
        $signal = collect($this->arrayList(data_get($update->evidence, 'signals', [])))
            ->first(fn (mixed $candidate): bool => is_array($candidate));

        if (is_array($signal)) {
            return $signal;
        }

        $signal = collect($this->arrayList(data_get($update->proposed_change, 'signals', [])))
            ->first(fn (mixed $candidate): bool => is_array($candidate));

        return is_array($signal) ? $signal : null;
    }

    private function actionLabel(string $action): string
    {
        if (Str::contains($action, 'prompt')) {
            return 'prompt update';
        }

        if (Str::contains($action, ['bias', 'calibration'])) {
            return 'bias or calibration issue';
        }

        if (Str::contains($action, ['reference_data', 'registry', 'benchmark', 'funder'])) {
            return 'reference data update';
        }

        if (Str::contains($action, 'template')) {
            return 'template update';
        }

        return $action !== '' ? $this->label($action) : 'learning update';
    }

    private function promptLabel(LearningUpdate $update): string
    {
        $promptId = $this->text(data_get($update->source, 'prompt_id'));

        if (Str::startsWith($promptId, 'analysis.')) {
            $topic = $this->label((string) Str::of($promptId)->after('analysis.'));

            return $topic !== '' ? "{$topic} analysis" : 'analysis prompt';
        }

        return $this->label($promptId);
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? Str::squish((string) $value) : '';
    }

    private function label(string $value): string
    {
        return Str::of($value)
            ->replace(['_', '-', '.'], ' ')
            ->squish()
            ->lower()
            ->toString();
    }
}
