import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * How long forecast revenue takes to become cash.
 *
 * Without these the model recognises revenue and never collects it: operating
 * receipts come only from trade items, which are opening debtors and one-offs
 * typed as amounts. A revenue downside then barely moves the cash position,
 * and a funding ask sized against that position understates what the business
 * needs. The consequence is stated here rather than left to a rule, because
 * an advisor who leaves this blank should know what it costs.
 *
 * One set of terms for the whole model. A business that genuinely bills some
 * work differently needs per-stream terms, which is a later change; stating
 * one set is honest about being an assumption.
 */

export type CollectionFieldsProps = {
    debtorDays: string;
    onInvoicePercent: string;
    source: string;
    onDebtorDays: (value: string) => void;
    onInvoicePercentChange: (value: string) => void;
    onSource: (value: string) => void;
};

export function CollectionFields({
    debtorDays,
    onInvoicePercent,
    source,
    onDebtorDays,
    onInvoicePercentChange,
    onSource,
}: CollectionFieldsProps) {
    return (
        <fieldset className="rounded border p-4">
            <legend className="px-1 text-sm font-medium">
                Revenue collection terms
            </legend>
            <p className="mb-3 text-sm text-muted-foreground">
                Revenue is billed at month end. Leave all three blank if these
                have not been established — the model will collect none of its
                forecast revenue and say so, rather than assume it arrives the
                moment it is earned.
            </p>
            <div className="grid gap-4 sm:grid-cols-3">
                <div>
                    <Label htmlFor="collection-on-invoice">
                        Collected when billed (%)
                    </Label>
                    <Input
                        id="collection-on-invoice"
                        name="collection_on_invoice_percent"
                        inputMode="decimal"
                        value={onInvoicePercent}
                        onChange={(event) =>
                            onInvoicePercentChange(event.target.value)
                        }
                    />
                </div>
                <div>
                    <Label htmlFor="collection-debtor-days">
                        Balance collected after (days)
                    </Label>
                    <Input
                        id="collection-debtor-days"
                        name="collection_debtor_days"
                        inputMode="numeric"
                        value={debtorDays}
                        onChange={(event) => onDebtorDays(event.target.value)}
                    />
                </div>
                <div>
                    <Label htmlFor="collection-source">
                        Record these come from
                    </Label>
                    <Input
                        id="collection-source"
                        name="collection_source"
                        value={source}
                        onChange={(event) => onSource(event.target.value)}
                    />
                </div>
            </div>
        </fieldset>
    );
}
