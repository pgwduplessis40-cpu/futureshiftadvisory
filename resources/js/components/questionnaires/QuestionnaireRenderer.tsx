import { ChevronDown, CircleCheck, Upload, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { queueDocumentUpload } from '@/lib/portal-offline';
import { evaluateVisibleQuestionIds } from '@/lib/questionnaires/conditional-logic';
import { cn } from '@/lib/utils';
import type {
    QuestionnaireAnswer,
    QuestionnaireAnswers,
    QuestionnaireQuestion,
    QuestionnaireSchema,
} from '@/types/questionnaire';

type Props = {
    schema: QuestionnaireSchema;
    answers: QuestionnaireAnswers;
    errors?: Record<string, string | undefined>;
    onChange?: (answers: QuestionnaireAnswers) => void;
    readOnly?: boolean;
    uploadUrl?: string;
    clientId?: string;
    collapsibleSections?: boolean;
    showProgress?: boolean;
};

type UploadedDocument = {
    id: string;
    original_filename: string;
};

export function QuestionnaireRenderer({
    schema,
    answers,
    errors = {},
    onChange,
    readOnly = false,
    uploadUrl,
    clientId,
    collapsibleSections = false,
    showProgress = false,
}: Props) {
    const [uploadedDocuments, setUploadedDocuments] = useState<
        Record<string, UploadedDocument>
    >({});
    const visibleQuestionIds = evaluateVisibleQuestionIds(schema, answers);
    const sections = schema.sections
        .map((section) => {
            const questions = section.questions.filter((question) =>
                visibleQuestionIds.has(question.id),
            );
            const requiredQuestions = questions.filter(
                (question) => question.required,
            );
            const answeredCount = questions.filter((question) =>
                hasAnswer(question, answers[question.id] ?? emptyAnswer()),
            ).length;
            const requiredAnsweredCount = requiredQuestions.filter((question) =>
                hasAnswer(question, answers[question.id] ?? emptyAnswer()),
            ).length;

            return {
                ...section,
                questions,
                answeredCount,
                requiredCount: requiredQuestions.length,
                requiredAnsweredCount,
                ready:
                    requiredQuestions.length > 0
                        ? requiredAnsweredCount === requiredQuestions.length
                        : answeredCount === questions.length,
            };
        })
        .filter((section) => section.questions.length > 0);
    const requiredCount = sections.reduce(
        (total, section) => total + section.requiredCount,
        0,
    );
    const requiredAnsweredCount = sections.reduce(
        (total, section) => total + section.requiredAnsweredCount,
        0,
    );
    const questionCount = sections.reduce(
        (total, section) => total + section.questions.length,
        0,
    );
    const answeredCount = sections.reduce(
        (total, section) => total + section.answeredCount,
        0,
    );
    const progressTotal = requiredCount || questionCount;
    const progressCompleted = requiredCount
        ? requiredAnsweredCount
        : answeredCount;
    const progressPercentage = progressTotal
        ? Math.round((progressCompleted / progressTotal) * 100)
        : 0;
    const nextSection = sections.find((section) => !section.ready);
    const [openSectionId, setOpenSectionId] = useState<string | null>(null);
    const defaultOpenSectionId = nextSection?.id ?? sections[0]?.id ?? null;
    const activeSectionId =
        openSectionId === null ? defaultOpenSectionId : openSectionId;

    const updateAnswer = (
        questionId: string,
        patch: Partial<QuestionnaireAnswer>,
    ) => {
        if (!onChange || readOnly) {
            return;
        }

        onChange({
            ...answers,
            [questionId]: {
                ...emptyAnswer(),
                ...(answers[questionId] ?? {}),
                ...patch,
            },
        });
    };

    return (
        <div className="space-y-4">
            {showProgress && progressTotal > 0 && (
                <section
                    className="border-b pb-4"
                    aria-label="Questionnaire completion"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span className="font-medium">Your progress</span>
                        <span className="text-muted-foreground">
                            {progressCompleted} of {progressTotal}{' '}
                            {requiredCount ? 'required answers' : 'answers'}
                        </span>
                    </div>
                    <div
                        className="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-valuemin={0}
                        aria-valuemax={progressTotal}
                        aria-valuenow={progressCompleted}
                        aria-label="Questionnaire completion"
                    >
                        <div
                            className="h-full rounded-full bg-emerald-600 transition-[width]"
                            style={{ width: `${progressPercentage}%` }}
                        />
                    </div>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {nextSection
                            ? `Next focus: ${nextSection.title}.`
                            : 'Every required answer is ready for review.'}
                    </p>
                </section>
            )}

            {sections.map((section) => {
                const expanded =
                    !collapsibleSections || activeSectionId === section.id;
                const header = (
                    <div className="flex min-w-0 flex-1 items-start gap-3 text-left">
                        <CircleCheck
                            className={cn(
                                'mt-0.5 size-5 shrink-0',
                                section.ready
                                    ? 'text-emerald-600'
                                    : 'text-muted-foreground',
                            )}
                            aria-hidden="true"
                        />
                        <div className="min-w-0 flex-1">
                            <h3
                                id={`section-${section.id}`}
                                className="text-base font-medium"
                            >
                                {section.title}
                            </h3>
                            {section.help_text && (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {section.help_text}
                                </p>
                            )}
                        </div>
                        <span className="shrink-0 text-sm text-muted-foreground">
                            {section.requiredCount
                                ? `${section.requiredAnsweredCount}/${section.requiredCount} required`
                                : `${section.answeredCount}/${section.questions.length} answered`}
                        </span>
                    </div>
                );
                const fields = (
                    <div className="space-y-4 pb-4">
                        {section.questions.map((question) => (
                            <QuestionField
                                key={question.id}
                                question={question}
                                answer={answers[question.id] ?? emptyAnswer()}
                                error={
                                    errors[`answers.${question.id}.value`] ??
                                    errors[
                                        `answers.${question.id}.attached_document_ids`
                                    ]
                                }
                                readOnly={readOnly}
                                uploadUrl={uploadUrl}
                                clientId={clientId}
                                uploadedDocuments={uploadedDocuments}
                                onDocumentUploaded={(document) =>
                                    setUploadedDocuments((current) => ({
                                        ...current,
                                        [document.id]: document,
                                    }))
                                }
                                onChange={(answer) =>
                                    updateAnswer(question.id, answer)
                                }
                            />
                        ))}
                    </div>
                );

                if (!collapsibleSections) {
                    return (
                        <section
                            key={section.id}
                            className="space-y-4 border-t pt-5 first:border-t-0 first:pt-0"
                            aria-labelledby={`section-${section.id}`}
                        >
                            {header}
                            {fields}
                        </section>
                    );
                }

                return (
                    <Collapsible
                        key={section.id}
                        open={expanded}
                        onOpenChange={(open) =>
                            setOpenSectionId(open ? section.id : '')
                        }
                        className="border-b last:border-b-0"
                    >
                        <CollapsibleTrigger asChild>
                            <button
                                type="button"
                                className="flex w-full items-start gap-3 py-4 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                aria-labelledby={`section-${section.id}`}
                            >
                                {header}
                                <ChevronDown
                                    className={cn(
                                        'mt-1 size-4 shrink-0 text-muted-foreground transition-transform',
                                        expanded && 'rotate-180',
                                    )}
                                    aria-hidden="true"
                                />
                            </button>
                        </CollapsibleTrigger>
                        <CollapsibleContent>{fields}</CollapsibleContent>
                    </Collapsible>
                );
            })}
        </div>
    );
}

function QuestionField({
    question,
    answer,
    error,
    readOnly,
    uploadUrl,
    clientId,
    uploadedDocuments,
    onDocumentUploaded,
    onChange,
}: {
    question: QuestionnaireQuestion;
    answer: QuestionnaireAnswer;
    error?: string;
    readOnly: boolean;
    uploadUrl?: string;
    clientId?: string;
    uploadedDocuments: Record<string, UploadedDocument>;
    onDocumentUploaded: (document: UploadedDocument) => void;
    onChange: (answer: Partial<QuestionnaireAnswer>) => void;
}) {
    const fieldId = `question-${question.id}`;

    return (
        <div className="space-y-2 rounded-md border p-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <Label htmlFor={fieldId}>{question.prompt}</Label>
                    {question.help_text && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {question.help_text}
                        </p>
                    )}
                </div>
                {question.required && (
                    <Badge variant="outline" className="shrink-0">
                        Required
                    </Badge>
                )}
            </div>

            {!(question.type === 'file-attach' && uploadUrl && !readOnly) && (
                <QuestionControl
                    id={fieldId}
                    question={question}
                    answer={answer}
                    readOnly={readOnly}
                    onChange={onChange}
                />
            )}

            {uploadUrl && !readOnly && question.type === 'file-attach' && (
                <DocumentAttachmentControl
                    question={question}
                    answer={answer}
                    uploadUrl={uploadUrl}
                    clientId={clientId}
                    uploadedDocuments={uploadedDocuments}
                    onDocumentUploaded={onDocumentUploaded}
                    onChange={onChange}
                />
            )}

            <InputError message={error} />
        </div>
    );
}

