<?php

declare(strict_types=1);

namespace App\Services\Questionnaires;

use App\Models\Questionnaire;
use Illuminate\Support\Collection;

final class QuestionnaireRuleEngine
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array<int, string>
     */
    public function visibleQuestionIds(Questionnaire $questionnaire, array $answers): array
    {
        $questionnaire->loadMissing('sections.questions');

        $questions = $questionnaire->sections
            ->flatMap(fn ($section): Collection => $section->questions)
            ->values();

        $visible = $questions
            ->pluck('id')
            ->mapWithKeys(fn (string $id): array => [$id => true])
            ->all();

        foreach ($questions as $question) {
            foreach ($this->rules($question->conditional_logic) as $rule) {
                $target = $this->stringValue($rule['show'] ?? null) ?: (string) $question->getKey();
                $when = $this->stringValue($rule['when'] ?? null);

                if ($when === '' || ! array_key_exists($target, $visible)) {
                    continue;
                }

                $visible[$target] = $visible[$target] && $this->matches($this->answerValue($answers, $when), $rule);
            }
        }

        return array_values(array_keys(array_filter($visible)));
    }

    /**
     * @param  array<string, mixed>|null  $logic
     * @return array<int, array<string, mixed>>
     */
    private function rules(?array $logic): array
    {
        if ($logic === null || $logic === []) {
            return [];
        }

        if (array_is_list($logic)) {
            return array_values(array_filter($logic, 'is_array'));
        }

        return [$logic];
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function answerValue(array $answers, string $questionId): mixed
    {
        $answer = $answers[$questionId] ?? null;

        if (is_array($answer) && array_key_exists('value', $answer)) {
            return $answer['value'];
        }

        return $answer;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function matches(mixed $value, array $rule): bool
    {
        if (array_key_exists('equals', $rule)) {
            return $this->containsValue($value, $rule['equals']);
        }

        if (array_key_exists('in', $rule) && is_array($rule['in'])) {
            foreach ($rule['in'] as $candidate) {
                if ($this->containsValue($value, $candidate)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    private function containsValue(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            foreach ($actual as $item) {
                if ($this->sameValue($item, $expected)) {
                    return true;
                }
            }

            return false;
        }

        return $this->sameValue($actual, $expected);
    }

    private function sameValue(mixed $actual, mixed $expected): bool
    {
        return $this->normalise($actual) === $this->normalise($expected);
    }

    private function normalise(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return trim((string) $value);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
