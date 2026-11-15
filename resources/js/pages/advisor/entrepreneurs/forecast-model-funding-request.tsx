import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/**
 * The external ask, and the reasoning that sized it.
 *
 * The release gate will not read an amount on its own. It asks which
 * requirement the request answers, which scenario that requirement came from,
 * what the business is putting in before reaching outside, and when the money
 * is needed — because a request sized against the base case and one sized
 * against the worst downside are different asks, and the amount cannot say
 * which.
 *
 * The requirement and the scenario are chosen rather than typed. A scenario
 * the model does not run has no deficit to size against, so the gate reads a
 * request naming one as unstated; offering only the six the run builds keeps
 * an answer from being taken that could never be used.
 */

const METRICS: { value: string; label: string }[] = [
    { value: '', label: 'Not stated' },
    {
        value: 'cash_deficit',
        label: 'Cash deficit — money the business runs out of',
    },
    {
        value: 'reserve_gap',
        label: 'Reserve gap — less held than policy requires',
    },
];

const SCENARIOS: { value: string; label: string }[] = [
    { value: '', label: 'Not stated' },
    { value: 'base', label: 'Base' },
    { value: 'revenue_downside', label: 'Revenue downside' },
    { value: 'slow_start', label: 'Slow start' },
    { value: 'cost_upside', label: 'Cost upside' },
    { value: 'collections_delay', label: 'Collections delay' },
    { value: 'combined_downside', label: 'Combined downside' },
];

export type FundingRequestFieldsProps = {
    amount: string;
    neededOn: string;
    metric: string;
    basisScenario: string;
    nonExternalTotal: string;
    reserveOnlyDisclosure: string;
    onAmount: (value: string) => void;
    onNeededOn: (value: string) => void;
    onMetric: (value: string) => void;
    onBasisScenario: (value: string) => void;
    onNonExternalTotal: (value: string) => void;
    onReserveOnlyDisclosure: (value: string) => void;
};

export function FundingRequestFields({
    amount,
    neededOn,
    metric,
    basisScenario,
    nonExternalTotal,
    reserveOnlyDisclosure,
    onAmount,
    onNeededOn,
    onMetric,
    onBasisScenario,
    onNonExternalTotal,
    onReserveOnlyDisclosure,
}: FundingRequestFieldsProps) {
    return (
        <fieldset className="rounded border p-4">
            <legend className="px-1 text-sm font-medium">
                External funding request
            </legend>
            <p className="mb-3 text-sm text-muted-foreground">
                Leave every field blank where no external money is being asked
                for. An amount of zero is a decision not to borrow; blank is
                nobody having said.
            </p>
            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <Label htmlFor="funding-request-amount">
                        Amount requested
                    </Label>
                    <Input
                        id="funding-request-amount"
                        name="funding_request_amount"
                        inputMode="decimal"
                        value={amount}
                        onChange={(event) => onAmount(event.target.value)}
                    />
                </div>
                <div>
                    <Label htmlFor="funding-request-needed-on">Needed by</Label>
                    <Input
                        id="funding-request-needed-on"
                        name="funding_request_needed_on"
                        type="date"
                        value={neededOn}
                        onChange={(event) => onNeededOn(event.target.value)}
                    />
                </div>
                <div>
                    <Label htmlFor="funding-request-metric">
                        Requirement it answers
                    </Label>
                    <select
                        id="funding-request-metric"
                        name="funding_request_metric"
                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        value={metric}
                        onChange={(event) => onMetric(event.target.value)}
                    >
                        {METRICS.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <Label htmlFor="funding-request-basis-scenario">
                        Scenario it is sized against
                    </Label>
                    <select
                        id="funding-request-basis-scenario"
                        name="funding_request_basis_scenario"
                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        value={basisScenario}
                        onChange={(event) =>
                            onBasisScenario(event.target.value)
                        }
                    >
                        {SCENARIOS.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <Label htmlFor="funding-request-non-external">
                        Non-external sources applied first
                    </Label>
                    <Input
                        id="funding-request-non-external"
                        name="funding_request_non_external_total"
                        inputMode="decimal"
                        value={nonExternalTotal}
                        onChange={(event) =>
                            onNonExternalTotal(event.target.value)
                        }
                    />
                    <p className="mt-1 text-xs text-muted-foreground">
                        Subtracted once, when the request is checked. Do not net
                        it off the amount above as well.
                    </p>
                </div>
            </div>
            <div className="mt-4">
                <Label htmlFor="funding-reserve-only-disclosure">
                    Reserve-only disclosure
                </Label>
                <Textarea
                    id="funding-reserve-only-disclosure"
                    name="funding_reserve_only_disclosure"
                    rows={3}
                    value={reserveOnlyDisclosure}
                    onChange={(event) =>
                        onReserveOnlyDisclosure(event.target.value)
                    }
                />
                <p className="mt-1 text-xs text-muted-foreground">
                    Required where the reserve target is short but no month runs
                    out of cash and nothing is being borrowed. Running thinner
                    than policy is allowed to stand, not to go unsaid.
                </p>
            </div>
        </fieldset>
    );
}
