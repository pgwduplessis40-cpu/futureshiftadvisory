import { Explainer } from '@/components/explainer';
import type { Explanation } from '@/components/explainer';

export type BudgetTerm =
    | 'amount'
    | 'break-even'
    | 'cash-curve'
    | 'cash-positive'
    | 'capacity'
    | 'confidence'
    | 'cost-treatment'
    | 'cost-inflation'
    | 'creditor-days'
    | 'debtor-days'
    | 'direct-cost'
    | 'evidence-reference'
    | 'evidence-source'
    | 'first-due-year'
    | 'forecast-month'
    | 'forecast-start-month'
    | 'funding-position'
    | 'funding-purpose'
    | 'growth-cadence'
    | 'growth-rate'
    | 'gross-profit'
    | 'interest-rate'
    | 'loan-term'
    | 'lowest-cash'
    | 'net-profit'
    | 'opening-cash'
    | 'payment-frequency'
    | 'profit-year'
    | 'rate-billing-period'
    | 'renewal-cycle'
    | 'revenue-basis'
    | 'runway'
    | 'units'
    | 'useful-life';

const explanations: Record<BudgetTerm, Explanation> = {
    amount: {
        title: 'Amount',
        what: 'The total cost, funding, or income figure before the quantity is applied.',
        action: 'Use the best current figure from an invoice, quote, price list, or a clearly labelled estimate.',
        why: 'The forecast multiplies this by the quantity and places it in the relevant month or year.',
    },
    'break-even': {
        title: 'Break-even year',
        what: 'The first forecast year in which revenue covers the costs of running the business.',
        action: 'Compare it with the sales plan and funding runway. If it arrives late, test a different price, cost, timing, or funding assumption.',
        why: 'It shows when the operating model is expected to stand on its own. It is different from a cash-positive year.',
    },
    'cash-curve': {
        title: '12-month cash curve',
        what: 'A month-by-month view of the forecast bank balance and revenue. It shows when money actually arrives and leaves, not only annual profit.',
        action: 'Check the lowest-cash marker, the break-even marker, and whether the balance stays above zero long enough.',
        why: 'A business can make a yearly profit yet run out of cash before customers pay or after a large bill falls due.',
    },
    'cash-positive': {
        title: 'Cash-positive year',
        what: 'The first forecast year in which the business generates more cash than it uses during that year.',
        action: 'Read it beside runway and the lowest cash point, not as a replacement for either.',
        why: 'A business can reach break-even before accumulated setup costs, delayed customer payments, or loan repayments allow cash to recover.',
    },
    confidence: {
        title: 'Confidence',
        what: 'How certain you are that this number is right: Known, Estimate, or Guess.',
        action: 'Use Known for checked records, Estimate for a supported assumption, and Guess only until better evidence is available.',
        why: 'It helps you and your advisor focus on the numbers most likely to change the funding decision.',
    },
    capacity: {
        title: 'Capacity',
        what: 'The most units you or the business can deliver in one month, such as customers served, jobs completed, or subscriptions supported.',
        action: 'Base it on available hours, staff, equipment, and the operating model in the plan.',
        why: 'It keeps the revenue forecast from assuming more sales than the business can deliver.',
    },
    'cost-treatment': {
        title: 'Cost treatment',
        what: 'Whether a future item is an operating cost that is paid when due, or a capital asset that will support the business for several years.',
        action: 'Use Operating cost for renewals and normal running costs; use Capital asset for long-lived equipment or a platform.',
        why: 'The treatment affects how the item is interpreted in the forecast and budget pack.',
    },
    'cost-inflation': {
        title: 'Cost / CPI %',
        what: 'The yearly percentage by which costs are expected to rise after the first forecast year.',
        action: 'Use a modest evidence-based assumption, or leave it at zero while you confirm supplier pricing.',
        why: 'Holding costs flat can make later years look stronger than they are.',
    },
    'creditor-days': {
        title: 'Creditor days',
        what: 'The average number of days the business has before it must pay suppliers after receiving a bill.',
        action: 'Use agreed supplier terms and your actual payment pattern where possible.',
        why: 'It affects when a cost leaves the bank account, which can be different from when the cost is recorded.',
    },
    'debtor-days': {
        title: 'Debtor days',
        what: 'The average number of days customers take to pay after you invoice them.',
        action: 'Use your payment terms and actual collection history where available.',
        why: 'Sales do not become usable cash until the customer pays.',
    },
    'direct-cost': {
        title: 'Direct cost %',
        what: 'The part of each sale spent directly delivering it, such as materials, a contractor, payment fees, or a commission.',
        action: 'Enter the percentage or unit cost that is incurred only when a sale happens.',
        why: 'It determines gross profit: the money left from a sale to pay overheads and create profit.',
    },
    'evidence-reference': {
        title: 'Evidence reference',
        what: 'The exact record someone can check, such as “Xero invoice August 2026”, a quote number, a document name, or a public price-list URL.',
        action: 'Name or link the specific evidence. Use the notes or your plan to explain the reasoning behind an estimate.',
        why: 'It makes the figure traceable without turning the source category into a vague explanation.',
    },
    'evidence-source': {
        title: 'Evidence source',
        what: 'The type of record supporting this figure, such as a bank statement, Xero ledger, supplier quote, signed contract, pricing evidence, or your own record.',
        action: 'Choose the closest source type, then add the precise record in Evidence reference.',
        why: 'A source category tells a reviewer how reliable a number is; it is different from an explanation of how you chose it.',
    },
    'first-due-year': {
        title: 'First due year',
        what: 'The first forecast year in which a future cost is expected to be paid.',
        action: 'For a trademark renewal due in ten years, choose Year 10; for a planned upgrade in three years, choose Year 3.',
        why: 'It keeps long-term commitments visible and includes them in the cash forecast when they fall inside the chosen horizon.',
    },
    'forecast-month': {
        title: 'Forecast month',
        what: 'The month in which a cost is paid or revenue starts. Month 1 is anchored to the Forecast start month.',
        action: 'Match it to the launch plan, contract date, deposit due date, or expected first sale.',
        why: 'Timing changes the cash curve even when the annual total stays the same.',
    },
    'forecast-start-month': {
        title: 'Forecast start month',
        what: 'The calendar month represented by Month 1 of the budget.',
        action: 'Set it to the planned launch or decision month and check it against your milestones.',
        why: 'It connects the budget’s timing to the business plan and prevents a cash forecast with no real-world date.',
    },
    'funding-position': {
        title: 'Funding position',
        what: 'Whether the plan is self-funded, needs external funding, or is still undecided.',
        action: 'Choose the position that matches the written plan and state what an external request will pay for.',
        why: 'It connects the funding gap in the budget to a clear decision for a lender, investor, or advisor.',
    },
    'funding-purpose': {
        title: 'Funding purpose',
        what: 'A plain-language statement of what requested funding will pay for, such as setup, stock, working capital, or a particular launch milestone.',
        action: 'Describe the items or period funded, not simply “growth”.',
        why: 'A defensible funding request explains both how much is needed and what it unlocks in the plan.',
    },
    'growth-cadence': {
        title: 'Growth cadence',
        what: 'How often the growth percentage is applied: every month or once each year.',
        action: 'Use monthly only for a deliberate early ramp; use annual for a steadier longer-term assumption.',
        why: 'The same percentage grows much faster when compounded monthly.',
    },
    'growth-rate': {
        title: 'Growth %',
        what: 'The percentage by which the revenue line is expected to increase over the selected cadence.',
        action: 'Tie it to real drivers in the plan: leads, conversion, delivery capacity, price changes, or new channels.',
        why: 'Growth is not a target by itself; it changes revenue, cash, break-even, and funding needs.',
    },
    'gross-profit': {
        title: 'GP %',
        what: 'Gross profit percentage: the share of each sale left after direct delivery costs, before rent, software, wages, and other overheads.',
        action: 'Check it against your price and the direct cost of delivering one sale.',
        why: 'A strong sales total with a weak gross margin may still be unable to cover fixed costs.',
    },
    'interest-rate': {
        title: 'Interest %',
        what: 'The yearly cost charged by a lender for borrowing money, before fees and repayments.',
        action: 'Use the lender’s proposed annual rate and update it if the offer changes.',
        why: 'Interest affects the true cost of funding and the cash available for the business.',
    },
    'loan-term': {
        title: 'Loan term',
        what: 'The number of years allowed to repay a loan.',
        action: 'Use the lender’s proposed term and interest rate, then compare it with the cash curve.',
        why: 'Loan repayments affect future cash and are different from the amount initially received.',
    },
    'lowest-cash': {
        title: 'Lowest cash point',
        what: 'The smallest forecast bank balance and the month in which it occurs.',
        action: 'Make sure funding and contingency cover this point, then test what happens if sales are slower or a bill is higher.',
        why: 'It identifies the greatest cash-pressure point in the plan, even if later months recover.',
    },
    'net-profit': {
        title: 'Net profit',
        what: 'Profit after the relevant expenses. NPBT is before tax; NPAT is after estimated company tax.',
        action: 'Use this as a target to test against the operating model, not as an assumption that overrides the cash forecast.',
        why: 'It shows whether the business can earn a sustainable return after all costs, while cash flow still shows whether bills can be paid on time.',
    },
    'opening-cash': {
        title: 'Opening cash',
        what: 'Money already available in the business at the start of Month 1.',
        action: 'Use the current bank balance that is genuinely available for the business, not future sales or an unconfirmed loan.',
        why: 'It is the starting point for the cash curve and runway calculation.',
    },
    'payment-frequency': {
        title: 'Payment frequency',
        what: 'How often a bill is actually paid: weekly, fortnightly, monthly, quarterly, or annually.',
        action: 'Match the invoice or contract. For example, enter $600 with Annual if it is one $600 bill each year.',
        why: 'The forecast converts the billing pattern to cash timing; it should not be hidden in the Units field.',
    },
    'profit-year': {
        title: 'Profit year',
        what: 'The first forecast year with a positive profit after the modelled expenses.',
        action: 'Compare it with break-even and cash-positive timing to understand the difference between profit and cash.',
        why: 'Profit is an important outcome, but it does not by itself prove there is enough cash to pay bills on time.',
    },
    'rate-billing-period': {
        title: 'Rate / billing period',
        what: 'The price for one billing event, before Units. For example, $50 monthly or $600 annually.',
        action: 'Enter the amount on the invoice, quote, or price list, then select the actual payment frequency.',
        why: 'The forecast uses the rate, frequency, and units together to calculate the correct monthly cost.',
    },
    'renewal-cycle': {
        title: 'Renewal cycle',
        what: 'A future cost that is due again at a set interval, such as a trademark renewal every 10 years.',
        action: 'Set the first due year, choose Renewal cycle, and enter how many years until it repeats. Choose One-off if it happens only once.',
        why: 'It keeps future obligations visible without incorrectly treating a long-term renewal as a monthly cost.',
    },
    'revenue-basis': {
        title: 'Revenue after Year 1',
        what: 'The figure used to begin Year 2: either the Month 12 run-rate or the average monthly revenue across Year 1.',
        action: 'Use the exit run-rate for a deliberate ramp; use the average only for a seasonal or intentionally averaged forecast.',
        why: 'It avoids an artificial drop or jump between the first and second year.',
    },
    runway: {
        title: 'Runway',
        what: 'How many months the forecast cash balance can keep the business operating before it falls below zero.',
        action: 'Set the survival period you are aiming for, then compare it with the calculated runway and improve the plan or funding if there is a gap.',
        why: 'Runway shows whether there is enough cash to reach the sales and break-even milestones in the plan.',
    },
    units: {
        title: 'Units',
        what: 'The number of parallel items bought or sold at the stated rate: for example, 3 software licences, 2 staff seats, or 5 subscriptions.',
        action: 'Use 1 for one billed item. Do not enter the number of months or payments in a year here.',
        why: 'Units multiplies the rate. Payment frequency separately tells the forecast when the bill is due.',
    },
    'useful-life': {
        title: 'Useful life years',
        what: 'How long a capital item is expected to support the business before it needs replacement.',
        action: 'Use a realistic replacement life for equipment, vehicles, or a platform—not the length of the financing.',
        why: 'It helps distinguish a long-lived capital asset from a normal operating cost.',
    },
};

