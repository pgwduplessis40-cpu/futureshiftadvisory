import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    FileCheck2,
    RotateCcw,
    Send,
    ShieldCheck,
} from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type Client = {
    id: string;
    legal_name: string;
    nzbn: string | null;
    primary_contact_name: string | null;
    primary_contact_email: string | null;
};

type LatestOffboarding = {
    id: string;
    triggered_at: string | null;
    reengagement_due: string | null;
    advisor_capacity_released: boolean;
} | null;

type OffboardingForm = {
    exit_interview_notes: string;
    handover_notes: string;
    privacy_acknowledged: boolean;
};

type Props = {
    client: Client;
    latestOffboarding: LatestOffboarding;
    reengagementDays: number;
    submitUrl: string;
    backUrl: string;
};

export default function ClientsOffboard({
    client,
    latestOffboarding,
    reengagementDays,
    submitUrl,
    backUrl,
}: Props) {
    const form = useForm<OffboardingForm>({
        exit_interview_notes: '',
        handover_notes: '',
        privacy_acknowledged: false,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(submitUrl);
    };

    return (
        <>
            <Head title={`Offboard ${client.legal_name}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Offboard {client.legal_name}
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            Structured engagement completion
                        </div>
                    </div>
                    <Button asChild size="sm" variant="outline">
                        <Link href={backUrl}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <form onSubmit={submit} className="space-y-6">
                        <section className="space-y-4 rounded-md border p-4">
                            <div className="flex items-center gap-2">
                                <FileCheck2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">
                                    Completion record
                                </h2>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="exit_interview_notes">
                                    Exit interview notes
                                </Label>
                                <textarea
                                    id="exit_interview_notes"
                                    value={form.data.exit_interview_notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'exit_interview_notes',
                                            event.target.value,
                                        )
                                    }
                                    rows={6}
                                    className="min-h-36 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError
                                    message={form.errors.exit_interview_notes}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="handover_notes">
                                    Handover notes
                                </Label>
                                <textarea
                                    id="handover_notes"
                                    value={form.data.handover_notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'handover_notes',
                                            event.target.value,
                                        )
                                    }
                                    rows={6}
                                    className="min-h-36 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError
                                    message={form.errors.handover_notes}
                                />
                            </div>
                        </section>

                        <section className="space-y-4 rounded-md border p-4">
                            <div className="flex items-center gap-2">
                                <ShieldCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">
                                    Privacy notice
                                </h2>
                            </div>

                            <label className="flex items-start gap-3 text-sm">
                                <Checkbox
                                    checked={form.data.privacy_acknowledged}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'privacy_acknowledged',
                                            checked === true,
                                        )
                                    }
                                    aria-label="Confirm privacy notice"
                                />
                                <span>
                                    Confirm the client-facing privacy notice is
                                    included in the offboarding pack.
                                </span>
                            </label>
                            <InputError
                                message={form.errors.privacy_acknowledged}
                            />
                        </section>

                        <Button type="submit" disabled={form.processing}>
                            <Send className="size-4" aria-hidden="true" />
                            Complete offboarding
                        </Button>
                    </form>

                    <aside className="space-y-4 rounded-md border p-4">
                        <div>
                            <div className="text-xs text-muted-foreground">
                                Client
                            </div>
                            <div className="mt-1 text-sm font-medium">
                                {client.legal_name}
                            </div>
                        </div>
                        <dl className="grid gap-3 text-sm">
                            <Detail label="NZBN" value={client.nzbn} />
                            <Detail
                                label="Contact"
                                value={
                                    client.primary_contact_name ??
                                    client.primary_contact_email
                                }
                            />
                            <Detail
                                label="Reminder"
                                value={`${reengagementDays} days`}
                            />
                        </dl>

                        <div className="rounded-md bg-muted p-3 text-sm text-muted-foreground">
                            Final progress report, engagement summary, handover
                            document, exit interview record, and privacy notice
                            PDFs are generated together.
                        </div>

                        {latestOffboarding && (
                            <div className="space-y-2 border-t pt-4 text-sm">
                                <div className="flex items-center gap-2 font-medium">
                                    <RotateCcw
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Latest offboarding
                                </div>
                                <Detail
                                    label="Triggered"
                                    value={formatDate(
                                        latestOffboarding.triggered_at,
                                    )}
                                />
                                <Detail
                                    label="Re-engage"
                                    value={formatDate(
                                        latestOffboarding.reengagement_due,
                                    )}
                                />
                            </div>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="grid grid-cols-[96px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

function formatDate(value: string | null) {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(value));
}

ClientsOffboard.layout = {
    breadcrumbs: [
        {
            title: 'Clients',
            href: '/advisor/clients',
        },
        {
            title: 'Offboarding',
            href: '#',
        },
    ],
};
