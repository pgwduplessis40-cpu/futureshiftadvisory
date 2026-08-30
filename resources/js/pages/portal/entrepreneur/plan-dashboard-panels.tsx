import { RotateCcw } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

import { FormattedMarkdown } from '@/components/formatted-textarea';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatNzDate } from '@/lib/formatters';
import { cn } from '@/lib/utils';

export type Tab = 'actions' | 'information';

export type IdeaValidationVersion = {
    id: string;
    revision_number: number;
    problem: string;
    target_customer: string;
    demand_signal: string;
    evaluated_at: string | null;
    advisor_gate_status: string;
    recalled_at: string | null;
    is_current: boolean;
    restore_url: string;
};

export const ideaFields = [
    {
        key: 'problem',
        label: 'Problem',
        minimum: 5,
        placeholder: 'What specific customer problem are you solving?',
        plain: 'What is hard, costly, risky, or frustrating for the customer right now?',
    },
    {
        key: 'target_customer',
        label: 'Target customer',
        minimum: 3,
        placeholder: 'Who has this problem and how do you know?',
        plain: 'Who exactly would pay attention to this problem first?',
    },
    {
        key: 'solution',
        label: 'Solution',
        minimum: 10,
        placeholder: 'What will you offer and how will it work?',
        plain: 'What will you sell or deliver, and what changes for the customer?',
    },
    {
        key: 'value_proposition',
        label: 'Value proposition',
        minimum: 10,
        placeholder: 'Why would the customer choose this over alternatives?',
        plain: 'Why would a customer choose you instead of doing nothing or choosing another option?',
    },
    {
        key: 'demand_signal',
        label: 'Demand signal',
        minimum: 5,
        placeholder: 'What evidence shows people want or need this?',
        plain: 'What have real people done or said that shows this is worth testing further?',
    },
    {
        key: 'revenue_model',
        label: 'Revenue model',
        minimum: 5,
        placeholder: 'How will the business earn, collect, and retain revenue?',
        plain: 'How will money come in, how often, and from whom?',
    },
] satisfies {
    key:
        | 'problem'
        | 'target_customer'
        | 'solution'
        | 'value_proposition'
        | 'demand_signal'
        | 'revenue_model';
    label: string;
    minimum: number;
    placeholder: string;
    plain: string;
}[];

const plainLanguageTerms = [
    {
        term: 'Target customer',
        meaning:
            'The specific person or business most likely to need this first.',
    },
    {
        term: 'Demand signal',
        meaning:
            'Evidence that someone wants the offer, such as interviews, bookings, pilots, deposits, or repeated requests.',
    },
    {
        term: 'Value proposition',
        meaning:
            'The reason a customer would choose this option instead of another option or doing nothing.',
    },
    {
        term: 'Revenue model',
        meaning:
            'How the business gets paid, how often it gets paid, and what keeps that income going.',
    },
    {
        term: 'Evidence',
        meaning:
            'Real support for a claim: a quote, customer note, test result, sale, supplier price, contract, or clear calculation.',
    },
    {
        term: 'Advisory ready',
        meaning:
            'Ready for an advisor to rely on the plan enough to agree the next service or roadmap.',
    },
] satisfies Array<{ term: string; meaning: string }>;

export function TabList({
    activeTab,
    onChange,
}: {
    activeTab: Tab;
    onChange: (tab: Tab) => void;
}) {
    return (
        <div
            className="inline-flex w-full max-w-md rounded-md border bg-muted/30 p-1"
            role="tablist"
            aria-label="Business plan sections"
        >
            {(['actions', 'information'] as Tab[]).map((tab) => (
                <button
                    key={tab}
                    type="button"
                    role="tab"
                    aria-selected={activeTab === tab}
                    className={cn(
                        'flex-1 rounded-sm px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                        activeTab === tab &&
                            'bg-background text-foreground shadow-xs',
                    )}
                    onClick={() => onChange(tab)}
                >
                    {formatLabel(tab)}
                </button>
            ))}
        </div>
    );
}

