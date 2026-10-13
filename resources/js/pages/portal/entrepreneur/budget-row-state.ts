import type { Dispatch, SetStateAction } from 'react';
import type { BudgetFormState, BudgetGroupKey, BudgetRow } from './plan-types';

const budgetRowDescriptions: Record<string, string> = {
    'product or platform setup':
        'The one-off cost to build or configure what customers will use: a prototype, app, website platform, booking system, payment setup, member portal, no-code tools, or integrations.',
    'brand, landing page, and content':
        'The basics that make the offer credible online: naming, logo or visual identity, landing page copy, images, explainer content, and simple sales material.',
    'launch campaign':
        'The first push to get attention and early customers, such as ads, email outreach, social content, flyers, launch event costs, or promotional offers.',
    'hosting and software tools':
        'Monthly tools needed to keep the product or platform running, such as hosting, domain services, email tools, subscriptions, plugins, or workflow software.',
    'support and admin tools':
        'Tools or services used to help customers and manage operations, such as helpdesk, booking, CRM, payment admin, document storage, or scheduling.',
    'content or product maintenance':
        'Ongoing work needed to keep the product useful, such as updates, new content, bug fixes, community moderation, or small contractor help.',
    'monthly subscribers or members':
        'Expected recurring customers. Amount is the monthly price per subscriber or member, and Qty is the number of paying people expected that month.',
    'founder cash':
        'Money the founder can realistically contribute to start the business. It can be changed later if the amount is only a guess.',
    'website and domain':
        'One-off setup for a basic website, domain name, email domain, landing page, or simple online presence.',
    'basic equipment':
        'Practical items needed before serving customers, such as laptop, tools, furniture, devices, packaging equipment, or starter supplies.',
    'launch marketing':
        'Initial marketing spend to help people discover the business, such as ads, flyers, photography, launch content, or promotional discounts.',
    'brand and website setup':
        'Initial brand and online setup, such as logo, colours, simple website, domain, email, profile pages, and basic sales copy.',
    'professional templates':
        'Reusable documents needed to deliver the service, such as proposals, agreements, onboarding forms, session notes, checklists, or reports.',
    'launch outreach':
        'Initial effort to reach potential customers, such as email campaigns, calls, networking events, direct messages, or introductory offers.',
    'opening stock':
        'Inventory needed before sales can start. This can include finished goods, raw materials, samples, or minimum order quantities.',
    'display, packaging, or signage':
        'Items that help present and sell products, such as packaging, labels, shelves, displays, market stall setup, or signs.',
    'point of sale setup':
        'Tools for taking payments and tracking sales, such as card reader, POS software, barcode labels, receipt printer, or payment setup.',
    'online store setup':
        'One-off setup for an ecommerce store, product pages, payment gateway, checkout, shipping rules, and basic integrations.',
    'opening inventory':
        'Initial products or materials needed to start selling online.',
    'launch ads and content':
        'Initial paid or organic content used to drive traffic, such as social ads, videos, photos, product copy, or influencer samples.',
    'tools and equipment':
        'Tools, safety gear, devices, or specialist equipment needed to deliver the work.',
    'vehicle setup or signage':
        'Vehicle-related setup, such as signage, storage, racks, fit-out, registration changes, or initial road-ready costs.',
    'licences and safety gear':
        'Required licences, certifications, compliance items, protective gear, or safety setup before work begins.',
};

export function budgetRowDescription(row: BudgetRow): string {
    const label = budgetRowLabel(row);

    return (
        row.description ??
        budgetRowDescriptions[label.toLowerCase()] ??
        'Add this suggested line to your budget, then adjust the amount and confidence level if needed.'
    );
}

export function quickAddBudgetRow(
    currentRows: BudgetRow[],
    suggestedRow: BudgetRow,
    revenue = false,
    timed = false,
    fixedCost = false,
): { rows: BudgetRow[]; rowIndex: number; added: boolean } {
    const suggestedLabel = budgetRowLabel(suggestedRow).toLowerCase();
    const rows = mergeBudgetRows(
        currentRows,
        [suggestedRow],
        revenue,
        timed,
        fixedCost,
    );
    const existingIndex = currentRows.findIndex(
        (row) => budgetRowLabel(row).toLowerCase() === suggestedLabel,
    );

    return {
        rows,
        rowIndex: rows.findIndex(
            (row) => budgetRowLabel(row).toLowerCase() === suggestedLabel,
        ),
        added: existingIndex === -1,
    };
}

export function mergeBudgetRows(
    currentRows: BudgetRow[],
    suggestedRows: BudgetRow[],
    revenue = false,
    timed = false,
    fixedCost = false,
) {
    return dedupeBudgetRows([
        ...currentRows
            .map((row) => normaliseBudgetRow(row, revenue, timed, fixedCost))
            .filter((row) => !isBlankBudgetRow(row)),
        ...suggestedRows.map((row) => ({
            ...normaliseBudgetRow(row, revenue, timed, fixedCost),
            confidence: row.confidence ?? 'guess',
        })),
    ]);
}

export function dedupeBudgetRows(rows: BudgetRow[]) {
    const seen = new Set<string>();

    return rows.filter((row) => {
        const key = budgetRowLabel(row).toLowerCase();

        if (key === '' || seen.has(key)) {
            return false;
        }

        seen.add(key);

        return true;
    });
}

export function isBlankBudgetRow(row: BudgetRow): boolean {
    return budgetRowLabel(row) === '' && numberFromInput(row.amount) === 0;
}

