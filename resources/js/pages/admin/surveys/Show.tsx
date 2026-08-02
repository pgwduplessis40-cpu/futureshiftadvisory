import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BarChart3, Pencil } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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

type ScaleOption = {
    value: number;
    label: string;
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
    indexUrl: string;
    editUrl: string;
    resultsUrl: string;
};

export default function SurveyShow({
    survey,
    indexUrl,
    editUrl,
    resultsUrl,
}: Props) {
    return (
        <>
            <Head title={survey.title} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Link
                            href={indexUrl}
                            className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Surveys
                        </Link>
                        <div className="mt-3 flex flex-wrap items-center gap-3">
                            <h1 className="text-xl font-semibold">
                                {survey.title}
                            </h1>
                            <Badge variant="secondary">{survey.status}</Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {survey.key} v{survey.version}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm" variant="outline">
                            <Link href={resultsUrl}>
                                <BarChart3
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Results
                            </Link>
                        </Button>
                        {survey.status === 'draft' ? (
                            <Button asChild size="sm">
                                <Link href={editUrl}>
                                    <Pencil
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Edit
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </div>

                <section className="rounded-md border bg-background p-4">
                    <h2 className="text-sm font-medium">Survey details</h2>
                    <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                        <Detail label="Status" value={survey.status} />
                        <Detail label="Version" value={survey.version} />
                        <Detail
                            label="Published"
                            value={formatDate(survey.published_at)}
                        />
                    </dl>
                    {survey.description ? (
                        <p className="mt-4 text-sm text-muted-foreground">
                            {survey.description}
                        </p>
                    ) : null}
                </section>

                <section className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-base font-semibold">Questions</h2>
                        <Badge variant="outline">
                            {survey.questions.length}
                        </Badge>
                    </div>

                    {survey.questions.length > 0 ? (
                        <div className="space-y-3">
                            {survey.questions
                                .slice()
                                .sort((a, b) => a.order - b.order)
                                .map((question) => (
                                    <article
                                        key={question.id ?? question.key}
                                        className="rounded-md border bg-background p-4"
                                    >
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge variant="outline">
                                                        {question.order}
                                                    </Badge>
                                                    <Badge variant="secondary">
                                                        {formatLabel(
                                                            question.type,
                                                        )}
                                                    </Badge>
                                                    {question.required ? (
                                                        <Badge variant="outline">
                                                            Required
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <h3 className="mt-3 text-sm font-medium">
                                                    {question.prompt}
                                                </h3>
                                                {question.help_text ? (
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {question.help_text}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {question.key}
                                            </div>
                                        </div>
                                        <QuestionResponsePreview
                                            question={question}
                                        />
                                    </article>
                                ))}
                        </div>
                    ) : (
                        <div className="rounded-md border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground">
                            No questions have been added yet.
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 font-medium">{value}</dd>
        </div>
    );
}

function formatDate(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatLabel(value: string) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function QuestionResponsePreview({ question }: { question: SurveyQuestion }) {
    if (question.type === 'likert' || question.type === 'nps') {
        const options = ratingOptions(question);

        return (
            <div className="mt-4 border-t pt-4">
                <p className="text-xs font-medium text-muted-foreground">
                    Respondent rating scale
                </p>
                <div
                    className={
                        question.type === 'likert'
                            ? 'mt-2 grid grid-cols-5 gap-2'
                            : 'mt-2 grid grid-cols-6 gap-2 sm:grid-cols-11'
                    }
                >
                    {options.map((option) => (
                        <div
                            key={option.value}
                            className="min-w-0 border px-2 py-2 text-center"
                        >
                            <div className="text-sm font-medium">
                                {option.value}
                            </div>
                            <div className="mt-1 hidden text-xs text-muted-foreground sm:block">
                                {option.label}
                            </div>
                        </div>
                    ))}
                </div>
                <div className="mt-2 flex justify-between gap-4 text-xs text-muted-foreground">
                    <span>
                        {options.at(0)?.value} - {options.at(0)?.label}
                    </span>
                    <span className="text-right">
                        {options.at(-1)?.value} - {options.at(-1)?.label}
                    </span>
                </div>
                <div className="mt-3 border-l-2 border-[var(--fs-teal)] pl-3">
                    <p className="text-sm font-medium">
                        Optional rating context
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        What would help us understand this rating?
                    </p>
                    <div className="mt-2 min-h-10 border bg-muted/20 px-3 py-2 text-sm text-muted-foreground">
                        Respondents can add the detail behind their score.
                    </div>
                </div>
            </div>
        );
    }

    if (question.type === 'anchored_matrix') {
        const answerKeys = optionStrings(question.options, 'answer_keys');

        return (
            <div className="mt-4 border-t pt-4">
                <p className="text-xs font-medium text-muted-foreground">
                    Delivered item checks
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                    {answerKeys.map((key) => (
                        <Badge key={key} variant="outline">
                            {formatLabel(key)}
                        </Badge>
                    ))}
                </div>
            </div>
        );
    }

    if (question.type === 'text') {
        return (
            <div className="mt-4 border-t pt-4">
                <p className="text-xs font-medium text-muted-foreground">
                    Written response
                </p>
                <div className="mt-2 min-h-20 border bg-muted/20 px-3 py-2 text-sm text-muted-foreground">
                    The respondent writes their answer here.
                </div>
            </div>
        );
    }

    return null;
}

function ratingOptions(question: SurveyQuestion): ScaleOption[] {
    if (question.type === 'likert') {
        const options = Array.isArray(question.options)
            ? question.options
                  .map((option): ScaleOption | null => {
                      const value = option.value;
                      const label = option.label;

                      return typeof value === 'number' &&
                          typeof label === 'string'
                          ? { value, label }
                          : null;
                  })
                  .filter((option): option is ScaleOption => option !== null)
            : [];

        return options;
    }

    const settings = isOptionsRecord(question.options) ? question.options : {};
    const min = typeof settings.min === 'number' ? settings.min : 0;
    const max = typeof settings.max === 'number' ? settings.max : 10;
    const minLabel =
        typeof settings.min_label === 'string'
            ? settings.min_label
            : 'Not at all likely';
    const maxLabel =
        typeof settings.max_label === 'string'
            ? settings.max_label
            : 'Extremely likely';

    return Array.from({ length: max - min + 1 }, (_, index) => {
        const value = min + index;

        return {
            value,
            label:
                value === min
                    ? minLabel
                    : value === max
                      ? maxLabel
                      : String(value),
        };
    });
}

function optionStrings(options: SurveyQuestionOptions, key: string): string[] {
    if (!isOptionsRecord(options)) {
        return [];
    }

    const value = options[key];

    return Array.isArray(value)
        ? value.filter((item): item is string => typeof item === 'string')
        : [];
}

function isOptionsRecord(
    options: SurveyQuestionOptions,
): options is Record<string, string | number | boolean | string[] | number[]> {
    return options !== null && !Array.isArray(options);
}
