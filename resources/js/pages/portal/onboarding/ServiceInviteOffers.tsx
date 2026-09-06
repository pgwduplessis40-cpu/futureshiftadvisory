import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type ServiceOffer = {
    id: string;
    label: string;
    scope_label: string;
    description: string;
    fixed_fee: number | null;
    currency: string;
    included_stages: string[];
    acknowledged_at: string | null;
    activation_url: string;
};

export type ServiceOffers = {
    must_acknowledge: boolean;
    items: ServiceOffer[];
};

export function ServiceInviteOffers({
    serviceOffers,
    checked,
    error,
    onCheckedChange,
}: {
    serviceOffers: ServiceOffers;
    checked: boolean;
    error?: string;
    onCheckedChange: (checked: boolean) => void;
}) {
    const selectedOfferTotal = serviceOffers.items.every(
        (offer) => offer.fixed_fee !== null,
    )
        ? serviceOffers.items.reduce(
              (total, offer) => total + (offer.fixed_fee ?? 0),
              0,
          )
        : null;
    const selectedOfferCurrency = serviceOffers.items[0]?.currency ?? 'NZD';

    return serviceOffers.items.length > 0 ? (
        <div className="space-y-3 rounded-md border border-[var(--fs-linen)] bg-muted/30 p-4">
            <div>
                <h3 className="text-sm font-medium">
                    Your selected service and price
                </h3>
                <p className="mt-1 text-sm text-muted-foreground">
                    Your advisor selected these services for this invitation. No
                    other service has been added; other services remain by
                    request.
                </p>
            </div>
            {selectedOfferTotal !== null ? (
                <div className="rounded-md border bg-background px-3 py-2 text-sm">
                    Combined selected fee:{' '}
                    <span className="font-medium">
                        {formatMoney(selectedOfferTotal, selectedOfferCurrency)}{' '}
                        ex GST
                    </span>
                </div>
            ) : null}
            {serviceOffers.items.map((offer) => (
                <div
                    key={offer.id}
                    className="rounded-md border bg-background p-3"
                >
                    <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div className="font-medium">{offer.label}</div>
                            <div className="text-xs text-muted-foreground">
                                {offer.scope_label}
                            </div>
                        </div>
                        <span className="text-sm font-medium">
                            {formatMoney(offer.fixed_fee, offer.currency)} ex
                            GST
                        </span>
                    </div>
                    {offer.description ? (
                        <p className="mt-2 text-sm text-muted-foreground">
                            {offer.description}
                        </p>
                    ) : null}
                    {offer.included_stages.length > 0 ? (
                        <ul className="mt-2 list-disc space-y-1 pl-5 text-xs text-muted-foreground">
                            {offer.included_stages.map((stage) => (
                                <li key={stage}>{stage}</li>
                            ))}
                        </ul>
                    ) : null}
                </div>
            ))}
            {serviceOffers.must_acknowledge ? (
                <CheckboxField
                    id="service_offers_acknowledged"
                    label="I agree to the selected service scope and fee shown above. I understand that payment and workspace access follow the payment steps for each service."
                    checked={checked}
                    onCheckedChange={(checked) => onCheckedChange(checked)}
                    error={error}
                />
            ) : (
                <p className="text-xs text-muted-foreground">
                    You have already acknowledged the selected service scope and
                    fee.
                </p>
            )}
        </div>
    ) : null;
}

export function CheckboxField({
    id,
    label,
    checked,
    error,
    onCheckedChange,
}: {
    id: string;
    label: string;
    checked: boolean;
    error?: string;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <div className="space-y-2">
            <div className="flex items-start gap-3">
                <Checkbox
                    id={id}
                    checked={checked}
                    onCheckedChange={(value) => onCheckedChange(value === true)}
                />
                <Label htmlFor={id}>{label}</Label>
            </div>
            <InputError message={error} />
        </div>
    );
}

function formatMoney(amount: number | null, currency: string): string {
    if (amount === null) {
        return 'Fee to be confirmed';
    }

    return new Intl.NumberFormat('en-NZ', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(amount);
}
