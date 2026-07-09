import { Head, Link, useForm } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import {
    ArrowLeft,
    BriefcaseBusiness,
    Lightbulb,
    Send,
} from 'lucide-react';
import type { FormEvent } from 'react';
import {
    ExplainedSectionHeader,
    Explainer,
} from '@/components/explainer';
import type { Explanation } from '@/components/explainer';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type ServiceType = 'due_diligence' | 'entrepreneur';

type ServiceOption = {
    service_type: ServiceType;
    label: string;
    description: string;
    available: boolean;
    start_url: string;
};

type PaymentSplit = {
    deposit_percent: number;
    card_deposit_amount: number | null;
    bank_transfer_amount: number | null;
    requires_bank_transfer: boolean;
};

type ServicePackage = {
    id?: string;
    client_label?: string;
    package_name?: string;
    package_scope?: string | null;
    billing_model?: string;
    fixed_fee?: number | null;
    deposit_percent?: number | null;
    currency?: string;
    scope_description?: string | null;
    included_stages?: string[];
    client_outcomes?: string[];
    payment_split?: PaymentSplit;
    purchase_price_min?: number | null;
    purchase_price_max?: number | null;
    free_access_mode?: {
        active?: boolean;
        reason?: string;
        nominal_fixed_fee?: number | null;
        stripe_required?: boolean;
    };
};

type PricingPreview = {
    status: string;
    matched: boolean;
    message: string;
    payment_required: boolean;
    free_access_mode: boolean;
    source: string;
    package: ServicePackage | null;
    packages?: ServicePackage[];
};

type Props = {
    service: ServiceOption;
    pricingPreview: PricingPreview;
    requestUrl: string;
    dashboardUrl: string;
};

export default function ServiceActivationRequest({
    service,
    pricingPreview,
    requestUrl,
    dashboardUrl,
}: Props) {
    const isDueDiligence = service.service_type === 'due_diligence';
    const pricingPackages = pricingPreview.packages ?? [];
    const form = useForm<ServiceActivationForm>({
        service_type: service.service_type,
        target_name: '',
        vendor_name: '',
        industry: '',
        asking_price: '',
        idea_name: '',
        customer: '',
        problem: '',
        timing: '',
        notes: '',
        pricing_acknowledged: false,
        pricing_package_id: '',
    });
    const Icon = isDueDiligence ? BriefcaseBusiness : Lightbulb;
    const matchedPackage = isDueDiligence
        ? selectPricingPackage(form.data.asking_price, pricingPackages)
        : null;

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(requestUrl);
    }

    function updateAskingPrice(value: string) {
        const normalized = normalizeMoneyInput(value);

        form.setData('asking_price', normalized);
        form.setData('pricing_acknowledged', false);
        form.setData(
            'pricing_package_id',
            selectPricingPackage(normalized, pricingPackages)?.id ?? '',
        );
    }

    function updatePricingAcknowledgement(checked: boolean) {
        form.setData('pricing_acknowledged', checked);
        form.setData('pricing_package_id', matchedPackage?.id ?? '');
    }

    return (
        <>
            <Head title={service.label} />

            <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={dashboardUrl}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Dashboard
                            </Link>
                        </Button>
                        <div className="mt-4 flex items-start gap-3">
                            <span className="rounded-md border bg-background p-2">
                                <Icon
                                    className="size-5 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </span>
                            <div>
                                <h1 className="text-xl font-semibold">
                                    {service.label}
                                </h1>
                                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                                    {service.description}
                                </p>
                            </div>
                        </div>
                    </div>
                    <Badge variant="secondary">
                        {service.available ? 'Available' : 'Already open'}
                    </Badge>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-5 rounded-md border bg-background p-4"
                >
                    <ExplainedSectionHeader
                        title="Workspace request"
                        description="These details help your advisor confirm the right service path before selecting scope and package."
                        explanation={{
                            title: 'Workspace request',
                            what: 'This form captures the initial business, acquisition, or idea context for the requested service.',
                            action: 'Complete the fields you know now. Your advisor can clarify or refine the scope after the request is submitted.',
                            why: 'Early context helps FSA choose the correct package and avoid opening the wrong workflow for the client.',
                        }}
                    />
                    <div className="grid gap-4">
                        {isDueDiligence ? (
                            <DueDiligenceFields
                                form={form}
                                onAskingPriceChange={updateAskingPrice}
                            />
                        ) : (
                            <EntrepreneurFields form={form} />
                        )}

                        <div className="grid gap-2">
                            <LabelWithExplanation
                                htmlFor="service_timing"
                                explanation={serviceExplanations.timing}
                            >
                                Timing
                            </LabelWithExplanation>
                            <Input
                                id="service_timing"
                                value={form.data.timing}
                                onChange={(event) =>
                                    form.setData(
                                        'timing',
                                        event.target.value,
                                    )
                                }
                                placeholder="Now, next month, after funding, still exploring"
                            />
                            <InputError message={form.errors.timing} />
                        </div>

                        <div className="grid gap-2">
                            <LabelWithExplanation
                                htmlFor="service_notes"
                                explanation={serviceExplanations.notes}
                            >
                                Notes
                            </LabelWithExplanation>
                            <textarea
                                id="service_notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                rows={5}
                                className={cn(
                                    'min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50',
                                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                )}
                            />
                            <InputError message={form.errors.notes} />
                        </div>
                    </div>

                    <PricingTransparencyPanel
                        isDueDiligence={isDueDiligence}
                        pricingPreview={pricingPreview}
                        matchedPackage={matchedPackage}
                        askingPrice={form.data.asking_price}
                        acknowledged={form.data.pricing_acknowledged}
                        error={form.errors.pricing_acknowledged}
                        onAcknowledgedChange={updatePricingAcknowledgement}
                    />

                    <div className="mt-5 flex justify-end">
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !form.data.pricing_acknowledged
                            }
                        >
                            <Send className="size-4" aria-hidden="true" />
                            Request workspace
                        </Button>
                    </div>
                </form>
            </main>
        </>
    );
}

