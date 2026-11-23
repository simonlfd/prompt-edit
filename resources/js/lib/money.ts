/**
 * Money arrives from the server as exact 9-decimal strings ("0.381887500").
 * We never do arithmetic on it in the browser; these helpers only format it, using BigInt so
 * display rounding is exact too.
 */
const NANOS = 1_000_000_000n;

function toNanos(decimal: string): bigint {
    const negative = decimal.startsWith('-');
    const [whole, fraction = ''] = decimal.replace('-', '').split('.');
    const nanos =
        BigInt(whole) * NANOS + BigInt(fraction.padEnd(9, '0').slice(0, 9));

    return negative ? -nanos : nanos;
}

function render(nanos: bigint, decimals: number, trim: boolean): string {
    const negative = nanos < 0n;
    const abs = negative ? -nanos : nanos;
    const step = 10n ** BigInt(9 - decimals);
    const rounded = ((abs + step / 2n) / step) * step; // half-up
    const whole = rounded / NANOS;
    let fraction = (rounded % NANOS)
        .toString()
        .padStart(9, '0')
        .slice(0, decimals);

    if (trim) {
        fraction = fraction.replace(/0+$/, '').padEnd(2, '0');
    }

    return `${negative ? '-' : ''}$${whole.toLocaleString('en-US')}.${fraction}`;
}

/** "$0.3819": rounded for reading. Tiny non-zero amounts show as "<$0.0001" rather than "$0.0000". */
export function formatUsd(
    decimal: string | null | undefined,
    decimals = 4,
): string {
    if (decimal === null || decimal === undefined) {
        return '—';
    }

    const nanos = toNanos(decimal);
    const min = 10n ** BigInt(9 - decimals);

    if (nanos !== 0n && nanos > -min && nanos < min) {
        return `<$0.${'0'.repeat(decimals - 1)}1`;
    }

    return render(nanos, decimals, false);
}

/** "$0.3818875": the exact amount, trailing zeros trimmed. Used in tooltips and the admin report. */
export function formatUsdExact(decimal: string | null | undefined): string {
    return decimal === null || decimal === undefined
        ? '—'
        : render(toNanos(decimal), 9, true);
}

export function isNegative(decimal: string): boolean {
    return toNanos(decimal) < 0n;
}

/** Exact comparison of two decimal strings: -1, 0 or 1. */
export function compareUsd(a: string, b: string): number {
    const diff = toNanos(a) - toNanos(b);

    return diff === 0n ? 0 : diff < 0n ? -1 : 1;
}
