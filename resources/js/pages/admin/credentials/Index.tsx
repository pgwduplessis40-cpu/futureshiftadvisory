import { Head, useForm } from '@inertiajs/react';
import { Ban, CircleCheck, KeyRound, RotateCw, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
    credentials_ready: boolean;
    effective_live: boolean;
    credentials: CredentialField[];
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
                <header className="flex items-center gap-2">
                    <ShieldCheck className="size-5" aria-hidden="true" />
                    <h1 className="text-xl font-semibold">
                        Integration credentials
                    </h1>
                </header>

                {Object.entries(grouped).map(([category, rows]) => (
                    <section
                        key={category}
                        className="space-y-3 rounded-md border bg-background p-4"
                    >
                        <h2 className="text-sm font-medium capitalize">
                            {category.replaceAll('_', ' ')}
                        </h2>
                        <div className="overflow-hidden rounded-md border">
                            <table className="w-full table-fixed text-sm">
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
                    </section>
                ))}
            </div>
        </>
    );
}

function IntegrationRowView({ row }: { row: IntegrationRow }) {
    return (
        <tr className="border-t align-top">
            <td className="px-3 py-3">
                <div className="font-medium">{row.display_name}</div>
                <div className="break-words text-xs text-muted-foreground">
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
            </td>
            <td className="px-3 py-3">
                <CredentialList row={row} />
            </td>
            <td className="px-3 py-3">
                <ReadinessBadge row={row} />
            </td>
            <td className="px-3 py-3">
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
            <td className="px-3 py-3">
                <LiveControl row={row} />
            </td>
        </tr>
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
                    <div className="break-words text-xs text-muted-foreground">
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
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    disabled={
                        credential.status !== 'active' || revokeForm.processing
                    }
                    onClick={revoke}
                    aria-label={`Revoke ${credential.field}`}
                >
                    <Ban className="size-4" aria-hidden="true" />
                </Button>
            </div>
        </form>
    );
}

function CredentialStatus({
    credential,
}: {
    credential: CredentialField;
}) {
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
            <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={deactivateForm.processing}
                onClick={() =>
                    deactivateForm.patch(
                        '/admin/integration-credentials/deactivate',
                        { preserveScroll: true },
                    )
                }
            >
                <Ban className="size-4" aria-hidden="true" />
                Off
            </Button>
        );
    }

    return (
        <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={!row.credentials_ready || activateForm.processing}
            onClick={() =>
                activateForm.patch('/admin/integration-credentials/activate', {
                    preserveScroll: true,
                })
            }
        >
            <ShieldCheck className="size-4" aria-hidden="true" />
            On
        </Button>
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