export function updateBudgetRow(
    onFormChange: Dispatch<SetStateAction<BudgetFormState>>,
    group: BudgetGroupKey,
    index: number,
    patch: Partial<BudgetRow>,
) {
    onFormChange((current) => ({
        ...current,
        [group]: current[group].map((row, rowIndex) =>
            rowIndex === index ? { ...row, ...patch } : row,
        ),
    }));
}

export function blankBudgetRow(
    revenue = false,
    timed = false,
    fixedCost = false,
): BudgetRow {
    return revenue
        ? {
              label: '',
              amount: '',
              quantity: 1,
              month: 1,
              growth_percent: 0,
              growth_cadence: 'annual',
              growth_cadence_confirmed: false,
              monthly_capacity_units: '',
              capacity_confirmed: false,
              founder_capacity_units: '',
              contractor_unit_cost: '',
              contractor_cost_confirmed: false,
              unit_label: 'units',
              variable_cost_percent: 0,
              unit_cost: '',
              gross_profit_percent: '',
              confidence: 'estimate',
              source_type: 'unverified',
              source_reference: '',
              source_confirmed: false,
          }
        : timed
          ? {
                label: '',
                amount: '',
                quantity: 1,
                month: 1,
                confidence: 'estimate',
            }
          : fixedCost
            ? {
                  label: '',
                  amount: '',
                  quantity: 1,
                  cadence: 'monthly',
                  cadence_confirmed: false,
                  confidence: 'estimate',
                  source_type: 'unverified',
                  source_reference: '',
                  source_confirmed: false,
              }
            : {
                  label: '',
                  amount: '',
                  quantity: 1,
                  confidence: 'estimate',
              };
}

export function normaliseBudgetRow(
    row: BudgetRow,
    revenue = false,
    timed = false,
    fixedCost = false,
): BudgetRow {
    return {
        label: budgetRowLabel(row),
        amount: row.amount ?? '',
        quantity: numberFromInput(row.quantity ?? 1) || 1,
        confidence: budgetRowConfidence(row.confidence),
        ...(timed || revenue
            ? { month: numberFromInput(row.month ?? 1) || 1 }
            : {}),
        ...(fixedCost
            ? {
                  cadence:
                      row.cadence === 'weekly' ||
                      row.cadence === 'fortnightly' ||
                      row.cadence === 'quarterly' ||
                      row.cadence === 'annual'
                          ? row.cadence
                          : 'monthly',
                  cadence_confirmed: Boolean(row.cadence_confirmed),
              }
            : {}),
        ...(fixedCost || revenue
            ? {
                  source_type:
                      row.source_type === 'bank_statement' ||
                      row.source_type === 'xero_ledger' ||
                      row.source_type === 'supplier_quote' ||
                      row.source_type === 'signed_contract' ||
                      row.source_type === 'pipeline_evidence' ||
                      row.source_type === 'owner_record'
                          ? row.source_type
                          : 'unverified',
                  source_reference: String(row.source_reference ?? '').trim(),
                  source_confirmed: Boolean(row.source_confirmed),
              }
            : {}),
        ...(revenue
            ? {
                  growth_percent: signedNumberFromInput(
                      row.growth_percent ?? row.monthly_growth_percent ?? 0,
                  ),
                  growth_cadence:
                      row.growth_cadence === 'annual' ? 'annual' : 'monthly',
                  growth_cadence_confirmed: Boolean(
                      row.growth_cadence_confirmed,
                  ),
                  monthly_capacity_units:
                      row.monthly_capacity_units === undefined ||
                      row.monthly_capacity_units === null
                          ? ''
                          : row.monthly_capacity_units,
                  capacity_confirmed: Boolean(row.capacity_confirmed),
                  founder_capacity_units:
                      row.founder_capacity_units === undefined ||
                      row.founder_capacity_units === null
                          ? ''
                          : row.founder_capacity_units,
                  contractor_unit_cost:
                      row.contractor_unit_cost === undefined ||
                      row.contractor_unit_cost === null
                          ? ''
                          : row.contractor_unit_cost,
                  contractor_cost_confirmed: Boolean(
                      row.contractor_cost_confirmed,
                  ),
                  unit_label: String(row.unit_label ?? 'units'),
                  variable_cost_percent: numberFromInput(
                      row.variable_cost_percent ?? 0,
                  ),
                  unit_cost:
                      row.unit_cost === undefined || row.unit_cost === null
                          ? ''
                          : row.unit_cost,
                  gross_profit_percent:
                      row.gross_profit_percent === undefined ||
                      row.gross_profit_percent === null
                          ? ''
                          : row.gross_profit_percent,
              }
            : {}),
    };
}

export function budgetRowLabel(row: BudgetRow): string {
    return String(row.label ?? '').trim();
}

export function budgetRowConfidence(
    confidence: BudgetRow['confidence'] | null | undefined,
): NonNullable<BudgetRow['confidence']> {
    return confidence === 'known' || confidence === 'guess'
        ? confidence
        : 'estimate';
}

export function numberFromInput(
    value: string | number | null | undefined,
): number {
    const parsed =
        typeof value === 'number'
            ? value
            : Number.parseFloat(String(value ?? '').replace(/[^0-9.-]/g, ''));

    return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
}

export function signedNumberFromInput(
    value: string | number | null | undefined,
    min = -100,
    max = 500,
): number {
    const parsed =
        typeof value === 'number'
            ? value
            : Number.parseFloat(String(value ?? '').replace(/[^0-9.-]/g, ''));

    if (!Number.isFinite(parsed)) {
        return 0;
    }

    return Math.min(max, Math.max(min, parsed));
}
