import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

const comparisonRef = process.argv[2];

if (!comparisonRef || !/^[A-Za-z0-9._/-]+$/.test(comparisonRef)) {
    throw new Error(
        'Usage: assert-explicit-intl-locales.mjs <comparison-ref>.',
    );
}

const changedPages = execFileSync(
    'git',
    ['diff', '--name-only', comparisonRef, '--', 'resources/js/pages'],
    { encoding: 'utf8' },
)
    .split(/\r?\n/)
    .filter(Boolean)
    .filter((path) => /\.(?:ts|tsx)$/.test(path));

const implicitIntl =
    /new\s+Intl\.(?:NumberFormat|DateTimeFormat)\(\s*undefined\s*,/g;
const violations = [];

for (const path of changedPages) {
    const currentCount = matches(
        readFileSync(path, 'utf8'),
        implicitIntl,
    ).length;
    const previous = gitShow(`${comparisonRef}:${path}`);
    const previousCount =
        previous === null ? 0 : matches(previous, implicitIntl).length;

    if (currentCount > previousCount) {
        violations.push(
            `${path} adds ${currentCount - previousCount} implicit-locale Intl formatter(s).`,
        );
    }
}

if (violations.length > 0) {
    throw new Error(
        `${violations.join('\n')} Use the shared explicit locale formatters instead.`,
    );
}

console.log('No new implicit-locale Intl formatters were added to SSR pages.');

function gitShow(path) {
    try {
        return execFileSync('git', ['show', path], {
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'ignore'],
        });
    } catch {
        return null;
    }
}

function matches(value, expression) {
    return [...value.matchAll(expression)];
}
