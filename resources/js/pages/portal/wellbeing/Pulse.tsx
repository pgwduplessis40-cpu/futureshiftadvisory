import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, HeartPulse, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type ClientPayload = {
    id: string;
    legal_name: string;
    trading_name: string | null;
};

type CheckinPayload = {
    id: string;
    business_confidence: number;
    personal_coping: number;
    notes: string | null;
    submitted_at: string | null;
    can_delete: boolean;
    delete_url: string;
};

type WellbeingForm = {
    business_confidence: number;
    personal_coping: number;
    notes: string;
};

type Props = {
    client: ClientPayload;
    periodStart: string;
    currentCheckin: CheckinPayload | null;
    storeUrl: string;
    dashboardUrl: string;
};

const scale = [
    { value: 1, label: '1', help: 'Very low' },
    { value: 2, label: '2', help: 'Low' },
    { value: 3, label: '3', help: 'Steady' },
    { value: 4, label: '4', help: 'Good' },
    { value: 5, label: '5', help: 'Strong' },
];

export default function WellbeingPulse({
    client,
    periodStart,
    currentCheckin,
    storeUrl,
    dashboardUrl,
}: Props) {
    const form = useForm<WellbeingForm>({
        business_confidence: currentCheckin?.business_confidence ?? 3,
        personal_coping: currentCheckin?.personal_coping ?? 3,
        notes: currentCheckin?.notes ?? '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(storeUrl, {
            preserveScroll: true,
        });
    };

    const destroy = () => {
        if (!currentCheckin?.can_delete) {
            return;
        }

        router.delete(currentCheckin.delete_url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Wellbeing check-in" />

            <main className="flex-1 space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Link
                            href={dashboardUrl}
                            className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Dashboard
                        </Link>
                        <div className="mt-3 flex items-center gap-2">
                            <HeartPulse
                                className="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <h1 className="text-xl font-semibold">
                                Wellbeing check-in
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {client.trading_name || client.legal_name}
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {formatMonth(periodStart)}
                    </Badge>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-md border bg-background p-4"
                >
                    <div className="space-y-2">
                        <h2 className="text-sm font-medium">
                            Optional monthly pulse
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Share only what feels useful. Your response helps
                            your advisor spot pressure early.
                        </p>
                    </div>

                    <ScaleField
                        label="Business confidence"
                        value={form.data.business_confidence}
                        onChange={(value) =>
                            form.setData('business_confidence', value)
                        }
                    />
                    <InputError message={form.errors.business_confidence} />

                    <ScaleField
                        label="Personal coping"
                        value={form.data.personal_coping}
                        onChange={(value) =>
                            form.setData('personal_coping', value)
                        }
                    />
                    <InputError message={form.errors.personal_coping} />

                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            rows={5}
                            maxLength={1000}
                            className="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            placeholder="Optional context for your advisor"
                        />
                        <InputError message={form.errors.notes} />
                    </div>

                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <Button asChild variant="outline">
                            <Link href={dashboardUrl}>Skip for now</Link>
                        </Button>
                        <div className="flex flex-col gap-3 sm:flex-row">
                            {currentCheckin?.can_delete && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={destroy}
                                >
                                    <Trash2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Delete
                                </Button>
                            )}
                            <Button type="submit" disabled={form.processing}>
                                Save check-in
                            </Button>
                        </div>
                    </div>
                </form>
            </main>
        </>
    );
}

function ScaleField({
    label,
    value,
    onChange,
}: {
    label: string;
    value: number;
    onChange: (value: number) => void;
}) {
    return (
        <fieldset className="space-y-3">
            <legend className="text-sm font-medium">{label}</legend>
            <div className="grid grid-cols-5 gap-2">
                {scale.map((item) => {
                    const active = value === item.value;

                    return (
                        <button
                            key={item.value}
                            type="button"
                            onClick={() => onChange(item.value)}
                            className={cn(
                                'min-h-16 rounded-md border px-2 py-2 text-center text-sm transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                active
                                    ? 'border-[var(--fs-admiralty)] bg-[var(--fs-linen)] text-[var(--fs-admiralty)]'
                                    : 'hover:bg-muted',
                            )}
                            aria-pressed={active}
                        >
                            <span className="block font-semibold">
                                {item.label}
                            </span>
                            <span className="mt-1 block text-xs text-muted-foreground">
                                {item.help}
                            </span>
                        </button>
                    );
                })}
            </div>
        </fieldset>
    );
}

function formatMonth(value: string) {
    return new Intl.DateTimeFormat(undefined, {
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}
