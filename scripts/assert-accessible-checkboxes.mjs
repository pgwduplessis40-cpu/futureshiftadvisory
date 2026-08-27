/* global process */

import { execFileSync } from 'node:child_process';

const comparisonRef = process.argv[2] ?? 'HEAD';

if (!/^[A-Za-z0-9._/-]+$/.test(comparisonRef)) {
    throw new Error(`Unsafe comparison revision ${comparisonRef}.`);
}

const patch = execFileSync(
    'git',
    ['diff', '--unified=0', comparisonRef, '--', 'resources/js'],
    { encoding: 'utf8' },
);

for (const filePatch of patch.split(/^diff --git /m)) {
    const path = filePatch.match(/^a\/(resources\/js\/[^\s]+) b\//m)?.[1];

    if (!path || !/\.(?:tsx?|jsx?)$/.test(path)) {
        continue;
    }

    for (const hunk of filePatch.split(/^@@ .* @@$/m).slice(1)) {
        const additions = hunk
            .split(/\r?\n/)
            .filter((line) => line.startsWith('+') && !line.startsWith('+++'))
            .map((line) => line.slice(1))
            .join('\n');
        const checkboxUses =
            additions.match(/<(?:Checkbox|input)\b[\s\S]{0,600}?(?:\/>|>)/g) ??
            [];

        for (const use of checkboxUses) {
            const isCheckbox =
                /<Checkbox\b/.test(use) || /type=["']checkbox["']/.test(use);

            if (!isCheckbox) {
                continue;
            }

            const hasName = /\bname=/.test(use);
            const hasAccessibleName =
                /aria-label=|aria-labelledby=/.test(use) ||
                /<Label\b[^>]*htmlFor=|<label\b[^>]*htmlFor=/.test(additions);

            if (!hasName || !hasAccessibleName) {
                throw new Error(
                    `${path} adds a checkbox without the required name and accessible label contract.`,
                );
            }
        }
    }
}

console.log(
    'New checkbox controls satisfy the name and accessible-label contract.',
);
