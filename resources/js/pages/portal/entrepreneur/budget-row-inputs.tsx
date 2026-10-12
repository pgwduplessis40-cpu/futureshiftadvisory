import type { Ref } from 'react';
import type { BudgetRow } from './plan-types';

export function BudgetEvidenceInputs({
    row,
    revenue = false,
    onChange,
}: {
    row: BudgetRow;
    revenue?: boolean;
    onChange: (changes: Partial<BudgetRow>) => void;
}) {
    return (
        <>
            <label className="grid gap-1 text-xs">
                <span className="text-muted-foreground">Evidence source</span>
                <select
                    value={row.source_type ?? 'unverified'}
                    onChange={(event) =>
                        onChange({
                            source_type: event.target
                                .value as BudgetRow['source_type'],
                            source_confirmed: false,
                        })
                    }
                    className="h-9 rounded-md border bg-background px-2 text-sm"
                >
                    <option value="unverified">Select source</option>
                    <option value="bank_statement">Bank statement</option>
                    <option value="xero_ledger">Xero ledger</option>
                    <option value="supplier_quote">Supplier quote</option>
                    <option value="signed_contract">Signed contract</option>
                    <option value="pipeline_evidence">
                        {revenue ? 'Pipeline evidence' : 'Pricing evidence'}
                    </option>
                    <option value="owner_record">Owner record</option>
                </select>
            </label>
            <BudgetInput
                label="Evidence reference"
                value={row.source_reference ?? ''}
                onChange={(value) =>
                    onChange({
                        source_reference: value,
                        source_confirmed: false,
                    })
                }
            />
            <label className="flex items-end gap-2 pb-2 text-xs text-muted-foreground">
                <input
                    type="checkbox"
                    name="source_confirmed"
                    aria-label={`Confirm ${revenue ? 'revenue' : 'cost'} evidence source`}
                    checked={Boolean(row.source_confirmed)}
                    onChange={(event) =>
                        onChange({ source_confirmed: event.target.checked })
                    }
                />
                Source checked
            </label>
        </>
    );
}

export function BudgetInput({
    label,
    value,
    onChange,
    type = 'text',
    min,
    inputRef,
}: {
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    type?: 'text' | 'number';
    min?: number;
    inputRef?: Ref<HTMLInputElement>;
}) {
    return (
        <label className="grid gap-1 text-xs">
            <span className="text-muted-foreground">{label}</span>
            <input
                ref={inputRef}
                type={type}
                value={value}
                min={type === 'number' ? (min ?? 0) : undefined}
                onChange={(event) => onChange(event.target.value)}
                className="h-9 min-w-0 rounded-md border bg-background px-2 text-sm"
            />
        </label>
    );
}

export function BudgetConfidenceSelect({
    value,
    onChange,
}: {
    value: NonNullable<BudgetRow['confidence']>;
    onChange: (value: NonNullable<BudgetRow['confidence']>) => void;
}) {
    return (
        <label className="grid gap-1 text-xs">
            <span className="text-muted-foreground">Confidence</span>
            <select
                value={value}
                onChange={(event) =>
                    onChange(
                        event.target.value as NonNullable<
                            BudgetRow['confidence']
                        >,
                    )
                }
                className="h-9 min-w-0 rounded-md border bg-background px-2 text-sm"
            >
                <option value="known">Known</option>
                <option value="estimate">Estimate</option>
                <option value="guess">Guess</option>
            </select>
        </label>
    );
}
