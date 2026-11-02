import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { PNG } from 'pngjs';
import {
    approvedScreenshotNames,
    compareApprovedScreenshots,
} from './browser-screenshot.mjs';

const approved = approvedScreenshotNames(
    'budget-and-runway-builder-desktop.png',
).map((name) =>
    readFileSync(new URL(`../e2e/snapshots/${name}`, import.meta.url)),
);

// Exercise both reviewed CI renderings, including Puppeteer's Uint8Array.
for (const actual of approved) {
    const result = compareApprovedScreenshots(new Uint8Array(actual), approved);
    assert.equal(result.changedPixels, 0);
    assert.equal(result.allowedChangedPixels, 1440);
}

assert.deepEqual(
    approvedScreenshotNames('budget-and-runway-builder-mobile.png'),
    ['budget-and-runway-builder-mobile.png'],
);

function rejectChange(image) {
    const result = compareApprovedScreenshots(PNG.sync.write(image), approved);
    assert.ok(result.changedPixels > result.allowedChangedPixels);
}

// Removing the request button must fail against BOTH approved renderings.
for (const source of approved) {
    const image = PNG.sync.read(source);

    for (let y = 762; y < 805; y++) {
        for (let x = 382; x < 640; x++) {
            const index = (y * image.width + x) * 4;
            image.data.fill(255, index, index + 4);
        }
    }

    rejectChange(image);
}

// Moving the page content must also fail, even if its text is unchanged.
const shifted = PNG.sync.read(approved[0]);
PNG.bitblt(shifted, shifted, 280, 100, 1130, 790, 285, 105);
rejectChange(shifted);

const resized = new PNG({ width: 1441, height: 1000 });
assert.throws(
    () => compareApprovedScreenshots(PNG.sync.write(resized), approved),
    /dimensions changed/,
);
assert.throws(() => compareApprovedScreenshots(approved[0], []), /required/);

console.log(
    'Reviewed renderings pass; missing controls, movement and resizing fail.',
);