function DueDiligenceFields({
    form,
    onAskingPriceChange,
}: {
    form: InertiaFormProps<ServiceActivationForm>;
    onAskingPriceChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-4">
            <div className="grid gap-2 lg:col-span-2">
                <LabelWithExplanation
                    htmlFor="service_target_name"
                    explanation={serviceExplanations.targetName}
                >
                    Target business
                </LabelWithExplanation>
                <Input
                    id="service_target_name"
                    value={form.data.target_name}
                    onChange={(event) =>
                        form.setData('target_name', event.target.value)
                    }
                />
                <InputError message={form.errors.target_name} />
            </div>
            <div className="grid gap-2">
                <LabelWithExplanation
                    htmlFor="service_vendor_name"
                    explanation={serviceExplanations.vendorName}
                >
                    Vendor
                </LabelWithExplanation>
                <Input
                    id="service_vendor_name"
                    value={form.data.vendor_name}
                    onChange={(event) =>
                        form.setData('vendor_name', event.target.value)
                    }
                />
                <InputError message={form.errors.vendor_name} />
            </div>
            <div className="grid gap-2">
                <LabelWithExplanation
                    htmlFor="service_asking_price"
                    explanation={serviceExplanations.askingPrice}
                >
                    Asking price
                </LabelWithExplanation>
                <Input
                    id="service_asking_price"
                    inputMode="decimal"
                    value={form.data.asking_price}
                    onChange={(event) =>
                        onAskingPriceChange(event.target.value)
                    }
                />
                <InputError message={form.errors.asking_price} />
            </div>
            <div className="grid gap-2 lg:col-span-4">
                <LabelWithExplanation
                    htmlFor="service_industry"
                    explanation={serviceExplanations.industry}
                >
                    Industry
                </LabelWithExplanation>
                <Input
                    id="service_industry"
                    value={form.data.industry}
                    onChange={(event) =>
                        form.setData('industry', event.target.value)
                    }
                />
                <InputError message={form.errors.industry} />
            </div>
        </div>
    );
}

