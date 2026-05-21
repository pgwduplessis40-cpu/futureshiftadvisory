import { DndContext, useDraggable, useDroppable } from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    ChevronUp,
    Eye,
    GripVertical,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { createContext, useContext, useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { QuestionnaireRenderer } from '@/components/questionnaires/QuestionnaireRenderer';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type {
    ConditionalLogic,
    ConditionalRule,
    QuestionOption,
    QuestionnaireAnswers,
    QuestionnaireQuestion,
    QuestionnaireSection,
} from '@/types/questionnaire';
import type { QuestionnaireForm } from './types';

type Props = {
    questionnaire: QuestionnaireForm;
    questionTypes: string[];
    sets: string[];
};

export default function QuestionnairesEdit({
    questionnaire,
    questionTypes,
    sets,
}: Props) {
    const form = useForm<QuestionnaireForm>(questionnaire);
    const [previewAnswers, setPreviewAnswers] = useState<QuestionnaireAnswers>(
        {},
    );
    const allQuestions = useMemo(
        () => form.data.sections.flatMap((section) => section.questions),
        [form.data.sections],
    );

    const updateSection = (
        sectionId: string,
        patch: Partial<QuestionnaireSection>,
    ) => {
        form.setData(
            'sections',
            form.data.sections.map((section) =>
                section.id === sectionId ? { ...section, ...patch } : section,
            ),
        );
    };

    const updateQuestion = (
        sectionId: string,
        questionId: string,
        patch: Partial<QuestionnaireQuestion>,
    ) => {
        form.setData(
            'sections',
            form.data.sections.map((section) =>
                section.id === sectionId
                    ? {
                          ...section,
                          questions: section.questions.map((question) =>
                              question.id === questionId
                                  ? { ...question, ...patch }
                                  : question,
                          ),
                      }
                    : section,
            ),
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/admin/questionnaires/${questionnaire.id}`);
    };

    return (
        <>
            <Head title={`Edit ${questionnaire.title}`} />

            <form className="space-y-6" onSubmit={submit}>
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <Button asChild size="sm" variant="outline">
                            <Link href="/admin/questionnaires">
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Questionnaires
                            </Link>
                        </Button>
                        <h1 className="mt-4 text-xl font-semibold">
                            Edit questionnaire
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm" variant="outline">
                            <Link
                                href={`/admin/questionnaires/${questionnaire.id}/preview`}
                            >
                                <Eye className="size-4" aria-hidden="true" />
                                Preview
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    `/admin/questionnaires/${questionnaire.id}/publish`,
                                )
                            }
                        >
                            Publish
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                        >
                            <Save className="size-4" aria-hidden="true" />
                            Save
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Field label="Set" id="set" error={form.errors.set}>
                        <select
                            id="set"
                            value={form.data.set}
                            onChange={(event) =>
                                form.setData('set', event.target.value)
                            }
                            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            {sets.map((set) => (
                                <option key={set} value={set}>
                                    {set}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        label="Version"
                        id="version"
                        error={form.errors.version}
                    >
                        <Input
                            id="version"
                            value={form.data.version}
                            onChange={(event) =>
                                form.setData('version', event.target.value)
                            }
                            required
                        />
                    </Field>
                    <Field label="Title" id="title" error={form.errors.title}>
                        <Input
                            id="title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            required
                        />
                    </Field>
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(24rem,0.85fr)]">
                    <div className="space-y-4">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-base font-medium">Builder</h2>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    form.setData('sections', [
                                        ...form.data.sections,
                                        newSection(form.data.sections.length),
                                    ])
                                }
                            >
                                <Plus className="size-4" aria-hidden="true" />
                                Section
                            </Button>
                        </div>

                        <DndContext
                            onDragEnd={(event) =>
                                form.setData(
                                    'sections',
                                    reorderByDrag(
                                        form.data.sections,
                                        event,
                                        'section:',
                                    ),
                                )
                            }
                        >
                            <div className="space-y-4">
                                {form.data.sections.map(
                                    (section, sectionIndex) => (
                                        <DraggableBlock
                                            key={section.id}
                                            id={`section:${section.id}`}
                                        >
                                            <SectionEditor
                                                section={section}
                                                sectionIndex={sectionIndex}
                                                questionTypes={questionTypes}
                                                allQuestions={allQuestions}
                                                onSectionChange={(patch) =>
                                                    updateSection(
                                                        section.id,
                                                        patch,
                                                    )
                                                }
                                                onSectionRemove={() =>
                                                    form.setData(
                                                        'sections',
                                                        form.data.sections.filter(
                                                            (candidate) =>
                                                                candidate.id !==
                                                                section.id,
                                                        ),
                                                    )
                                                }
                                                onSectionMove={(direction) =>
                                                    form.setData(
                                                        'sections',
                                                        moveAt(
                                                            form.data.sections,
                                                            sectionIndex,
                                                            direction,
                                                        ),
                                                    )
                                                }
                                                onQuestionAdd={() =>
                                                    updateSection(section.id, {
                                                        questions: [
                                                            ...section.questions,
                                                            newQuestion(
                                                                section
                                                                    .questions
                                                                    .length,
                                                            ),
                                                        ],
                                                    })
                                                }
                                                onQuestionsReorder={(event) =>
                                                    updateSection(section.id, {
                                                        questions:
                                                            reorderByDrag(
                                                                section.questions,
                                                                event,
                                                                'question:',
                                                            ),
                                                    })
                                                }
                                                onQuestionChange={(
                                                    questionId,
                                                    patch,
                                                ) =>
                                                    updateQuestion(
                                                        section.id,
                                                        questionId,
                                                        patch,
                                                    )
                                                }
                                                onQuestionRemove={(
                                                    questionId,
                                                ) =>
                                                    updateSection(section.id, {
                                                        questions:
                                                            section.questions.filter(
                                                                (question) =>
                                                                    question.id !==
                                                                    questionId,
                                                            ),
                                                    })
                                                }
                                                onQuestionMove={(
                                                    questionIndex,
                                                    direction,
                                                ) =>
                                                    updateSection(section.id, {
                                                        questions: moveAt(
                                                            section.questions,
                                                            questionIndex,
                                                            direction,
                                                        ),
                                                    })
                                                }
                                            />
                                        </DraggableBlock>
                                    ),
                                )}
                            </div>
                        </DndContext>
                    </div>

                    <aside className="space-y-4">
                        <h2 className="text-base font-medium">Preview</h2>
                        <div className="rounded-md border p-4">
                            <QuestionnaireRenderer
                                schema={form.data}
                                answers={previewAnswers}
                                onChange={setPreviewAnswers}
                            />
                        </div>
                    </aside>
                </div>
            </form>
        </>
    );
}

function SectionEditor({
    section,
    sectionIndex,
    questionTypes,
    allQuestions,
    onSectionChange,
    onSectionRemove,
    onSectionMove,
    onQuestionAdd,
    onQuestionsReorder,
    onQuestionChange,
    onQuestionRemove,
    onQuestionMove,
}: {
    section: QuestionnaireSection;
    sectionIndex: number;
    questionTypes: string[];
    allQuestions: QuestionnaireQuestion[];
    onSectionChange: (patch: Partial<QuestionnaireSection>) => void;
    onSectionRemove: () => void;
    onSectionMove: (direction: -1 | 1) => void;
    onQuestionAdd: () => void;
    onQuestionsReorder: (event: DragEndEvent) => void;
    onQuestionChange: (
        questionId: string,
        patch: Partial<QuestionnaireQuestion>,
    ) => void;
    onQuestionRemove: (questionId: string) => void;
    onQuestionMove: (questionIndex: number, direction: -1 | 1) => void;
}) {
    return (
        <section className="rounded-md border bg-background">
            <div className="grid gap-3 border-b p-4 lg:grid-cols-[auto_1fr_auto]">
                <DragHandle />
                <div className="grid gap-3">
                    <Field
                        label="Section title"
                        id={`section-${section.id}-title`}
                    >
                        <Input
                            id={`section-${section.id}-title`}
                            value={section.title}
                            onChange={(event) =>
                                onSectionChange({
                                    title: event.target.value,
                                })
                            }
                            required
                        />
                    </Field>
                    <Field label="Help text" id={`section-${section.id}-help`}>
                        <Input
                            id={`section-${section.id}-help`}
                            value={section.help_text ?? ''}
                            onChange={(event) =>
                                onSectionChange({
                                    help_text: event.target.value,
                                })
                            }
                        />
                    </Field>
                </div>
                <div className="flex gap-2 lg:flex-col">
                    <IconButton
                        label="Move section up"
                        onClick={() => onSectionMove(-1)}
                        disabled={sectionIndex === 0}
                    >
                        <ChevronUp className="size-4" aria-hidden="true" />
                    </IconButton>
                    <IconButton
                        label="Move section down"
                        onClick={() => onSectionMove(1)}
                    >
                        <ChevronDown className="size-4" aria-hidden="true" />
                    </IconButton>
                    <IconButton
                        label="Remove section"
                        onClick={onSectionRemove}
                    >
                        <Trash2 className="size-4" aria-hidden="true" />
                    </IconButton>
                </div>
            </div>

            <div className="space-y-3 p-4">
                <div className="flex items-center justify-between gap-3">
                    <h3 className="text-sm font-medium">Questions</h3>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={onQuestionAdd}
                    >
                        <Plus className="size-4" aria-hidden="true" />
                        Question
                    </Button>
                </div>

                <DndContext onDragEnd={onQuestionsReorder}>
                    <div className="space-y-3">
                        {section.questions.map((question, questionIndex) => (
                            <DraggableBlock
                                key={question.id}
                                id={`question:${question.id}`}
                                subtle
                            >
                                <QuestionEditor
                                    question={question}
                                    questionIndex={questionIndex}
                                    questionTypes={questionTypes}
                                    allQuestions={allQuestions}
                                    onChange={(patch) =>
                                        onQuestionChange(question.id, patch)
                                    }
                                    onRemove={() =>
                                        onQuestionRemove(question.id)
                                    }
                                    onMove={(direction) =>
                                        onQuestionMove(questionIndex, direction)
                                    }
                                />
                            </DraggableBlock>
                        ))}
                    </div>
                </DndContext>
            </div>
        </section>
    );
}

function QuestionEditor({
    question,
    questionIndex,
    questionTypes,
    allQuestions,
    onChange,
    onRemove,
    onMove,
}: {
    question: QuestionnaireQuestion;
    questionIndex: number;
    questionTypes: string[];
    allQuestions: QuestionnaireQuestion[];
    onChange: (patch: Partial<QuestionnaireQuestion>) => void;
    onRemove: () => void;
    onMove: (direction: -1 | 1) => void;
}) {
    const rule = firstRule(question.conditional_logic);
    const operator = Array.isArray(rule?.in) ? 'in' : 'equals';
    const conditionValue =
        operator === 'in'
            ? (rule?.in ?? []).join(', ')
            : String(rule?.equals ?? '');
    const supportsOptions = [
        'single-select',
        'multi-select',
        'likert',
    ].includes(question.type);

    return (
        <div className="space-y-3 rounded-md border p-3">
            <div className="flex items-start gap-3">
                <DragHandle />
                <div className="grid flex-1 gap-3 md:grid-cols-[1fr_11rem]">
                    <Field label="Prompt" id={`question-${question.id}-prompt`}>
                        <Input
                            id={`question-${question.id}-prompt`}
                            value={question.prompt}
                            onChange={(event) =>
                                onChange({ prompt: event.target.value })
                            }
                            required
                        />
                    </Field>
                    <Field label="Type" id={`question-${question.id}-type`}>
                        <select
                            id={`question-${question.id}-type`}
                            value={question.type}
                            onChange={(event) =>
                                onChange({
                                    type: event.target
                                        .value as QuestionnaireQuestion['type'],
                                    options: defaultOptions(event.target.value),
                                })
                            }
                            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            {questionTypes.map((type) => (
                                <option key={type} value={type}>
                                    {type}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>
                <div className="flex gap-2">
                    <IconButton
                        label="Move question up"
                        onClick={() => onMove(-1)}
                        disabled={questionIndex === 0}
                    >
                        <ChevronUp className="size-4" aria-hidden="true" />
                    </IconButton>
                    <IconButton
                        label="Move question down"
                        onClick={() => onMove(1)}
                    >
                        <ChevronDown className="size-4" aria-hidden="true" />
                    </IconButton>
                    <IconButton label="Remove question" onClick={onRemove}>
                        <Trash2 className="size-4" aria-hidden="true" />
                    </IconButton>
                </div>
            </div>

            <Field label="Help text" id={`question-${question.id}-help`}>
                <Input
                    id={`question-${question.id}-help`}
                    value={question.help_text ?? ''}
                    onChange={(event) =>
                        onChange({ help_text: event.target.value })
                    }
                />
            </Field>

            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={question.required}
                    onCheckedChange={(checked) =>
                        onChange({ required: checked === true })
                    }
                />
                Required
            </label>

            {supportsOptions && (
                <Field label="Options" id={`question-${question.id}-options`}>
                    <textarea
                        id={`question-${question.id}-options`}
                        value={optionsToText(question.options)}
                        onChange={(event) =>
                            onChange({
                                options: parseOptions(event.target.value),
                            })
                        }
                        rows={4}
                        className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                </Field>
            )}

            <div className="grid gap-3 border-t pt-3 md:grid-cols-3">
                <Field
                    label="Show when question"
                    id={`question-${question.id}-when`}
                >
                    <select
                        id={`question-${question.id}-when`}
                        value={rule?.when ?? ''}
                        onChange={(event) =>
                            onChange({
                                conditional_logic: event.target.value
                                    ? buildRule(
                                          question.id,
                                          event.target.value,
                                          operator,
                                          conditionValue,
                                      )
                                    : null,
                            })
                        }
                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">Always visible</option>
                        {allQuestions
                            .filter((candidate) => candidate.id !== question.id)
                            .map((candidate) => (
                                <option key={candidate.id} value={candidate.id}>
                                    {candidate.prompt}
                                </option>
                            ))}
                    </select>
                </Field>
                <Field label="Rule" id={`question-${question.id}-operator`}>
                    <select
                        id={`question-${question.id}-operator`}
                        value={operator}
                        disabled={!rule?.when}
                        onChange={(event) =>
                            onChange({
                                conditional_logic: rule?.when
                                    ? buildRule(
                                          question.id,
                                          rule.when,
                                          event.target.value,
                                          conditionValue,
                                      )
                                    : null,
                            })
                        }
                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="equals">equals</option>
                        <option value="in">is one of</option>
                    </select>
                </Field>
                <Field label="Value" id={`question-${question.id}-value`}>
                    <Input
                        id={`question-${question.id}-value`}
                        value={conditionValue}
                        disabled={!rule?.when}
                        onChange={(event) =>
                            onChange({
                                conditional_logic: rule?.when
                                    ? buildRule(
                                          question.id,
                                          rule.when,
                                          operator,
                                          event.target.value,
                                      )
                                    : null,
                            })
                        }
                    />
                </Field>
            </div>
        </div>
    );
}

function DraggableBlock({
    id,
    children,
    subtle = false,
}: {
    id: string;
    children: ReactNode;
    subtle?: boolean;
}) {
    const {
        attributes,
        listeners,
        setNodeRef: setDragRef,
        transform,
    } = useDraggable({ id });
    const { isOver, setNodeRef: setDropRef } = useDroppable({ id });

    const setRefs = (node: HTMLElement | null) => {
        setDragRef(node);
        setDropRef(node);
    };

    return (
        <div
            ref={setRefs}
            style={{
                transform: transform
                    ? `translate3d(${transform.x}px, ${transform.y}px, 0)`
                    : undefined,
            }}
            className={cn(
                'rounded-md outline-none',
                isOver && 'ring-2 ring-ring/40',
                subtle && 'bg-muted/20',
            )}
        >
            <div className="[&_[data-drag-handle]]:cursor-grab">
                <DragContext.Provider value={{ attributes, listeners }}>
                    {children}
                </DragContext.Provider>
            </div>
        </div>
    );
}

const DragContext = createContext<{
    attributes: ReturnType<typeof useDraggable>['attributes'];
    listeners: ReturnType<typeof useDraggable>['listeners'];
} | null>(null);

function DragHandle() {
    const drag = useContext(DragContext);

    return (
        <button
            type="button"
            data-drag-handle
            {...drag?.attributes}
            {...drag?.listeners}
            className="mt-1 inline-flex size-8 items-center justify-center rounded-md border text-muted-foreground"
            aria-label="Drag to reorder"
        >
            <GripVertical className="size-4" aria-hidden="true" />
        </button>
    );
}

function Field({
    label,
    id,
    error,
    children,
}: {
    label: string;
    id: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function IconButton({
    label,
    onClick,
    disabled = false,
    children,
}: {
    label: string;
    onClick: () => void;
    disabled?: boolean;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="outline"
            size="icon"
            aria-label={label}
            disabled={disabled}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}

function reorderByDrag<T extends { id: string }>(
    items: T[],
    event: DragEndEvent,
    prefix: string,
): T[] {
    const activeId = String(event.active.id).replace(prefix, '');
    const overId = event.over
        ? String(event.over.id).replace(prefix, '')
        : null;

    if (!overId || activeId === overId) {
        return items;
    }

    const from = items.findIndex((item) => item.id === activeId);
    const to = items.findIndex((item) => item.id === overId);

    if (from < 0 || to < 0) {
        return items;
    }

    const next = [...items];
    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved);

    return next;
}

function moveAt<T>(items: T[], index: number, direction: -1 | 1): T[] {
    const to = index + direction;

    if (to < 0 || to >= items.length) {
        return items;
    }

    const next = [...items];
    const [moved] = next.splice(index, 1);

    next.splice(to, 0, moved);

    return next;
}

function newSection(index: number): QuestionnaireSection {
    return {
        id: crypto.randomUUID(),
        order: index + 1,
        title: 'New section',
        help_text: null,
        questions: [newQuestion(0)],
    };
}

function newQuestion(index: number): QuestionnaireQuestion {
    return {
        id: crypto.randomUUID(),
        order: index + 1,
        type: 'text',
        prompt: 'New question',
        help_text: null,
        options: [],
        conditional_logic: null,
        required: true,
    };
}

function defaultOptions(type: string): QuestionOption[] {
    if (type === 'likert') {
        return [
            { value: '1', label: 'Very low' },
            { value: '2', label: 'Low' },
            { value: '3', label: 'Moderate' },
            { value: '4', label: 'High' },
            { value: '5', label: 'Very high' },
        ];
    }

    if (['single-select', 'multi-select'].includes(type)) {
        return [
            { value: 'yes', label: 'Yes' },
            { value: 'no', label: 'No' },
        ];
    }

    return [];
}

function optionsToText(options: QuestionOption[]): string {
    return options.map((option) => option.label).join('\n');
}

function parseOptions(value: string): QuestionOption[] {
    return value
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean)
        .map((label) => ({
            value: slug(label),
            label,
        }));
}

function slug(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
}

function firstRule(logic: ConditionalLogic): ConditionalRule | null {
    if (!logic) {
        return null;
    }

    return Array.isArray(logic) ? (logic[0] ?? null) : logic;
}

function buildRule(
    questionId: string,
    when: string,
    operator: string,
    value: string,
): ConditionalLogic {
    return operator === 'in'
        ? {
              when,
              in: value
                  .split(',')
                  .map((item) => item.trim())
                  .filter(Boolean),
              show: questionId,
          }
        : {
              when,
              equals: value,
              show: questionId,
          };
}
