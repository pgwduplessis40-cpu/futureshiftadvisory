import { Head, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    Bot,
    ChevronDown,
    ChevronUp,
    CircleCheck,
    Info,
    KeyRound,
    Plus,
    PlugZap,
    RotateCw,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { CompletionSummaryBadges } from '@/components/completion-summary-badges';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type CredentialField = {
    field: string;
    config_path: string | null;
    env_fallback_path: string | null;
    status: 'active' | 'revoked' | null;
    last_four: string | null;
    rotated_at: string | null;
    revoked_at: string | null;
    set_by: string | null;
    has_env_fallback: boolean;
};

type IntegrationRow = {
    integration_key: string;
    display_name: string;
    category: string;
    fallback_mode: string;
    managed_via: 'vault' | 'environment' | string;
    wiring_status: 'wired' | 'not_wired' | string;
    purpose: string;
    api_outcome: string;
    credentials_ready: boolean;
    effective_live: boolean;
    ai_provider: boolean;
    credentials: CredentialField[];
    practice_connection?: PracticeConnection | null;
};

type PracticeConnection = {
    connected: boolean;
    tenant_name: string | null;
    tenant_id: string | null;
    connected_at: string | null;
    last_invoice_sync_at: string | null;
    connect_url: string;
    revoke_url: string | null;
};

type Props = {
    credentials: IntegrationRow[];
};

export default function IntegrationCredentialIndex({ credentials }: Props) {
    const grouped = credentials.reduce<Record<string, IntegrationRow[]>>(
        (carry, row) => {
            carry[row.category] = [...(carry[row.category] ?? []), row];

            return carry;
        },
        {},
    );

    return (
        <>
            <Head title="Integration credentials" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Integrations"
                    icon={ShieldCheck}
                    title="Integration credentials"
                    description="Store and rotate the API keys and secrets used to activate live integrations."
                />

                {Object.entries(grouped).map(([category, rows]) => (
                    <IntegrationCredentialGroup
                        key={category}
                        category={category}
                        rows={rows}
                    />
                ))}
            </div>
        </>
    );
}

function IntegrationCredentialGroup({
    category,
    rows,
}: {
    category: string;
    rows: IntegrationRow[];
}) {
    const [expanded, setExpanded] = useState(false);
    const [addAiOpen, setAddAiOpen] = useState(false);
    const categoryTitle = formatCategoryTitle(category);
    const contentId = `integration-credentials-${category}`;
    const completed = rows.filter((row) => row.credentials_ready).length;
    const isAiGroup = category === 'ai';
    const activeAi =
        rows.find((row) => row.ai_provider && row.effective_live) ?? null;

    return (
        <section className="rounded-md border bg-background">
            <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 className="text-sm font-medium capitalize">
                    {categoryTitle}
                </h2>
                <div className="flex shrink-0 items-center gap-2">
                    {isAiGroup ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="h-8"
                            onClick={() => {
                                setExpanded(true);
                                setAddAiOpen(true);
                            }}
                        >
                            <Plus className="size-4" aria-hidden="true" />
                            Add AI
                        </Button>
                    ) : null}
                    <CompletionSummaryBadges
                        total={rows.length}
                        completed={completed}
                        itemSingular="integration"
                        itemPlural="integrations"
                    />
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        className="size-8"
                        aria-controls={contentId}
                        aria-expanded={expanded}
                        aria-label={
                            expanded
                                ? `Collapse ${categoryTitle}`
                                : `Expand ${categoryTitle}`
                        }
                        title={
                            expanded
                                ? `Collapse ${categoryTitle}`
                                : `Expand ${categoryTitle}`
                        }
                        onClick={() => setExpanded((current) => !current)}
                    >
                        {expanded ? (
                            <ChevronUp className="size-4" aria-hidden="true" />
                        ) : (
                            <ChevronDown
                                className="size-4"
                                aria-hidden="true"
                            />
                        )}
                    </Button>
                </div>
            </div>

            {isAiGroup ? (
                <AddAiDialog
                    open={addAiOpen}
                    onOpenChange={setAddAiOpen}
                    activeAiName={activeAi?.display_name ?? null}
                />
            ) : null}

            <div id={contentId} hidden={!expanded} className="p-4">
                {isAiGroup ? (
                    <div className="mb-4 rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                        Activating a different AI assistant will automatically
                        deactivate the current AI assistant. Platform learning,
                        knowledge, governance rules, prompt history, and audit
                        records stay in Future Shift Advisory.
                    </div>
                ) : null}
                <div className="overflow-hidden rounded-md border">
                    <table className="fsa-responsive-table table-fixed md:table-fixed">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="w-[22%] px-3 py-2 font-medium">
                                    Integration
                                </th>
                                <th className="w-[28%] px-3 py-2 font-medium">
                                    Required keys
                                </th>
                                <th className="w-[16%] px-3 py-2 font-medium">
                                    Readiness
                                </th>
                                <th className="w-[24%] px-3 py-2 font-medium">
                                    Update
                                </th>
                                <th className="w-[10%] px-3 py-2 font-medium">
                                    Live
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <IntegrationRowView
                                    key={row.integration_key}
                                    row={row}
                                />
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    );
}

