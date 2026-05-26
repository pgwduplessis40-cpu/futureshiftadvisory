import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Building2, Save, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { ConflictDeclarationModal } from '@/components/conflicts/ConflictDeclarationModal';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EngagementTypeOption, NpoOption, RegistryLookup } from './types';

type Defaults = {
    engagement_type: string;
    nzbn: string;
    legal_name: string;
    trading_name: string;
    entity_type: string;
    npo: {
        sub_type: string;
        legal_structure: string;
        isa_2022_reregistered: boolean;
    };
};

type CreateClientForm = Defaults & {
    conflict: {
        declared: boolean;
        referral_type: string;
        existing_relationship: boolean;
        details: string;
    };
};

type Props = {
    engagementTypes: EngagementTypeOption[];
    npoOptions: {
        subTypes: NpoOption[];
        legalStructures: NpoOption[];
    };
    lookup: RegistryLookup | null;
    defaults: Defaults;
};

export default function ClientsCreate({
    engagementTypes,
    npoOptions,
    lookup,
    defaults,
}: Props) {
    const form = useForm<CreateClientForm>({
        ...defaults,
        conflict: {
            declared: false,
            referral_type: 'client_creation',
            existing_relationship: false,
            details: '',
        },
    });
    const errors = form.errors as Record<string, string | undefined>;

    const lookupNzbn = () => {
        form.post('/advisor/clients/lookup-nzbn', {
            preserveScroll: true,
            preserveState: false,
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/advisor/clients');
    };
    const isNpo = form.data.engagement_type === 'npo';

    return (
        <>
            <Head title="Add client" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Add client</h1>
                    <Button asChild size="sm" variant="outline">
                        <Link href="/advisor/clients">
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <form onSubmit={submit} className="space-y-6">
                        <div className="space-y-4 rounded-md border p-4">
                            <div className="grid gap-2">
                                <Label htmlFor="engagement_type">
                                    Engagement
                                </Label>
                                <Select
                                    value={form.data.engagement_type}
                                    onValueChange={(value) =>
                                        form.setData('engagement_type', value)
                                    }
                                >
                                    <SelectTrigger id="engagement_type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {engagementTypes.map((type) => (
                                            <SelectItem
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={form.errors.engagement_type}
                                />
                            </div>

                            {isNpo && (
                                <div className="grid gap-4 rounded-md border bg-muted/20 p-3 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="npo_sub_type">
                                            NPO stream
                                        </Label>
                                        <Select
                                            value={form.data.npo.sub_type}
                                            onValueChange={(value) =>
                                                form.setData('npo', {
                                                    ...form.data.npo,
                                                    sub_type: value,
                                                })
                                            }
                                        >
                                            <SelectTrigger id="npo_sub_type">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {npoOptions.subTypes.map(
                                                    (type) => (
                                                        <SelectItem
                                                            key={type.value}
                                                            value={type.value}
                                                        >
                                                            {type.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors['npo.sub_type']}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="npo_legal_structure">
                                            Legal structure
                                        </Label>
                                        <Select
                                            value={
                                                form.data.npo.legal_structure
                                            }
                                            onValueChange={(value) =>
                                                form.setData('npo', {
                                                    ...form.data.npo,
                                                    legal_structure: value,
                                                })
                                            }
                                        >
                                            <SelectTrigger id="npo_legal_structure">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {npoOptions.legalStructures.map(
                                                    (structure) => (
                                                        <SelectItem
                                                            key={
                                                                structure.value
                                                            }
                                                            value={
                                                                structure.value
                                                            }
                                                        >
                                                            {structure.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={
                                                errors['npo.legal_structure']
                                            }
                                        />
                                    </div>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="nzbn">NZBN</Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="nzbn"
                                        value={form.data.nzbn}
                                        inputMode="numeric"
                                        pattern="[0-9]{13}"
                                        onChange={(event) =>
                                            form.setData(
                                                'nzbn',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={form.processing}
                                        onClick={lookupNzbn}
                                        aria-label="Lookup NZBN"
                                    >
                                        <Search
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </div>
                                <InputError message={form.errors.nzbn} />
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="legal_name">
                                        Legal name
                                    </Label>
                                    <Input
                                        id="legal_name"
                                        value={form.data.legal_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'legal_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.legal_name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="trading_name">
                                        Trading name
                                    </Label>
                                    <Input
                                        id="trading_name"
                                        value={form.data.trading_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'trading_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.trading_name}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="entity_type">Entity type</Label>
                                <Input
                                    id="entity_type"
                                    value={form.data.entity_type}
                                    onChange={(event) =>
                                        form.setData(
                                            'entity_type',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.entity_type} />
                            </div>
                        </div>

                        <ConflictDeclarationModal
                            value={form.data.conflict}
                            onChange={(conflict) =>
                                form.setData('conflict', conflict)
                            }
                            errors={errors}
                        />

                        <Button type="submit" disabled={form.processing}>
                            <Save className="size-4" aria-hidden="true" />
                            Save client
                        </Button>
                    </form>

                    <div className="space-y-4 rounded-md border p-4">
                        <div className="flex items-center gap-2">
                            <Building2 className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">Registry</h2>
                        </div>

                        {lookup ? (
                            <div className="space-y-4 text-sm">
                                <div>
                                    <div className="text-xs text-muted-foreground">
                                        Legal name
                                    </div>
                                    <div className="font-medium">
                                        {lookup.summary.legal_name}
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <div className="text-xs text-muted-foreground">
                                            Status
                                        </div>
                                        <div>{lookup.summary.status}</div>
                                    </div>
                                    <div>
                                        <div className="text-xs text-muted-foreground">
                                            GST
                                        </div>
                                        <div>
                                            {lookup.summary.gst_registered
                                                ? 'registered'
                                                : 'not registered'}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {Object.entries(lookup.source_badges).map(
                                        ([service, badge]) => (
                                            <Badge
                                                key={service}
                                                variant={
                                                    lookup.degraded
                                                        ? 'outline'
                                                        : 'secondary'
                                                }
                                            >
                                                {service}: {badge}
                                            </Badge>
                                        ),
                                    )}
                                </div>
                            </div>
                        ) : (
                            <div className="text-sm text-muted-foreground">
                                No lookup yet.
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

ClientsCreate.layout = {
    breadcrumbs: [
        {
            title: 'Clients',
            href: '/advisor/clients',
        },
        {
            title: 'Add client',
            href: '/advisor/clients/create',
        },
    ],
};