function EntrepreneurFields({
    form,
}: {
    form: InertiaFormProps<ServiceActivationForm>;
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <div className="grid gap-2 lg:col-span-3">
                <LabelWithExplanation
                    htmlFor="service_idea_name"
                    explanation={serviceExplanations.ideaName}
                >
                    Idea or concept
                </LabelWithExplanation>
                <Input
                    id="service_idea_name"
                    value={form.data.idea_name}
                    onChange={(event) =>
                        form.setData('idea_name', event.target.value)
                    }
                />
                <InputError message={form.errors.idea_name} />
            </div>
            <div className="grid gap-2">
                <LabelWithExplanation
                    htmlFor="service_idea_industry"
                    explanation={serviceExplanations.industry}
                >
                    Industry
                </LabelWithExplanation>
                <Input
                    id="service_idea_industry"
                    value={form.data.industry}
                    onChange={(event) =>
                        form.setData('industry', event.target.value)
                    }
                />
                <InputError message={form.errors.industry} />
            </div>
            <div className="grid gap-2 lg:col-span-2">
                <LabelWithExplanation
                    htmlFor="service_customer"
                    explanation={serviceExplanations.customer}
                >
                    Customer
                </LabelWithExplanation>
                <Input
                    id="service_customer"
                    value={form.data.customer}
                    onChange={(event) =>
                        form.setData('customer', event.target.value)
                    }
                />
                <InputError message={form.errors.customer} />
            </div>
            <div className="grid gap-2 lg:col-span-3">
                <LabelWithExplanation
                    htmlFor="service_problem"
                    explanation={serviceExplanations.problem}
                >
                    Problem to solve
                </LabelWithExplanation>
                <textarea
                    id="service_problem"
                    value={form.data.problem}
                    onChange={(event) =>
                        form.setData('problem', event.target.value)
                    }
                    rows={4}
                    className={cn(
                        'min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50',
                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                    )}
                />
                <InputError message={form.errors.problem} />
            </div>
        </div>
    );
}

type ServiceActivationForm = {
    service_type: ServiceType;
    target_name: string;
    vendor_name: string;
    industry: string;
    asking_price: string;
    idea_name: string;
    customer: string;
    problem: string;
    timing: string;
    notes: string;
    pricing_acknowledged: boolean;
    pricing_package_id: string;
};

