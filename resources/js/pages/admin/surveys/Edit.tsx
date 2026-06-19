import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Save, Send, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type SurveyQuestion = {
    id?: string;
    order: number;
    type: string;
    key: string;
    prompt: string;
    help_text: string | null;
    required: boolean;
    options: SurveyQuestionOptions;
};

type SurveyQuestionOptions =
    | null
    | Record<string, string | number | boolean | string[] | number[]>
    | Array<Record<string, string | number | boolean>>;

type SurveyForm = {
    title: string;
    description: string;
    questions: SurveyQuestion[];
};

type SurveyPayload = {
    id: string;
    key: string;
    version: string;
    title: string;
    description: string | null;
    status: string;
    published_at: string | null;
    questions: SurveyQuestion[];
};

type Props = {
    survey: SurveyPayload;
    questionTypes: string[];
    updateUrl: string;
    publishUrl: string;
    indexUrl: string;
};

export default function SurveyEdit({
    survey,
    questionTypes,
    updateUrl,
    publishUrl,
    indexUrl,
}: Props) {
    const form = useForm<SurveyForm>({
        title: survey.title,
        description: survey.description ?? '',
        questions: survey.questions,
    });
    const immutable = survey.status !== 'draft';

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(updateUrl);
    };

    const addQuestion = () => {
        form.setData('questions', [
            ...form.data.questions,
            {
                order: form.data.questions.length + 1,
                type: 'likert',
                key: `question_${form.data.questions.length + 1}`,
                prompt: '',
                help_text: '',
                required: true,
                options: null,
            },
        ]);
    };

    const updateQuestion = (index: number, patch: Partial<SurveyQuestion>) => {
        form.setData(
            'questions',
            form.data.questions.map((question, candidate) =>
                candidate === index ? { ...question, ...patch } : question,
            ),
        );
    };

    const removeQuestion = (index: number) => {
        form.setData(
            'questions',
            form.data.questions
                .filter((_, candidate) => candidate !== index)
                .map((question, order) => ({ ...question, order: order + 1 })),
        );
    };

    return (
        <>
            <Head title={`Edit ${survey.title}`} />

            <form onSubmit={submit} className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Link
                            href={indexUrl}
                            className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Surveys
                        </Link>
                        <div className="mt-3 flex items-center gap-3">
                            <h1 className="text-xl font-semibold">
                                {survey.title}
                            </h1>
                            <Badge variant="secondary">{survey.status}</Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {survey.key} v{survey.version}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {!immutable && (
                            <Button type="submit" disabled={form.processing}>
                                <Save className="size-4" aria-hidden="true" />
                                Save
                            </Button>
                        )}
                        {survey.status === 'draft' && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => router.post(publishUrl)}
                            >
                                <Send className="size-4" aria-hidden="true" />
                                Publish
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 rounded-md border bg-background p-4">
                    <div className="grid gap-2">
                        <Label htmlFor="title">Title</Label>
                        <Input
                            id="title"
                            value={form.data.title}
                            disabled={immutable}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            value={form.data.description}
                            disabled={immutable}
                            rows={3}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50"
                        />
                        <InputError message={form.errors.description} />
                    </div>
                </div>

                <div className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-base font-semibold">Questions</h2>
                        {!immutable && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={addQuestion}
                            >
                                <Plus className="size-4" aria-hidden="true" />
                                Question
                            </Button>
                        )}
                    </div>

                    {form.data.questions.map((question, index) => (
                        <div
                            key={question.id ?? index}
                            className="grid gap-4 rounded-md border bg-background p-4"
                        >
                            <div className="grid gap-3 md:grid-cols-[5rem_12rem_1fr_auto]">
                                <div className="grid gap-1">
                                    <Label>Order</Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        value={question.order}
                                        disabled={immutable}
                                        onChange={(event) =>
                                            updateQuestion(index, {
                                                order:
                                                    Number.parseInt(
                                                        event.target.value,
                                                        10,
                                                    ) || index + 1,
                                            })
                                        }
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label>Type</Label>
                                    <select
                                        value={question.type}
                                        disabled={immutable}
                                        onChange={(event) =>
                                            updateQuestion(index, {
                                                type: event.target.value,
                                            })
                                        }
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm disabled:opacity-50"
                                    >
                                        {questionTypes.map((type) => (
                                            <option key={type} value={type}>
                                                {type}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-1">
                                    <Label>Key</Label>
                                    <Input
                                        value={question.key}
                                        disabled={immutable}
                                        onChange={(event) =>
                                            updateQuestion(index, {
                                                key: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                                {!immutable && (
                                    <div className="flex items-end">
                                        <ActionTooltip
                                            label={`Remove question ${index + 1}`}
                                        >
                                            <Button
                                                type="button"
                                                variant="outline"
                                                aria-label={`Remove question ${index + 1}`}
                                                onClick={() =>
                                                    removeQuestion(index)
                                                }
                                            >
                                                <Trash2
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </ActionTooltip>
                                    </div>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label>Prompt</Label>
                                <textarea
                                    value={question.prompt}
                                    disabled={immutable}
                                    rows={2}
                                    onChange={(event) =>
                                        updateQuestion(index, {
                                            prompt: event.target.value,
                                        })
                                    }
                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>Help Text</Label>
                                <Input
                                    value={question.help_text ?? ''}
                                    disabled={immutable}
                                    onChange={(event) =>
                                        updateQuestion(index, {
                                            help_text: event.target.value,
                                        })
                                    }
                                />
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={question.required}
                                    disabled={immutable}
                                    onChange={(event) =>
                                        updateQuestion(index, {
                                            required: event.target.checked,
                                        })
                                    }
                                />
                                Required
                            </label>
                        </div>
                    ))}
                </div>
            </form>
        </>
    );
}

function ActionTooltip({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent side="top">{label}</TooltipContent>
        </Tooltip>
    );
}
