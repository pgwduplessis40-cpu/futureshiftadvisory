import { ShieldAlert } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ConflictDeclarationValue = {
    declared: boolean;
    referral_type: string;
    existing_relationship: boolean;
    details: string;
};

type Props = {
    value: ConflictDeclarationValue;
    onChange: (value: ConflictDeclarationValue) => void;
    errors?: Record<string, string | undefined>;
};

const labels: Record<string, string> = {
    client_creation: 'Client creation',
    due_diligence: 'Due diligence',
    broker_referral: 'Broker referral',
    coach_referral: 'Coach referral',
};

export function ConflictDeclarationModal({
    value,
    onChange,
    errors = {},
}: Props) {
    const update = (patch: Partial<ConflictDeclarationValue>) =>
        onChange({ ...value, ...patch });

    return (
        <section className="space-y-4 rounded-md border p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-start gap-3">
                    <ShieldAlert
                        className="mt-0.5 size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <div>
                        <h2 className="text-sm font-medium">
                            Conflict declaration
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {value.declared
                                ? 'Declaration captured for this action.'
                                : 'Declaration required before saving.'}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant={value.declared ? 'secondary' : 'outline'}>
                        {value.declared ? 'Declared' : 'Required'}
                    </Badge>
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button type="button" variant="outline" size="sm">
                                Review
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    Conflict of interest declaration
                                </DialogTitle>
                                <DialogDescription>
                                    Confirm this before continuing with{' '}
                                    {labels[value.referral_type] ??
                                        value.referral_type}
                                    .
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-4">
                                <div className="rounded-md border bg-muted/40 p-3 text-sm">
                                    <div className="text-xs text-muted-foreground">
                                        Declaration type
                                    </div>
                                    <div className="mt-1 font-medium">
                                        {labels[value.referral_type] ??
                                            value.referral_type}
                                    </div>
                                </div>

                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="conflict_declared"
                                        checked={value.declared}
                                        onCheckedChange={(checked) =>
                                            update({
                                                declared: checked === true,
                                            })
                                        }
                                    />
                                    <div className="grid gap-1">
                                        <Label htmlFor="conflict_declared">
                                            I have reviewed and recorded any
                                            actual, potential, or perceived
                                            conflict.
                                        </Label>
                                        <InputError
                                            message={
                                                errors['conflict.declared']
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="existing_relationship"
                                        checked={value.existing_relationship}
                                        onCheckedChange={(checked) =>
                                            update({
                                                existing_relationship:
                                                    checked === true,
                                            })
                                        }
                                    />
                                    <Label htmlFor="existing_relationship">
                                        Existing relationship or referral
                                        interest
                                    </Label>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="conflict_details">
                                        Declaration notes
                                    </Label>
                                    <Input
                                        id="conflict_details"
                                        value={value.details}
                                        onChange={(event) =>
                                            update({
                                                details: event.target.value,
                                            })
                                        }
                                    />
                                    <InputError
                                        message={errors['conflict.details']}
                                    />
                                </div>
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button">Done</Button>
                                </DialogClose>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
            <InputError message={errors['conflict.referral_type']} />
        </section>
    );
}
