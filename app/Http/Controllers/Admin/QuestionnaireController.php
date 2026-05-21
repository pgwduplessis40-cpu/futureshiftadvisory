<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Http\Controllers\Controller;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireSection;
use App\Services\Audit\AuditWriter;
use App\Services\Questionnaires\QuestionnairePayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class QuestionnaireController extends Controller
{
    public function __construct(
        private readonly AuditWriter $auditWriter,
        private readonly QuestionnairePayload $payload,
    ) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Questionnaire::class);

        return Inertia::render('admin/questionnaires/Index', [
            'questionnaires' => Questionnaire::query()
                ->withCount(['sections', 'responses'])
                ->latest('created_at')
                ->get()
                ->map(fn (Questionnaire $questionnaire): array => $this->summaryPayload($questionnaire)),
            'sets' => QuestionnaireSet::values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Questionnaire::class);

        $validated = $request->validate([
            'set' => ['nullable', 'string', Rule::in(QuestionnaireSet::values())],
        ]);
        $set = $validated['set'] ?? QuestionnaireSet::STANDARD_ADVISORY->value;

        $draft = DB::transaction(function () use ($request, $set): Questionnaire {
            $source = Questionnaire::query()
                ->forSet($set)
                ->with('sections.questions')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->first();

            $draft = Questionnaire::query()->create([
                'set' => $set,
                'version' => $this->nextVersion($set),
                'title' => $source?->title ?? 'Questionnaire',
                'created_by_user_id' => $request->user()?->getAuthIdentifier(),
            ]);

            if ($source instanceof Questionnaire) {
                $this->cloneStructure($source, $draft);
            }

            return $draft;
        });

        return to_route('admin.questionnaires.edit', $draft);
    }

    public function edit(Questionnaire $questionnaire): Response
    {
        Gate::authorize('update', $questionnaire);

        return Inertia::render('admin/questionnaires/Edit', [
            'questionnaire' => $this->payload->schema($questionnaire->load('sections.questions')),
            'questionTypes' => QuestionnaireQuestionType::values(),
            'sets' => QuestionnaireSet::values(),
        ]);
    }

    public function update(Request $request, Questionnaire $questionnaire): RedirectResponse
    {
        Gate::authorize('update', $questionnaire);
        abort_if($questionnaire->isPublished(), 422, 'Published questionnaires are immutable. Create a new draft version.');

        $validated = $request->validate([
            'set' => ['required', 'string', Rule::in(QuestionnaireSet::values())],
            'version' => [
                'required',
                'string',
                'max:40',
                Rule::unique('questionnaires', 'version')
                    ->where('set', (string) $request->input('set'))
                    ->ignore($questionnaire->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.id' => ['required', 'uuid'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.help_text' => ['nullable', 'string'],
            'sections.*.questions' => ['required', 'array'],
            'sections.*.questions.*.id' => ['required', 'uuid'],
            'sections.*.questions.*.type' => ['required', 'string', Rule::in(QuestionnaireQuestionType::values())],
            'sections.*.questions.*.prompt' => ['required', 'string'],
            'sections.*.questions.*.help_text' => ['nullable', 'string'],
            'sections.*.questions.*.options' => ['nullable', 'array'],
            'sections.*.questions.*.conditional_logic' => ['nullable', 'array'],
            'sections.*.questions.*.required' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($questionnaire, $validated): void {
            $questionnaire->update([
                'set' => $validated['set'],
                'version' => $validated['version'],
                'title' => $validated['title'],
            ]);

            $seenSections = [];
            foreach (array_values($validated['sections']) as $sectionIndex => $sectionData) {
                $section = QuestionnaireSection::query()->updateOrCreate(
                    [
                        'id' => $sectionData['id'],
                        'questionnaire_id' => $questionnaire->getKey(),
                    ],
                    [
                        'order' => $sectionIndex + 1,
                        'title' => $sectionData['title'],
                        'help_text' => $sectionData['help_text'] ?? null,
                    ],
                );
                $seenSections[] = $section->getKey();

                $seenQuestions = [];
                foreach (array_values($sectionData['questions']) as $questionIndex => $questionData) {
                    $question = QuestionnaireQuestion::query()->updateOrCreate(
                        [
                            'id' => $questionData['id'],
                            'questionnaire_section_id' => $section->getKey(),
                        ],
                        [
                            'order' => $questionIndex + 1,
                            'type' => $questionData['type'],
                            'prompt' => $questionData['prompt'],
                            'help_text' => $questionData['help_text'] ?? null,
                            'options' => $this->normaliseOptions($questionData['options'] ?? []),
                            'conditional_logic' => $this->normaliseConditionalLogic($questionData['conditional_logic'] ?? null),
                            'required' => (bool) $questionData['required'],
                        ],
                    );
                    $seenQuestions[] = $question->getKey();
                }

                $section->questions()
                    ->whereNotIn('id', $seenQuestions)
                    ->delete();
            }

            $questionnaire->sections()
                ->whereNotIn('id', $seenSections)
                ->delete();
        });

        return to_route('admin.questionnaires.edit', $questionnaire)->with('status', 'questionnaire-updated');
    }

    public function preview(Questionnaire $questionnaire): Response
    {
        Gate::authorize('view', $questionnaire);

        return Inertia::render('admin/questionnaires/Preview', [
            'questionnaire' => $this->payload->schema($questionnaire->load('sections.questions')),
        ]);
    }

    public function publish(Request $request, Questionnaire $questionnaire): RedirectResponse
    {
        Gate::authorize('publish', $questionnaire);
        abort_if($questionnaire->isPublished(), 422, 'This questionnaire version is already published.');

        $questionnaire->forceFill([
            'published_at' => now(),
            'published_by_user_id' => $request->user()?->getAuthIdentifier(),
        ])->save();

        $this->auditWriter->record('questionnaire.published', subject: $questionnaire, after: [
            'set' => $questionnaire->set->value,
            'version' => $questionnaire->version,
            'published_at' => $questionnaire->published_at?->toIso8601String(),
        ]);

        return to_route('admin.questionnaires.preview', $questionnaire)->with('status', 'questionnaire-published');
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(Questionnaire $questionnaire): array
    {
        return [
            'id' => $questionnaire->id,
            'set' => $questionnaire->set->value,
            'version' => $questionnaire->version,
            'title' => $questionnaire->title,
            'published_at' => $questionnaire->published_at?->toIso8601String(),
            'sections_count' => $questionnaire->sections_count,
            'responses_count' => $questionnaire->responses_count,
        ];
    }

    private function nextVersion(string $set): string
    {
        $next = Questionnaire::query()
            ->forSet($set)
            ->pluck('version')
            ->map(fn (string $version): int => (int) preg_replace('/\D+/', '', $version))
            ->max() + 1;

        return (string) max(1, $next);
    }

    private function cloneStructure(Questionnaire $source, Questionnaire $draft): void
    {
        $questionIds = [];

        foreach ($source->sections as $section) {
            foreach ($section->questions as $question) {
                $questionIds[$question->id] = (string) Str::uuid();
            }
        }

        foreach ($source->sections as $section) {
            $newSection = $draft->sections()->create([
                'order' => $section->order,
                'title' => $section->title,
                'help_text' => $section->help_text,
            ]);

            foreach ($section->questions as $question) {
                $newSection->questions()->create([
                    'id' => $questionIds[$question->id],
                    'order' => $question->order,
                    'type' => $question->type->value,
                    'prompt' => $question->prompt,
                    'help_text' => $question->help_text,
                    'options' => $question->options,
                    'conditional_logic' => $this->remapConditionalLogic($question->conditional_logic, $questionIds),
                    'required' => $question->required,
                ]);
            }
        }
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    private function normaliseOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        return collect($options)
            ->map(function (mixed $option): ?array {
                if (is_array($option)) {
                    $label = trim((string) ($option['label'] ?? $option['value'] ?? ''));
                    $value = trim((string) ($option['value'] ?? Str::slug($label, '_')));
                } else {
                    $label = trim((string) $option);
                    $value = Str::slug($label, '_');
                }

                if ($label === '' || $value === '') {
                    return null;
                }

                return ['value' => $value, 'label' => $label];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    private function normaliseConditionalLogic(mixed $logic): ?array
    {
        if (! is_array($logic) || $logic === []) {
            return null;
        }

        if (array_is_list($logic)) {
            $rules = collect($logic)
                ->map(fn (mixed $rule): ?array => $this->normaliseRule($rule))
                ->filter()
                ->values()
                ->all();

            return $rules === [] ? null : $rules;
        }

        return $this->normaliseRule($logic);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normaliseRule(mixed $rule): ?array
    {
        if (! is_array($rule) || ! is_string($rule['when'] ?? null)) {
            return null;
        }

        $normalised = [
            'when' => $rule['when'],
        ];

        if (array_key_exists('equals', $rule)) {
            $normalised['equals'] = $rule['equals'];
        }

        if (isset($rule['in']) && is_array($rule['in'])) {
            $normalised['in'] = array_values($rule['in']);
        }

        if (is_string($rule['show'] ?? null)) {
            $normalised['show'] = $rule['show'];
        }

        return count($normalised) > 1 ? $normalised : null;
    }

    /**
     * @param  array<string, string>  $questionIds
     */
    private function remapConditionalLogic(?array $logic, array $questionIds): ?array
    {
        $normalised = $this->normaliseConditionalLogic($logic);
        if ($normalised === null) {
            return null;
        }

        $remap = function (array $rule) use ($questionIds): array {
            foreach (['when', 'show'] as $key) {
                if (isset($rule[$key]) && isset($questionIds[$rule[$key]])) {
                    $rule[$key] = $questionIds[$rule[$key]];
                }
            }

            return $rule;
        };

        if (array_is_list($normalised)) {
            return array_map($remap, $normalised);
        }

        return $remap($normalised);
    }
}
