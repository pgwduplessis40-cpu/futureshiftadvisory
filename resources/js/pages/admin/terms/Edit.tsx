import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, FileText, Save } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TermsClause, TermsVersion } from './types';

type Props = {
    version: TermsVersion;
};

type TermsForm = {
    version: string;
    title: string;
    material: boolean;
    notice_period_days: number;
    reviewer_reference: string;
    clauses: TermsClause[];
};

export default function TermsEdit({ version }: Props) {
    const form = useForm<TermsForm>({
        version: version.version,
        title: version.title,
        material: version.material,
        notice_period_days: version.notice_period_days,
        reviewer_reference: version.reviewer_reference ?? '',
        clauses: version.clauses,
    });
    const materialClauses = form.data.clauses.filter(
        (clause) => clause.material,
    ).length;

    const updateClause = <K extends keyof TermsClause>(
        index: number,
        key: K,
        value: TermsClause[K],
    ) => {
        const clauses = [...form.data.clauses];
        clauses[index] = { ...clauses[index], [key]: value };
        form.setData('clauses', clauses);
    };

    return (
        <>
            <Head title={`Edit terms ${version.version}`} />

            <form
                className="space-y-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/admin/terms/${version.id}`);
                }}
            >
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">
                        Terms {version.version}
                    </h1>
                    <div className="flex gap-2">
                        <Button asChild size="sm" variant="outline">
                            <Link href={`/admin/terms/${version.id}/preview`}>
                                <Eye className="size-4" aria-hidden="true" />
                                Preview
                            </Link>
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                        >
                            <Save className="size-4" aria-hidden="true" />
                            Save
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-3 rounded-md border p-4 md:col-span-2">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2 text-sm font-medium">
                                <FileText
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Classification
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Badge
                                    variant={
                                        form.data.material
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {form.data.material
                                        ? 'material document'
                                        : 'non-material document'}
                                </Badge>
                                <Badge variant="outline">
                                    {materialClauses} material clauses
                                </Badge>
                            </div>
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.material}
                                onCheckedChange={(checked) =>
                                    form.setData('material', checked === true)
                                }
                            />
                            Material version
                        </label>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="version">Version</Label>
                        <Input
                            id="version"
                            value={form.data.version}
                            onChange={(event) =>
                                form.setData('version', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.version} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="notice_period_days">Notice days</Label>
                        <Input
                            id="notice_period_days"
                            type="number"
                            min={0}
                            max={365}
                            value={form.data.notice_period_days}
                            onChange={(event) =>
                                form.setData(
                                    'notice_period_days',
                                    Number(event.target.value),
                                )
                            }
                            required
                        />
                        <InputError message={form.errors.notice_period_days} />
                    </div>

                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="title">Title</Label>
                        <Input
                            id="title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="reviewer_reference">
                            Reviewer reference
                        </Label>
                        <Input
                            id="reviewer_reference"
                            value={form.data.reviewer_reference}
                            onChange={(event) =>
                                form.setData(
                                    'reviewer_reference',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.reviewer_reference} />
                    </div>
                </div>

                <div className="space-y-4">
                    {form.data.clauses.map((clause, index) => (
                        <div
                            key={clause.id}
                            className="space-y-3 rounded-md border p-4"
                        >
                            <div className="grid gap-3 md:grid-cols-[6rem_1fr_auto]">
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`clause-${clause.id}-number`}
                                    >
                                        Number
                                    </Label>
                                    <Input
                                        id={`clause-${clause.id}-number`}
                                        type="number"
                                        min={1}
                                        max={99}
                                        value={clause.clause_number}
                                        onChange={(event) =>
                                            updateClause(
                                                index,
                                                'clause_number',
                                                Number(event.target.value),
                                            )
                                        }
                                        required
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`clause-${clause.id}-title`}
                                    >
                                        Title
                                    </Label>
                                    <Input
                                        id={`clause-${clause.id}-title`}
                                        value={clause.title}
                                        onChange={(event) =>
                                            updateClause(
                                                index,
                                                'title',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                </div>

                                <label className="flex items-end gap-2 pb-2 text-sm">
                                    <Checkbox
                                        checked={clause.material}
                                        onCheckedChange={(checked) =>
                                            updateClause(
                                                index,
                                                'material',
                                                checked === true,
                                            )
                                        }
                                    />
                                    Material
                                </label>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`clause-${clause.id}-body`}>
                                    Body
                                </Label>
                                <textarea
                                    id={`clause-${clause.id}-body`}
                                    className="min-h-36 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    value={clause.body}
                                    onChange={(event) =>
                                        updateClause(
                                            index,
                                            'body',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>
                        </div>
                    ))}
                </div>
            </form>
        </>
    );
}