function AddAiDialog({
    open,
    onOpenChange,
    activeAiName,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    activeAiName: string | null;
}) {
    const currentAssistant = activeAiName ?? 'the current AI assistant';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Bot className="size-5" aria-hidden="true" />
                        Add AI assistant
                    </DialogTitle>
                    <DialogDescription>
                        Connect a replacement assistant while keeping Future
                        Shift Advisory knowledge and governance in the platform.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <Alert>
                        <AlertTriangle className="size-4" aria-hidden="true" />
                        <AlertTitle>
                            Only one AI assistant can be live
                        </AlertTitle>
                        <AlertDescription>
                            <p>
                                When a new AI assistant is added and activated,{' '}
                                {currentAssistant} will be deactivated
                                automatically.
                            </p>
                        </AlertDescription>
                    </Alert>

                    <div className="rounded-md border bg-muted/30 p-3 text-sm leading-6 text-muted-foreground">
                        The learning history, knowledge records, governance
                        principles, prompt versions, evidence trails, and audit
                        records remain in Future Shift Advisory. A new AI
                        assistant starts from the platform record, not from a
                        blank slate.
                    </div>
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Cancel
                        </Button>
                    </DialogClose>
                    <DialogClose asChild>
                        <Button type="button">
                            <ShieldCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            I understand
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function IntegrationRowView({ row }: { row: IntegrationRow }) {
    return (
        <tr className="border-t align-top">
            <td className="px-3 py-3" data-label="Integration">
                <div className="flex items-center gap-1.5 font-medium">
                    <span>{row.display_name}</span>
                    <IntegrationHelpTooltip row={row} />
                </div>
                <div className="text-xs break-words text-muted-foreground">
                    {row.integration_key}
                </div>
                <div className="mt-2 flex flex-wrap gap-1">
                    <Badge variant="outline">{row.fallback_mode}</Badge>
                    {row.managed_via === 'environment' ? (
                        <Badge variant="secondary">Environment</Badge>
                    ) : null}
                    {row.wiring_status !== 'wired' ? (
                        <Badge variant="destructive">Not wired</Badge>
                    ) : null}
                </div>
                <PracticeConnectionControl row={row} />
            </td>
            <td className="px-3 py-3" data-label="Required keys">
                <CredentialList row={row} />
            </td>
            <td className="px-3 py-3" data-label="Readiness">
                <ReadinessBadge row={row} />
            </td>
            <td className="px-3 py-3" data-label="Update">
                {row.managed_via === 'vault' ? (
                    <div className="space-y-3">
                        {row.credentials.map((credential) => (
                            <CredentialEditor
                                key={credential.field}
                                row={row}
                                credential={credential}
                            />
                        ))}
                        {row.credentials.length === 0 ? (
                            <div className="text-xs text-muted-foreground">
                                No in-app credential fields
                            </div>
                        ) : null}
                    </div>
                ) : (
                    <div className="text-xs text-muted-foreground">
                        Deployment environment
                    </div>
                )}
            </td>
            <td className="px-3 py-3" data-label="Live">
                <LiveControl row={row} />
            </td>
        </tr>
    );
}

function PracticeConnectionControl({ row }: { row: IntegrationRow }) {
    const connection = row.practice_connection;
    const revokeForm = useForm({});

    if (!connection) {
        return null;
    }

    const canConnect = row.effective_live;

    return (
        <div className="mt-3 space-y-2 rounded-md border bg-muted/30 p-2">
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-xs font-medium">Practice Xero</span>
                {connection.connected ? (
                    <Badge variant="secondary">Connected</Badge>
                ) : (
                    <Badge variant="outline">Not connected</Badge>
                )}
            </div>
            <div className="text-xs break-words text-muted-foreground">
                {connection.connected
                    ? `${connection.tenant_name ?? 'Xero organisation'}${connection.tenant_id ? ` (${connection.tenant_id})` : ''}`
                    : "Connect Future Shift Advisory's Xero organisation before accepted proposals can create invoice batches."}
            </div>
            {connection.last_invoice_sync_at ? (
                <div className="text-xs text-muted-foreground">
                    Last invoice sync{' '}
                    {formatDateTime(connection.last_invoice_sync_at)}
                </div>
            ) : null}
            <div className="flex flex-wrap gap-2">
                {canConnect ? (
                    <Button asChild size="sm" variant="outline">
                        <a href={connection.connect_url}>
                            <PlugZap className="size-4" aria-hidden="true" />
                            {connection.connected ? 'Reconnect' : 'Connect'}
                        </a>
                    </Button>
                ) : (
                    <Button size="sm" variant="outline" disabled>
                        <PlugZap className="size-4" aria-hidden="true" />
                        Connect
                    </Button>
                )}
                {connection.connected && connection.revoke_url ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={revokeForm.processing}
                        onClick={() =>
                            revokeForm.patch(connection.revoke_url ?? '', {
                                preserveScroll: true,
                            })
                        }
                    >
                        <Ban className="size-4" aria-hidden="true" />
                        Revoke
                    </Button>
                ) : null}
            </div>
        </div>
    );
}