function PricingTransparencyPanel({
    isDueDiligence,
    pricingPreview,
    matchedPackage,
    askingPrice,
    acknowledged,
    error,
    onAcknowledgedChange,
}: {
    isDueDiligence: boolean;
    pricingPreview: PricingPreview;
    matchedPackage: ServicePackage | null;
    askingPrice: string;
    acknowledged: boolean;
    error?: string;
    onAcknowledgedChange: (checked: boolean) => void;
}) {
    const packageToShow = isDueDiligence ? matchedPackage : null;
    const packagePayment = packageToShow
        ? packagePaymentSplit(packageToShow)
        : null;
    const currency = packageToShow?.currency ?? 'NZD';
    const hasAskingPrice = parseMoneyInput(askingPrice) !== null;
    const activePackages = pricingPreview.packages ?? [];

    return (
        <section className="border-t pt-5">
            <ExplainedSectionHeader
                title="Package, scope, and fee before request"
                description="Review the visible pricing position before the request is submitted."
                explanation={serviceExplanations.pricing}
            />

            {packageToShow ? (
                <div className="mt-3 space-y-3 rounded-md border border-emerald-200 bg-emerald-50/70 p-3 text-sm">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div className="font-medium">
                                {packageToShow.client_label ??
                                    packageToShow.package_name ??
                                    'Matched package'}
                            </div>
                            <p className="mt-1 text-muted-foreground">
                                {packageToShow.scope_description ??
                                    'Matched from the active Admin Service Rates table.'}
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {packageToShow.free_access_mode?.active
                                ? 'No payment now'
                                : 'Matched fee'}
                        </Badge>
                    </div>

                    <dl className="grid gap-3 sm:grid-cols-3">
                        <PricingFact
                            label="Fee ex GST"
                            value={
                                packageToShow.fixed_fee !== null &&
                                packageToShow.fixed_fee !== undefined
                                    ? formatMoney(
                                          packageToShow.fixed_fee,
                                          currency,
                                      )
                                    : 'Pricing to confirm'
                            }
                        />
                        <PricingFact
                            label="Source"
                            value="Admin Service Rates"
                        />
                        <PricingFact
                            label="Payment before workspace"
                            value={
                                packageToShow.fixed_fee &&
                                packageToShow.fixed_fee > 0
                                    ? 'Required'
                                    : 'Not required'
                            }
                        />
                        {packagePayment?.card_deposit_amount !== null &&
                        packagePayment?.card_deposit_amount !== undefined ? (
                            <PricingFact
                                label="Card deposit"
                                value={formatMoney(
                                    packagePayment.card_deposit_amount,
                                    currency,
                                )}
                            />
                        ) : null}
                        {packagePayment?.bank_transfer_amount !== null &&
                        packagePayment?.bank_transfer_amount !== undefined &&
                        packagePayment.bank_transfer_amount > 0 ? (
                            <PricingFact
                                label="Bank transfer balance"
                                value={formatMoney(
                                    packagePayment.bank_transfer_amount,
                                    currency,
                                )}
                            />
                        ) : null}
                    </dl>

                    {packageToShow.free_access_mode?.active ? (
                        <p className="rounded-md border border-emerald-200 bg-background p-2 text-sm">
                            Fees are currently inactive for this service. The
                            nominal package fee is{' '}
                            {packageToShow.free_access_mode.nominal_fixed_fee !==
                                null &&
                            packageToShow.free_access_mode.nominal_fixed_fee !==
                                undefined
                                ? formatMoney(
                                      packageToShow.free_access_mode
                                          .nominal_fixed_fee,
                                      currency,
                                  )
                                : 'not charged'}{' '}
                            and Stripe/payment will not be requested before the
                            workspace moves forward.
                        </p>
                    ) : null}
                </div>
            ) : (
                <div className="mt-3 space-y-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                    <p className="font-medium">
                        {isDueDiligence && !hasAskingPrice
                            ? 'Enter the asking price to show the exact matched package fee.'
                            : 'Pricing must be confirmed before any charge or workspace access.'}
                    </p>
                    <p>
                        {isDueDiligence && !hasAskingPrice
                            ? pricingPreview.message
                            : 'No payment will be requested and no workspace will open until the advisor confirms the package, scope, and GST-exclusive fee.'}
                    </p>
                    {!isDueDiligence && activePackages.length > 0 ? (
                        <div className="mt-2 space-y-1">
                            <p className="font-medium">
                                Active packages currently configured:
                            </p>
                            <ul className="space-y-1">
                                {activePackages.map((servicePackage) => (
                                    <li key={servicePackage.id}>
                                        {servicePackage.client_label ??
                                            servicePackage.package_name}{' '}
                                        -{' '}
                                        {servicePackage.fixed_fee !== null &&
                                        servicePackage.fixed_fee !== undefined
                                            ? formatMoney(
                                                  servicePackage.fixed_fee,
                                                  servicePackage.currency ??
                                                      'NZD',
                                              )
                                            : 'pricing to confirm'}{' '}
                                        ex GST
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                </div>
            )}

            <label className="mt-4 flex gap-3 text-sm">
                <input
                    type="checkbox"
                    checked={acknowledged}
                    onChange={(event) =>
                        onAcknowledgedChange(event.target.checked)
                    }
                />
                <span>
                    {packageToShow
                        ? `I understand this request is based on the visible package, scope, and ${formatMoney(Number(packageToShow.fixed_fee ?? 0), currency)} GST-exclusive fee shown above.`
                        : 'I understand pricing will be confirmed before any charge is requested or any workspace access opens.'}
                </span>
            </label>
            <InputError message={error} className="mt-2" />
        </section>
    );
}

function PricingFact({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-medium">{value}</dd>
        </div>
    );
}

function selectPricingPackage(
    askingPrice: string,
    packages: ServicePackage[],
) {
    const price = parseMoneyInput(askingPrice);

    if (price === null) {
        return null;
    }

    return (
        packages.find((servicePackage) => {
            const minimum =
                servicePackage.purchase_price_min !== null &&
                servicePackage.purchase_price_min !== undefined
                    ? Number(servicePackage.purchase_price_min)
                    : null;
            const maximum =
                servicePackage.purchase_price_max !== null &&
                servicePackage.purchase_price_max !== undefined
                    ? Number(servicePackage.purchase_price_max)
                    : null;

            if (minimum !== null && price < minimum) {
                return false;
            }

            if (maximum !== null && price > maximum) {
                return false;
            }

            return true;
        }) ?? null
    );
}

function parseMoneyInput(value: string) {
    const normalized = normalizeMoneyInput(value);

    if (normalized === '') {
        return null;
    }

    const parsed = Number(normalized);

    return Number.isFinite(parsed) && parsed >= 0 ? parsed : null;
}

function normalizeMoneyInput(value: string) {
    return value.replace(/[$,\s]/g, '');
}

function packagePaymentSplit(servicePackage: ServicePackage) {
    if (servicePackage.payment_split) {
        return servicePackage.payment_split;
    }

    if (
        servicePackage.billing_model !== 'fixed_fee' ||
        servicePackage.fixed_fee === null ||
        servicePackage.fixed_fee === undefined
    ) {
        return null;
    }

    const depositPercent = Math.min(
        Math.max(Number(servicePackage.deposit_percent ?? 100), 0),
        100,
    );
    const cardDeposit = roundCurrency(
        servicePackage.fixed_fee * (depositPercent / 100),
    );
    const bankTransfer = roundCurrency(
        Math.max(servicePackage.fixed_fee - cardDeposit, 0),
    );

    return {
        deposit_percent: depositPercent,
        card_deposit_amount: cardDeposit,
        bank_transfer_amount: bankTransfer,
        requires_bank_transfer: bankTransfer > 0,
    };
}

function roundCurrency(value: number) {
    return Math.round(value * 100) / 100;
}

function formatMoney(value: number, currency: string) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(value);
}

function LabelWithExplanation({
    htmlFor,
    explanation,
    children,
}: {
    htmlFor: string;
    explanation: Explanation;
    children: string;
}) {
    return (
        <div className="flex items-center gap-2">
            <Label htmlFor={htmlFor}>{children}</Label>
            <Explainer explanation={explanation} />
        </div>
    );
}

const serviceExplanations = {
    targetName: {
        title: 'Target business',
        what: 'The business you may buy or want assessed through due diligence.',
        action: 'Enter the legal, trading, or commonly used business name if you know it.',
        why: 'The target name lets your advisor match evidence, vendor information, and DD reporting to the correct acquisition.',
    },
    vendorName: {
        title: 'Vendor',
        what: 'The seller or selling party connected to the acquisition.',
        action: 'Add the vendor name if known, or leave it blank if you are still exploring.',
        why: 'Vendor context helps the advisor understand who evidence and negotiation points may come from.',
    },
    askingPrice: {
        title: 'Asking price',
        what: 'The price or range currently being discussed for the business.',
        action: 'Enter the current asking price when available, even if it is only indicative.',
        why: 'Price band influences DD scope, package selection, and whether valuation evidence will be proportionate.',
    },
    industry: {
        title: 'Industry',
        what: 'The sector or market the business, idea, or acquisition operates in.',
        action: 'Use a plain-English industry label such as hospitality, software, construction, or health services.',
        why: 'Industry context helps select relevant risks, benchmarks, and advisor review questions.',
    },
    ideaName: {
        title: 'Idea or concept',
        what: 'The short working name for the business idea being explored.',
        action: 'Use the name you would recognize later in your portal and advisor messages.',
        why: 'A clear idea name keeps validation, budget, assessment, and follow-up records connected.',
    },
    customer: {
        title: 'Customer',
        what: 'The person, business, or market segment expected to buy from the idea.',
        action: 'Describe the primary customer as specifically as you can.',
        why: 'Customer clarity is central to testing whether the idea solves a real commercial problem.',
    },
    problem: {
        title: 'Problem to solve',
        what: 'The customer problem, friction, or unmet need the idea is trying to address.',
        action: 'Write the practical problem in the customer’s words where possible.',
        why: 'Problem clarity helps the advisor separate attractive ideas from ideas with evidence of demand.',
    },
    timing: {
        title: 'Timing',
        what: 'When you expect to need the service or make the next decision.',
        action: 'Describe urgency, deadlines, funding timing, or whether you are still exploring.',
        why: 'Timing helps your advisor prioritize the request and avoid opening work too early or too late.',
    },
    notes: {
        title: 'Notes',
        what: 'Any context that does not fit neatly into the structured fields.',
        action: 'Add constraints, concerns, files to expect, or questions you want the advisor to consider.',
        why: 'Notes reduce back-and-forth and help the advisor understand the commercial situation behind the request.',
    },
    pricing: {
        title: 'Package, scope, and fee before request',
        what: 'The package and GST-exclusive fee position visible before this request is submitted.',
        action: 'Review the fee or pricing-to-confirm notice, then tick the acknowledgement only if it matches what you understand.',
        why: 'This avoids surprise costs after requesting access and records what was shown before the advisor review step.',
    },
} satisfies Record<string, Explanation>;
