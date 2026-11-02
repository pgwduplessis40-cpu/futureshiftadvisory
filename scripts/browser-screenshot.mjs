import { Buffer } from 'node:buffer';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';

// Only this reviewed capture has two observed Chromium text renderings.
// Keep alternatives explicit: an arbitrary PNG must never approve a change.
export function approvedScreenshotNames(snapshotName) {
    return snapshotName === 'budget-and-runway-builder-desktop.png'
        ? [snapshotName, 'budget-and-runway-builder-desktop.alternate.png']
        : [snapshotName];
}

export function compareApprovedScreenshots(actual, approved) {
    if (approved.length === 0) {
        throw new Error('An approved screenshot is required.');
    }

    const received = PNG.sync.read(Buffer.from(actual));
    const { width, height } = received;
    // Retain the existing one-pixel-per-thousand budget for EACH complete
    // approved image. Never combine matching regions from different images.
    const allowedChangedPixels = Math.ceil(width * height * 0.001);
    let closest;

    for (const bytes of approved) {
        const expected = PNG.sync.read(Buffer.from(bytes));

        if (expected.width !== width || expected.height !== height) {
            throw new Error('Screenshot dimensions changed.');
        }

        const diff = new PNG({ width, height });
        const changedPixels = pixelmatch(
            expected.data,
            received.data,
            diff.data,
            width,
            height,
            { threshold: 0.2 },
        );

        if (!closest || changedPixels < closest.changedPixels) {
            closest = { changedPixels, diff };
        }
    }

    return { ...closest, allowedChangedPixels };
}
