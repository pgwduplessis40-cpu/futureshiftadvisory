import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type Props = {
    offer: {
        client_label: string;
        fixed_fee: number;
        currency: string;
        scope_description: string;
        package_scope: string;
        client_outcomes: string[];
        pilot_fee_waiver?: { active: boolean; nominal_fixed_fee: number };
    };
    offerVersion: string;
};

export default function EntrepreneurServiceOffer({
    offer,
    offerVersion,
}: Props) {
    const form = useForm({ accepted: false, offer_version: offerVersion });
    const money = (amount: number) =>
        new Intl.NumberFormat('en-NZ', {
            style: 'currency',
            currency: offer.currency,
        }).format(amount);

    return (
        <>
            <Head title="Confirm your service" />
            <form
                className="mx-auto max-w-2xl space-y-6 p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/portal/entrepreneur/service-offer');
                }}
            >
                <div className="space-y-2">
                    <h1 className="text-xl font-semibold">
                        Confirm your service
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Your advisor selected the service below. Review its
                        scope and fee to complete your onboarding.
                    </p>
                </div>
                <section className="space-y-3 rounded-md border p-4">
                    <h2 className="font-semibold">{offer.client_label}</h2>
                    <p className="text-sm">{offer.scope_description}</p>
                    <p className="font-medium">
                        Service fee: {money(offer.fixed_fee)} ex GST
                    </p>
                    {offer.pilot_fee_waiver?.active && (
                        <p className="text-sm text-muted-foreground">
                            Your pilot fee waiver applies to this service. The
                            normal fee of{' '}
                            {money(offer.pilot_fee_waiver.nominal_fixed_fee)} ex
                            GST is waived.
                        </p>
                    )}
                    {offer.client_outcomes.length > 0 && (
                        <ul className="list-disc space-y-1 pl-5 text-sm">
                            {offer.client_outcomes.map((outcome) => (
                                <li key={outcome}>{outcome}</li>
                            ))}
                        </ul>
                    )}
                    {offer.package_scope === 'combo' && (
                        <p className="text-sm text-muted-foreground">
                            Your package includes idea validation and the
                            business plan and budget. Your advisor reviews idea
                            validation before the plan stage opens.
                        </p>
                    )}
                    <p className="text-sm text-muted-foreground">
                        Other services are available by request. Agreeing to
                        this offer records your acceptance; it does not take a
                        payment.
                    </p>
                </section>
                <div className="flex items-start gap-3">
                    <Checkbox
                        id="accept-service"
                        checked={form.data.accepted}
                        onCheckedChange={(checked) =>
                            form.setData('accepted', checked === true)
                        }
                    />
                    <Label htmlFor="accept-service" className="leading-relaxed">
                        I agree to the selected service scope and the fee shown
                        above.
                    </Label>
                </div>
                <InputError
                    message={form.errors.accepted ?? form.errors.offer_version}
                />
                <Button
                    type="submit"
                    disabled={!form.data.accepted || form.processing}
                >
                    Agree and continue
                </Button>
            </form>
        </>
    );
}

EntrepreneurServiceOffer.layout = {
    breadcrumbs: [
        {
            title: 'Confirm your service',
            href: '/portal/entrepreneur/service-offer',
        },
    ],
};