export function PlainLanguageGuide() {
    return (
        <section className="space-y-3 rounded-md border bg-background p-4">
            <div>
                <h2 className="text-sm font-medium">Plain English guide</h2>
                <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                    These are the business terms used in the workspace, written
                    as the practical questions your advisor needs answered.
                </p>
            </div>
            <dl className="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-3">
                {plainLanguageTerms.map((term) => (
                    <div key={term.term}>
                        <dt className="font-medium">{term.term}</dt>
                        <dd className="mt-1 text-muted-foreground">
                            {term.meaning}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

export function ActionPanel({
    icon: Icon,
    title,
    value,
    explanation,
    children,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    title: string;
    value: ReactNode;
    explanation: string;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <div className="flex items-center gap-2 text-sm font-medium">
                                <Icon className="size-4" aria-hidden={true} />
                                {title}
                            </div>
                            <div className="mt-2 text-sm text-muted-foreground">
                                {value}
                            </div>
                        </div>
                    </div>
                    {children}
                </section>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
    );
}

export function IdeaValidationSnapshot({
    fields,
    revisionNumber,
    submittedAt,
}: {
    fields: { label: string; value: string }[];
    revisionNumber: number | null;
    submittedAt: string | null;
}) {
    return (
        <div className="rounded-md border bg-muted/20 p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="text-xs font-medium text-muted-foreground">
                    Submitted idea validation
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {revisionNumber ? (
                        <Badge variant="outline">
                            Version {revisionNumber}
                        </Badge>
                    ) : null}
                    {submittedAt ? (
                        <Badge variant="outline">
                            Submitted {formatDate(submittedAt)}
                        </Badge>
                    ) : null}
                </div>
            </div>
            <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {fields.map((field) => (
                    <div
                        key={field.label}
                        className="rounded-md border bg-card p-3"
                    >
                        <div className="text-xs font-medium text-muted-foreground">
                            {field.label}
                        </div>
                        <FormattedMarkdown
                            value={field.value}
                            className="mt-1"
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}

export function IdeaValidationHistory({
    versions,
    restoringVersionId,
    onRestore,
}: {
    versions: IdeaValidationVersion[];
    restoringVersionId: string | null;
    onRestore: (version: IdeaValidationVersion) => void;
}) {
    return (
        <div className="space-y-3 border-t pt-4">
            <h3 className="text-sm font-medium">Revision history</h3>
            <div className="grid gap-3 lg:grid-cols-2">
                {versions.map((version) => (
                    <div
                        key={version.id}
                        className="space-y-3 rounded-md border bg-muted/20 p-3"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-medium">
                                    Version {version.revision_number}
                                </span>
                                {version.is_current ? (
                                    <Badge variant="secondary">Current</Badge>
                                ) : (
                                    <Badge variant="outline">
                                        {ideaVersionStatusLabel(version)}
                                    </Badge>
                                )}
                            </div>
                            {version.evaluated_at ? (
                                <span className="text-xs text-muted-foreground">
                                    {formatDate(version.evaluated_at)}
                                </span>
                            ) : null}
                        </div>
                        <dl className="grid gap-2 text-sm">
                            <VersionDetail
                                label="Problem"
                                value={version.problem}
                            />
                            <VersionDetail
                                label="Target customer"
                                value={version.target_customer}
                            />
                            <VersionDetail
                                label="Demand signal"
                                value={version.demand_signal}
                            />
                        </dl>
                        {!version.is_current ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={restoringVersionId !== null}
                                onClick={() => onRestore(version)}
                            >
                                <RotateCcw
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {restoringVersionId === version.id
                                    ? 'Restoring...'
                                    : 'Restore as new revision'}
                            </Button>
                        ) : null}
                    </div>
                ))}
            </div>
        </div>
    );
}

export function Detail({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="grid grid-cols-[120px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0 break-words">{value || '-'}</dd>
        </div>
    );
}

export function displayStageLabel(
    stage: string | null | undefined,
    label: string | null | undefined,
): string {
    if (stage === 'onboarding' || label === 'Onboarding') {
        return 'Getting started';
    }

    return label ?? '-';
}

export function journeyLevelLabel(
    level:
        | {
              stage?: string | null;
              phase?: string | number | null;
              label: string;
          }
        | null
        | undefined,
): string {
    if (!level) {
        return 'Journey active';
    }

    if (level.stage === 'onboarding') {
        return level.phase
            ? `Getting started phase ${level.phase}`
            : 'Getting started';
    }

    return level.label;
}

function VersionDetail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs font-medium text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-1">
                <FormattedMarkdown value={value} />
            </dd>
        </div>
    );
}

function ideaVersionStatusLabel(version: IdeaValidationVersion): string {
    if (version.recalled_at) {
        return 'Recalled';
    }

    if (version.advisor_gate_status === 'approved') {
        return 'Approved';
    }

    if (version.advisor_gate_status === 'changes_requested') {
        return 'Changes requested';
    }

    return 'Advisor review';
}

export function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

export function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return formatNzDate(value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
