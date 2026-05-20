import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Send } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TermsVersion } from './types';

type Props = {
    version: TermsVersion;
};

export default function TermsPublish({ version }: Props) {
    const form = useForm({
        material: version.material,
        notice_period_days: version.notice_period_days,
        reviewer_reference: version.reviewer_reference ?? '',
    });

    return (
        <>
            <Head title={`Publish terms ${version.version}`} />

            <form
                className="max-w-xl space-y-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(`/admin/terms/${version.id}/publish`);
                }}
            >
                <div className="flex items-center justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold">
                            Publish {version.version}
                        </h1>
                        <Badge variant="secondary">
                            {version.clauses.length} clauses
                        </Badge>
                    </div>
                    <Button asChild size="sm" variant="outline">
                        <Link href={`/admin/terms/${version.id}/preview`}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Preview
                        </Link>
                    </Button>
                </div>

                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={form.data.material}
                        onCheckedChange={(checked) =>
                            form.setData('material', checked === true)
                        }
                    />
                    Material change
                </label>
                <InputError message={form.errors.material} />

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

                <div className="grid gap-2">
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

                <Button type="submit" disabled={form.processing}>
                    <Send className="size-4" aria-hidden="true" />
                    Publish
                </Button>
            </form>
        </>
    );
}