function QuestionControl({
    id,
    question,
    answer,
    readOnly,
    onChange,
}: {
    id: string;
    question: QuestionnaireQuestion;
    answer: QuestionnaireAnswer;
    readOnly: boolean;
    onChange: (answer: Partial<QuestionnaireAnswer>) => void;
}) {
    const disabled = readOnly;

    switch (question.type) {
        case 'long-text':
            return (
                <textarea
                    id={id}
                    value={stringValue(answer.value)}
                    rows={4}
                    disabled={disabled}
                    onChange={(event) =>
                        onChange({ value: event.target.value })
                    }
                    className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                />
            );
        case 'number':
        case 'currency':
            return (
                <Input
                    id={id}
                    type="number"
                    step={question.type === 'currency' ? '0.01' : '1'}
                    value={stringValue(answer.value)}
                    disabled={disabled}
                    onChange={(event) =>
                        onChange({ value: event.target.value })
                    }
                />
            );
        case 'date':
            return (
                <Input
                    id={id}
                    type="date"
                    value={stringValue(answer.value)}
                    disabled={disabled}
                    onChange={(event) =>
                        onChange({ value: event.target.value })
                    }
                />
            );
        case 'single-select':
            return (
                <select
                    id={id}
                    value={stringValue(answer.value)}
                    disabled={disabled}
                    onChange={(event) =>
                        onChange({ value: event.target.value })
                    }
                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="">Select</option>
                    {question.options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            );
        case 'multi-select':
            return (
                <div className="grid gap-2 sm:grid-cols-2">
                    {question.options.map((option) => {
                        const selected = arrayValue(answer.value).includes(
                            option.value,
                        );

                        return (
                            <label
                                key={option.value}
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    checked={selected}
                                    disabled={disabled}
                                    onCheckedChange={(checked) => {
                                        const current = arrayValue(
                                            answer.value,
                                        );
                                        onChange({
                                            value:
                                                checked === true
                                                    ? [...current, option.value]
                                                    : current.filter(
                                                          (value) =>
                                                              value !==
                                                              option.value,
                                                      ),
                                        });
                                    }}
                                />
                                {option.label}
                            </label>
                        );
                    })}
                </div>
            );
        case 'likert':
            return (
                <div className="grid gap-2 sm:grid-cols-5">
                    {question.options.map((option) => (
                        <label
                            key={option.value}
                            className="flex min-h-14 items-center gap-2 rounded-md border px-3 py-2 text-sm"
                        >
                            <input
                                type="radio"
                                name={id}
                                value={option.value}
                                checked={answer.value === option.value}
                                disabled={disabled}
                                onChange={(event) =>
                                    onChange({ value: event.target.value })
                                }
                            />
                            {option.label}
                        </label>
                    ))}
                </div>
            );
        case 'file-attach':
            return (
                <div className="flex flex-wrap items-center gap-2 rounded-md border border-dashed px-3 py-3 text-sm text-muted-foreground">
                    {answer.attached_document_ids.length > 0 ? (
                        answer.attached_document_ids.map((documentId) => (
                            <Badge key={documentId} variant="secondary">
                                {documentId}
                            </Badge>
                        ))
                    ) : (
                        <span>No documents attached yet.</span>
                    )}
                </div>
            );
        case 'text':
        default:
            return (
                <Input
                    id={id}
                    value={stringValue(answer.value)}
                    disabled={disabled}
                    onChange={(event) =>
                        onChange({ value: event.target.value })
                    }
                />
            );
    }
}

function DocumentAttachmentControl({
    question,
    answer,
    uploadUrl,
    clientId,
    uploadedDocuments,
    onDocumentUploaded,
    onChange,
}: {
    question: QuestionnaireQuestion;
    answer: QuestionnaireAnswer;
    uploadUrl: string;
    clientId?: string;
    uploadedDocuments: Record<string, UploadedDocument>;
    onDocumentUploaded: (document: UploadedDocument) => void;
    onChange: (answer: Partial<QuestionnaireAnswer>) => void;
}) {
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const upload = async () => {
        if (!file) {
            return;
        }

        setUploading(true);
        setError(null);

        const fields = {
            category: 'plan_attachment',
            question_id: question.id,
            question_prompt: question.prompt,
            claim_value: claimValue(question, answer),
        };

        if (!navigator.onLine) {
            if (!clientId) {
                setError('Client context is unavailable for offline upload.');
                setUploading(false);

                return;
            }

            try {
                const document = await queueDocumentUpload({
                    url: uploadUrl,
                    file,
                    fields,
                    clientId,
                });

                onDocumentUploaded(document);
                onChange({
                    attached_document_ids: unique([
                        ...answer.attached_document_ids,
                        document.id,
                    ]),
                });
                setFile(null);
                toast.success('Document queued for sync.');
            } catch {
                setError('Offline upload queue failed.');
            } finally {
                setUploading(false);
            }

            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        Object.entries(fields).forEach(([key, value]) => {
            formData.append(key, value);
        });

        const response = await fetch(uploadUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: formData,
        });

        setUploading(false);

        if (!response.ok) {
            const payload = (await response.json().catch(() => null)) as {
                message?: string;
            } | null;
            setError(payload?.message ?? 'Upload failed.');

            return;
        }

        const payload = (await response.json()) as {
            document?: UploadedDocument;
        };
        const document = payload.document;

        if (!document) {
            setError('Upload response was missing document details.');

            return;
        }

        onDocumentUploaded(document);
        onChange({
            attached_document_ids: unique([
                ...answer.attached_document_ids,
                document.id,
            ]),
        });
        setFile(null);
    };

    return (
        <div className="space-y-2 rounded-md border border-dashed p-3">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div className="min-w-0 flex-1">
                    <FileDropzone
                        id={`document_${question.id}`}
                        files={file ? [file] : []}
                        label="Attach document"
                        description="Drag a document here or browse"
                        onFilesChange={(files) => setFile(files[0] ?? null)}
                    />
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={!file || uploading}
                    onClick={() => void upload()}
                >
                    <Upload className="size-4" aria-hidden="true" />
                    {uploading ? 'Uploading' : 'Upload'}
                </Button>
            </div>

            {answer.attached_document_ids.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {answer.attached_document_ids.map((documentId) => (
                        <Badge
                            key={documentId}
                            variant="secondary"
                            className="gap-2"
                        >
                            {uploadedDocuments[documentId]?.original_filename ??
                                documentId}
                            <button
                                type="button"
                                className="rounded-xs outline-none focus-visible:ring-[2px] focus-visible:ring-ring"
                                aria-label="Remove attached document"
                                onClick={() =>
                                    onChange({
                                        attached_document_ids:
                                            answer.attached_document_ids.filter(
                                                (id) => id !== documentId,
                                            ),
                                    })
                                }
                            >
                                <X className="size-3" aria-hidden="true" />
                            </button>
                        </Badge>
                    ))}
                </div>
            )}

            <InputError message={error ?? undefined} />
        </div>
    );
}

function emptyAnswer(): QuestionnaireAnswer {
    return {
        value: null,
        attached_document_ids: [],
    };
}

function hasAnswer(
    question: QuestionnaireQuestion,
    answer: QuestionnaireAnswer,
): boolean {
    if (question.type === 'file-attach') {
        return answer.attached_document_ids.length > 0;
    }

    if (Array.isArray(answer.value)) {
        return answer.value.length > 0;
    }

    return answer.value !== null && String(answer.value).trim() !== '';
}

function stringValue(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : '';
}

function arrayValue(value: unknown): string[] {
    return Array.isArray(value) ? value.map(String) : [];
}

function claimValue(
    question: QuestionnaireQuestion,
    answer: QuestionnaireAnswer,
): string {
    if (Array.isArray(answer.value)) {
        return answer.value.join(', ');
    }

    if (typeof answer.value === 'string' || typeof answer.value === 'number') {
        const value = String(answer.value).trim();

        return value === '' ? question.prompt : value;
    }

    return question.prompt;
}

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function unique(values: string[]): string[] {
    return Array.from(new Set(values));
}