const labelTerms: Partial<Record<string, BudgetTerm>> = {
    Amount: 'amount',
    'Creditor days': 'creditor-days',
    'Cost %': 'direct-cost',
    'Evidence reference': 'evidence-reference',
    'First due year': 'first-due-year',
    'Forecast month': 'forecast-month',
    'Founder units / month': 'units',
    'Funding purpose (required for an external request)': 'funding-purpose',
    'GP %': 'gross-profit',
    'Growth %': 'growth-rate',
    'Interest %': 'interest-rate',
    'Max units / month': 'capacity',
    'Rate / billing period': 'rate-billing-period',
    'Renew every (years)': 'renewal-cycle',
    Term: 'loan-term',
    Units: 'units',
    Qty: 'units',
    'Useful life years': 'useful-life',
    'When paid (forecast month)': 'forecast-month',
};

export function budgetTermForLabel(label: string): BudgetTerm | undefined {
    return labelTerms[label];
}

export function BudgetTermLabel({
    term,
    children,
}: {
    term: BudgetTerm;
    children: string;
}) {
    return (
        <span className="flex items-center gap-1">
            <span>{children}</span>
            <Explainer
                explanation={explanations[term]}
                label={`Explain ${children}`}
                triggerClassName="size-5 border-transparent bg-transparent"
                contentClassName="text-left"
            />
        </span>
    );
}

