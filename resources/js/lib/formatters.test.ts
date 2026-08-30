import assert from 'node:assert/strict';
import test from 'node:test';
import {
    formatNzDate,
    formatNzMonth,
    NZ_LOCALE,
    NZ_TIME_ZONE,
} from './formatters';

test('date-only values retain their calendar day independently of the runtime timezone', () => {
    const expected = new Intl.DateTimeFormat(NZ_LOCALE, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date('2026-04-05T00:00:00.000Z'));

    assert.equal(formatNzDate('2026-04-05'), expected);
    assert.equal(
        formatNzMonth('2026-04'),
        new Intl.DateTimeFormat(NZ_LOCALE, {
            month: 'short',
            year: 'numeric',
            timeZone: 'UTC',
        }).format(new Date('2026-04-01T00:00:00.000Z')),
    );
});

test('timestamp values use the explicit Auckland business timezone', () => {
    const timestamp = '2026-09-26T14:30:00.000Z';
    const options = {
        dateStyle: 'medium' as const,
        timeStyle: 'short' as const,
    };

    assert.equal(
        formatNzDate(timestamp, options),
        new Intl.DateTimeFormat(NZ_LOCALE, {
            ...options,
            timeZone: NZ_TIME_ZONE,
        }).format(new Date(timestamp)),
    );
});