function IntegrationHelpTooltip({ row }: { row: IntegrationRow }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    className="inline-flex size-5 items-center justify-center rounded-full text-muted-foreground outline-none hover:bg-muted hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    aria-label={`About ${row.display_name}`}
                >
                    <Info className="size-3.5" aria-hidden="true" />
                </button>
            </TooltipTrigger>
            <TooltipContent
                side="right"
                align="start"
                className="max-w-sm space-y-3 p-3 text-left"
            >
                <div>
                    <div className="text-xs font-semibold tracking-normal text-primary-foreground/80 uppercase">
                        What it is
                    </div>
                    <p className="mt-1 text-xs leading-5">{row.purpose}</p>
                </div>
                <div>
                    <div className="text-xs font-semibold tracking-normal text-primary-foreground/80 uppercase">
                        What the API enables
                    </div>
                    <p className="mt-1 text-xs leading-5">{row.api_outcome}</p>
                </div>
            </TooltipContent>
        </Tooltip>
    );
}

function CredentialList({ row }: { row: IntegrationRow }) {
    if (row.credentials.length === 0) {
        return <div className="text-xs text-muted-foreground">None</div>;
    }

    return (
        <div className="space-y-2">
            {row.credentials.map((credential) => (
                <div key={credential.field}>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-medium">{credential.field}</span>
                        <CredentialStatus credential={credential} />
                    </div>
                    <div className="text-xs break-words text-muted-foreground">
                        {credential.config_path ?? 'No config path'}
                    </div>
                </div>
            ))}
        </div>
    );
}