export function FounderBudgetGuide() {
    return (
        <section
            className="rounded-md border bg-muted/20 p-4"
            aria-labelledby="founder-budget-guide"
        >
            <div className="space-y-1">
                <h2 id="founder-budget-guide" className="text-sm font-medium">
                    Founder-first budget guide
                </h2>
                <p className="text-sm text-muted-foreground">
                    You do not need finance experience to build a useful first
                    forecast. Use the ? beside a term for a short explanation;
                    this guide shows how the budget supports your business plan.
                </p>
            </div>
            <div className="mt-4 grid gap-3 text-sm md:grid-cols-3">
                <GuideGroup
                    title="What you enter"
                    items={[
                        [
                            'Costs',
                            'Rate/billing period × Units, plus when the bill is paid.',
                        ],
                        [
                            'Evidence',
                            'Evidence source is the kind of record; Evidence reference names the exact record.',
                        ],
                        [
                            'Confidence',
                            'Known, Estimate, or Guess tells your advisor which assumptions need checking first.',
                        ],
                    ]}
                />
                <GuideGroup
                    title="What the forecast tells you"
                    items={[
                        [
                            'Cash curve',
                            'The predicted bank balance month by month. The lowest cash point is the tightest point in that journey.',
                        ],
                        [
                            'Runway',
                            'How many months your current cash and funding can keep the business going.',
                        ],
                        [
                            'Break-even',
                            'The first year revenue covers trading costs. It is not necessarily the same as being cash-positive.',
                        ],
                    ]}
                />
                <GuideGroup
                    title="How this links to the plan"
                    items={[
                        [
                            'Revenue',
                            'Pricing, customer demand, sales channels, and delivery capacity support the revenue assumptions.',
                        ],
                        [
                            'Costs',
                            'The operating model, suppliers, staff, premises, and technology support the cost assumptions.',
                        ],
                        [
                            'Funding',
                            'Goals, launch milestones, compliance, and growth items explain what money is needed for.',
                        ],
                    ]}
                />
            </div>
            <a
                href="#business-plan-requirements"
                className="mt-4 inline-flex text-sm font-medium underline underline-offset-4"
            >
                Review the linked business-plan requirements
            </a>
        </section>
    );
}

function GuideGroup({
    title,
    items,
}: {
    title: string;
    items: Array<[string, string]>;
}) {
    return (
        <div className="rounded-md border bg-background p-3">
            <h3 className="font-medium">{title}</h3>
            <dl className="mt-2 space-y-2 text-xs leading-snug">
                {items.map(([label, copy]) => (
                    <div key={label}>
                        <dt className="font-medium">{label}</dt>
                        <dd className="text-muted-foreground">{copy}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
