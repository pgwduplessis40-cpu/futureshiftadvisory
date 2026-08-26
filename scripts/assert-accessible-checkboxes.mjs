/* global process */

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

const comparisonRef = process.argv[2] ?? 'HEAD';

if (!/^[A-Za-z0-9._/-]+$/.test(comparisonRef)) {
    throw new Error(`Unsafe comparison revision ${comparisonRef}.`);
}

const changed = execFileSync(
    'git',
    ['diff', '--name-only', comparisonRef, '--', 'resources/js'],
    { encoding: 'utf8' },
)
    .split(/\r?\n/)
    .filter((path) => /\.(?:tsx?|jsx?)$/.test(path));

for (const path of changed) {
    const source = readFileSync(path, 'utf8');
    const checkboxUses = source.match(/<(?:Checkbox|input)\b[\s\S]{0,600}?(?:\/>|>)/g) ?? [];

    for (const use of checkboxUses) {
        const isCheckbox = /<Checkbox\b/.test(use) || /type=["']checkbox["']/.test(use);

        if (!isCheckbox) {
            continue;
        }

        const hasName = /\bname=/.test(use);
        const hasAccessibleName = /aria-label=|aria-labelledby=|<Label\b/.test(source);

        if (!hasName || !hasAccessibleName) {
            throw new Error(`${path} adds a checkbox without the required name and accessible label contract.`);
        }
    }
}

console.log('New checkbox controls satisfy the name and accessible-label contract.');
