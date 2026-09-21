import type { ServicePackage } from './ServiceActivationRequest';

export function selectPricingPackage(
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

export function normalizeMoneyInput(value: string) {
    return value.replace(/[$,\s]/g, '');
}

export function packagePaymentSplit(servicePackage: ServicePackage) {
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

export function formatMoney(value: number, currency: string) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(value);
}

export function parseMoneyInput(value: string) {
    const normalized = normalizeMoneyInput(value);

    if (normalized === '') {
        return null;
    }

    const parsed = Number(normalized);

    return Number.isFinite(parsed) && parsed >= 0 ? parsed : null;
}

function roundCurrency(value: number) {
    return Math.round(value * 100) / 100;
}