function CredentialEditor({
    row,
    credential,
}: {
    row: IntegrationRow;
    credential: CredentialField;
}) {
    const setForm = useForm({
        integration_key: row.integration_key,
        field: credential.field,
        value: '',
    });
    const revokeForm = useForm({
        integration_key: row.integration_key,
        field: credential.field,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        setForm.post('/admin/integration-credentials', {
            preserveScroll: true,
            onSuccess: () => setForm.reset('value'),
        });
    }

    function revoke() {
        revokeForm.patch('/admin/integration-credentials/revoke', {
            preserveScroll: true,
        });
    }

    return (
        <form onSubmit={submit} className="space-y-2">
            <div className="grid min-w-0 gap-2 sm:grid-cols-[1fr_auto_auto]">
                <div>
                    <Label
                        htmlFor={`${row.integration_key}-${credential.field}`}
                        className="sr-only"
                    >
                        {credential.field}
                    </Label>
                    <Input
                        id={`${row.integration_key}-${credential.field}`}
                        type="password"
                        autoComplete="off"
                        value={setForm.data.value}
                        onChange={(event) =>
                            setForm.setData('value', event.target.value)
                        }
                    />
                    <InputError message={setForm.errors.value} />
                </div>
                <ActionTooltip
                    label={
                        credential.status === 'active'
                            ? `Rotate ${credential.field} credential`
                            : `Save ${credential.field} credential`
                    }
                >
                    <Button
                        type="submit"
                        size="icon"
                        disabled={setForm.processing}
                        aria-label={`Save ${credential.field}`}
                    >
                        {credential.status === 'active' ? (
                            <RotateCw className="size-4" aria-hidden="true" />
                        ) : (
                            <KeyRound className="size-4" aria-hidden="true" />
                        )}
                    </Button>
                </ActionTooltip>
                <ActionTooltip
                    label={
                        credential.status === 'active'
                            ? `Revoke ${credential.field} credential`
                            : `${credential.field} credential is not active`
                    }
                >
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        disabled={
                            credential.status !== 'active' ||
                            revokeForm.processing
                        }
                        onClick={revoke}
                        aria-label={`Revoke ${credential.field}`}
                    >
                        <Ban className="size-4" aria-hidden="true" />
                    </Button>
                </ActionTooltip>
            </div>
        </form>
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
            <TooltipTrigger asChild>
                <span className="inline-flex">{children}</span>
            </TooltipTrigger>
            <TooltipContent side="top">{label}</TooltipContent>
        </Tooltip>
    );
}

function CredentialStatus({ credential }: { credential: CredentialField }) {
    if (credential.status === 'active') {
        return (
            <Badge variant="secondary">
                Active
                {credential.last_four ? ` ...${credential.last_four}` : ''}
            </Badge>
        );
    }

    if (credential.status === 'revoked') {
        return <Badge variant="destructive">Revoked</Badge>;
    }

    if (credential.has_env_fallback) {
        return <Badge variant="outline">Environment</Badge>;
    }

    return <Badge variant="outline">Unset</Badge>;
}

function formatDateTime(value: string) {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatCategoryTitle(category: string) {
    if (category === 'ai') {
        return 'AI';
    }

    return category.replaceAll('_', ' ');
}

function ReadinessBadge({ row }: { row: IntegrationRow }) {
    if (row.credentials_ready) {
        return (
            <Badge variant="secondary">
                <CircleCheck className="size-3" aria-hidden="true" />
                Ready
            </Badge>
        );
    }

    return <Badge variant="outline">Needs setup</Badge>;
}

function LiveControl({ row }: { row: IntegrationRow }) {
    const activateForm = useForm({
        integration_key: row.integration_key,
    });
    const deactivateForm = useForm({
        integration_key: row.integration_key,
    });

    if (row.managed_via !== 'vault') {
        return row.credentials_ready ? (
            <Badge variant="secondary">Configured</Badge>
        ) : (
            <Badge variant="outline">Environment</Badge>
        );
    }

    if (row.wiring_status !== 'wired') {
        return <Badge variant="outline">Disabled</Badge>;
    }

    if (row.effective_live) {
        return (
            <ActionTooltip label={`Deactivate ${row.display_name}`}>
                <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    disabled={deactivateForm.processing}
                    onClick={() =>
                        deactivateForm.patch(
                            '/admin/integration-credentials/deactivate',
                            { preserveScroll: true },
                        )
                    }
                >
                    <Ban className="size-4" aria-hidden="true" />
                    On
                </Button>
            </ActionTooltip>
        );
    }

    return (
        <ActionTooltip
            label={
                row.credentials_ready
                    ? `Activate ${row.display_name}`
                    : `Add required credentials before activating ${row.display_name}`
            }
        >
            <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={!row.credentials_ready || activateForm.processing}
                onClick={() =>
                    activateForm.patch(
                        '/admin/integration-credentials/activate',
                        {
                            preserveScroll: true,
                        },
                    )
                }
            >
                <ShieldCheck className="size-4" aria-hidden="true" />
                Off
            </Button>
        </ActionTooltip>
    );
}

IntegrationCredentialIndex.layout = {
    breadcrumbs: [
        {
            title: 'Integration credentials',
            href: '/admin/integration-credentials',
        },
    ],
};
