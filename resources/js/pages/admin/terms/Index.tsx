import { Head, Link, router } from '@inertiajs/react';
import {
    Download,
    Eye,
    FilePlus,
    History,
    LockKeyhole,
    Pencil,
    Send,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type {
    TermsEnforcementState,
    TermsVersion,
    TermsWorkspace,
} from './types';

type Props = {
    versions: TermsVersion[];
    enforcement: TermsEnforcementState | null;
    workspace: TermsWorkspace;
};

export default function TermsIndex({
    versions,
    enforcement,
    workspace,
}: Props) {
    return (
        <>
            <Head title={workspace.label} />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <History className="size-4" aria-hidden="true" />
                            Version history
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            {workspace.label}
                        </h1>
                    </div>
                    <Button
                        size="sm"
                        type="button"
                        onClick={() => router.post(workspace.store_url)}
                    >
                        <FilePlus className="size-4" aria-hidden="true" />
                        Draft
                    </Button>
                </div>

                {enforcement && workspace.enforcement_url ? (
                    <section className="rounded-md border bg-background p-4">
                        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div className="flex gap-3">
                                <LockKeyhole
                                    className="mt-0.5 size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-sm font-medium">
                                            Terms acceptance enforcement
                                        </h2>
                                        <Badge
                                            variant={
                                                enforcement.active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {enforcement.active
                                                ? 'active'
                                                : 'inactive'}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {enforcement.active
                                            ? `Compulsory acceptance was activated${enforcement.activated_at ? ` on ${formatDate(enforcement.activated_at)}` : ''}. It cannot be deactivated.`
                                            : enforcement.latest_published_version
                                              ? `A published Terms & Conditions version is available, but customers can keep testing until enforcement is activated. Latest published version: ${enforcement.latest_published_version.version}.`
                                              : 'Publish a Terms & Conditions version before activating compulsory acceptance.'}
                                    </p>
                                </div>
                            </div>

                            {!enforcement.active ? (
                                <Button
                                    size="sm"
                                    type="button"
                                    disabled={!enforcement.can_activate}
                                    onClick={() => {
                                        if (workspace.enforcement_url) {
                                            router.post(
                                                workspace.enforcement_url,
                                            );
                                        }
                                    }}
                                >
                                    <LockKeyhole
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Activate
                                </Button>
                            ) : null}
                        </div>
                    </section>
                ) : null}

                <div className="overflow-hidden rounded-md border">
                    <table className="fsa-responsive-table">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Version
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Material
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Clause classes
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Clauses
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {versions.map((version) => (
                                <tr key={version.id} className="border-t">
                                    <td
                                        className="px-3 py-2 font-medium"
                                        data-label="Version"
                                    >
                                        {version.version}
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Status"
                                    >
                                        <Badge
                                            variant={
                                                version.published_at
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {version.published_at
                                                ? 'published'
                                                : 'draft'}
                                        </Badge>
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Material"
                                    >
                                        {version.material ? 'yes' : 'no'}
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Clause classes"
                                    >
                                        {version.material_clauses_count ?? 0}{' '}
                                        material
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Clauses"
                                    >
                                        {version.clauses_count ?? 0}
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Actions"
                                    >
                                        <div className="flex justify-start gap-2 md:justify-end">
                                            <ActionTooltip
                                                label={`Preview version ${version.version}`}
                                            >
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={
                                                            version.urls.preview
                                                        }
                                                        aria-label={`Preview version ${version.version}`}
                                                    >
                                                        <Eye
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Link>
                                                </Button>
                                            </ActionTooltip>
                                            <ActionTooltip
                                                label={`Download version ${version.version}`}
                                            >
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <a
                                                        href={
                                                            version.urls
                                                                .download
                                                        }
                                                        aria-label={`Download version ${version.version}`}
                                                    >
                                                        <Download
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </a>
                                                </Button>
                                            </ActionTooltip>
                                            {!version.published_at && (
                                                <>
                                                    <ActionTooltip
                                                        label={`Edit version ${version.version}`}
                                                    >
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            <Link
                                                                href={
                                                                    version.urls
                                                                        .edit
                                                                }
                                                                aria-label={`Edit version ${version.version}`}
                                                            >
                                                                <Pencil
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            </Link>
                                                        </Button>
                                                    </ActionTooltip>
                                                    <ActionTooltip
                                                        label={`Publish version ${version.version}`}
                                                    >
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            <Link
                                                                href={
                                                                    version.urls
                                                                        .publish
                                                                }
                                                                aria-label={`Publish version ${version.version}`}
                                                            >
                                                                <Send
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            </Link>
                                                        </Button>
                                                    </ActionTooltip>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-NZ', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
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
