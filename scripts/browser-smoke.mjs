import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import process from 'node:process';
import axe from 'axe-core';
import { generateSync } from 'otplib';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';
import puppeteer from 'puppeteer';

const required = [
    'E2E_BASE_URL',
    'E2E_ADVISOR_EMAIL',
    'E2E_ADVISOR_PASSWORD',
    'E2E_ADVISOR_MFA_SECRET',
    'E2E_CLIENT_EMAIL',
    'E2E_CLIENT_PASSWORD',
    'E2E_CLIENT_MFA_SECRET',
    'E2E_NPO_EMAIL',
    'E2E_NPO_PASSWORD',
    'E2E_NPO_MFA_SECRET',
    'E2E_CLIENT_SCREEN_PATH',
];
const missing = required.filter((name) => !process.env[name]?.trim());

if (missing.length > 0) {
    throw new Error(
        `browser:e2e requires ${missing.join(', ')}. Credentials and MFA secrets must come from CI secrets.`,
    );
}

const baseUrl = new URL(process.env.E2E_BASE_URL);
const screenshotRoot = process.env.E2E_SNAPSHOT_DIR ?? 'e2e/snapshots';
const artifactRoot =
    process.env.E2E_ARTIFACT_DIR ?? 'storage/app/e2e-artifacts';
const failures = [];
mkdirSync(artifactRoot, { recursive: true });

const accounts = {
    advisor: {
        email: process.env.E2E_ADVISOR_EMAIL,
        password: process.env.E2E_ADVISOR_PASSWORD,
        mfaSecret: process.env.E2E_ADVISOR_MFA_SECRET,
    },
    client: {
        email: process.env.E2E_CLIENT_EMAIL,
        password: process.env.E2E_CLIENT_PASSWORD,
        mfaSecret: process.env.E2E_CLIENT_MFA_SECRET,
    },
    npo: {
        email: process.env.E2E_NPO_EMAIL,
        password: process.env.E2E_NPO_PASSWORD,
        mfaSecret: process.env.E2E_NPO_MFA_SECRET,
    },
};

const flows = [
    {
        name: 'Login and onboarding',
        account: accounts.advisor,
        path: process.env.E2E_ONBOARDING_PATH ?? '/dashboard',
        expected: process.env.E2E_ONBOARDING_EXPECT ?? 'Dashboard',
    },
    {
        name: 'Interactive Dashboard',
        account: accounts.advisor,
        path: process.env.E2E_ADVISOR_DASHBOARD_PATH ?? '/dashboard',
        expected: process.env.E2E_ADVISOR_DASHBOARD_EXPECT ?? 'Dashboard',
    },
    {
        name: 'NPO Module',
        account: accounts.npo,
        path: process.env.E2E_NPO_PATH ?? '/portal/npo-board',
        expected: process.env.E2E_NPO_EXPECT ?? 'Board',
    },
    {
        name: 'Budget and Runway Builder',
        account: accounts.client,
        path: process.env.E2E_BUDGET_PATH ?? '/portal/business-plan-budget',
        expected: process.env.E2E_BUDGET_EXPECT ?? 'Budget',
    },
    {
        name: 'Client Screen',
        account: accounts.advisor,
        path: process.env.E2E_CLIENT_SCREEN_PATH,
        expected: process.env.E2E_CLIENT_SCREEN_EXPECT ?? 'Screen',
    },
];

const browser = await puppeteer.launch({
    headless: true,
    args: [
        '--use-fake-device-for-media-stream',
        '--use-fake-ui-for-media-stream',
        '--autoplay-policy=no-user-gesture-required',
        ...(process.env.CI
            ? [
                  // GitHub's isolated Linux runner does not support Chrome's
                  // regular sandbox or a large shared-memory mount.
                  '--no-sandbox',
                  '--disable-setuid-sandbox',
                  '--disable-dev-shm-usage',
                  '--disable-gpu',
              ]
            : []),
    ],
});

try {
    for (const flow of flows) {
        await runFlow(browser, flow);
    }
} finally {
    await browser.close();
}

if (failures.length > 0) {
    throw new Error(
        `Browser E2E failures:\n${failures.map((failure) => `- ${failure}`).join('\n')}`,
    );
}

async function runFlow(browserInstance, flow) {
    for (const viewport of [
        { name: 'desktop', width: 1440, height: 1000, isMobile: false },
        { name: 'mobile', width: 390, height: 844, isMobile: true },
    ]) {
        await runFlowViewport(browserInstance, flow, viewport);
    }
}

async function runFlowViewport(browserInstance, flow, viewport) {
    const context = await browserInstance.createBrowserContext();
    const page = await context.newPage();
    const pageFailures = [];

    page.on('console', (message) => {
        if (message.type() === 'error') {
            pageFailures.push(`console error: ${message.text()}`);
        }
    });
    page.on('pageerror', (error) =>
        pageFailures.push(`page error: ${error.message}`),
    );
    page.on('requestfailed', (request) => {
        if (!request.failure()?.errorText?.includes('ERR_ABORTED')) {
            pageFailures.push(
                `request failed: ${request.method()} ${request.url()}`,
            );
        }
    });
    page.on('response', (response) => {
        if (
            new URL(response.url()).origin === baseUrl.origin &&
            response.status() >= 400
        ) {
            pageFailures.push(
                `HTTP ${response.status()}: ${response.request().method()} ${response.url()}`,
            );
        }
    });

    try {
        await page.setViewport(viewport);
        await installFakeMedia(page);
        await login(page, flow.account, viewport.name);

        const response = await page.goto(absoluteUrl(flow.path), {
            waitUntil: 'networkidle2',
        });

        if (!response || response.status() >= 400) {
            throw new Error(
                `${flow.name} returned HTTP ${response?.status() ?? 'no response'} on ${viewport.name}.`,
            );
        }

        await page.waitForSelector('main, [role="main"]', {
            timeout: 10_000,
        });
        await assertExpectedContent(page, flow, viewport.name);
        await assertAccessibility(page, flow, viewport.name);
        await assertKeyboardFocus(page, flow, viewport.name);

        if (flow.name === 'Client Screen') {
            await assertFakeWebRtcContract(page, viewport.name);
        }

        await assertApprovedScreenshot(page, flow, viewport.name);
    } catch (error) {
        failures.push(
            `${flow.name} (${viewport.name}): ${error instanceof Error ? error.message : String(error)}`,
        );
    } finally {
        for (const failure of pageFailures) {
            failures.push(`${flow.name} (${viewport.name}): ${failure}`);
        }

        await context.close();
    }
}

async function login(page, account, viewport) {
    const response = await page.goto(absoluteUrl('/login'), {
        waitUntil: 'networkidle2',
    });

    if (!response || response.status() >= 400) {
        throw new Error(
            `Login returned HTTP ${response?.status() ?? 'no response'}.`,
        );
    }

    await assertAccessibility(page, { name: 'Login' }, viewport);
    await assertKeyboardFocus(page, { name: 'Login' }, viewport);
    await page.locator('input[name="email"]').fill(account.email);
    await page.locator('input[name="password"]').fill(account.password);
    await page.locator('button[type="submit"]').click();
    await page.waitForFunction(
        () => !window.location.pathname.includes('/login'),
        { timeout: 15_000 },
    );

    if (isMfaChallengePath(new URL(page.url()).pathname)) {
        await assertAccessibility(page, { name: 'MFA challenge' }, viewport);
        await assertKeyboardFocus(page, { name: 'MFA challenge' }, viewport);
        await page
            .locator('input[name="code"]')
            .fill(generateSync({ secret: account.mfaSecret }));
        await page.locator('button[type="submit"]').click();
        await page.waitForFunction(
            () => {
                const path = window.location.pathname;

                return ![
                    '/login',
                    '/mfa/challenge',
                    '/two-factor-challenge',
                ].some((challengePath) => path.includes(challengePath));
            },
            { timeout: 15_000 },
        );
    }
}

function isMfaChallengePath(pathname) {
    return ['/mfa/challenge', '/two-factor-challenge'].some((challengePath) =>
        pathname.includes(challengePath),
    );
}

async function installFakeMedia(page) {
    await page.evaluateOnNewDocument(() => {
        const fakeStream = () => new MediaStream();
        const mediaDevices = navigator.mediaDevices ?? {};

        class FakePeerConnection extends EventTarget {
            connectionState = 'connected';

            iceConnectionState = 'connected';

            localDescription = null;

            remoteDescription = null;

            async createOffer() {
                return { type: 'offer', sdp: 'v=0\r\n' };
            }

            async createAnswer() {
                return { type: 'answer', sdp: 'v=0\r\n' };
            }

            async setLocalDescription(description) {
                this.localDescription = description;
            }

            async setRemoteDescription(description) {
                this.remoteDescription = description;
            }

            async addIceCandidate() {}

            addTrack() {}

            getSenders() {
                return [];
            }

            close() {
                this.connectionState = 'closed';
            }
        }

        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: {
                ...mediaDevices,
                getUserMedia: async () => fakeStream(),
                getDisplayMedia: async () => fakeStream(),
            },
        });
        Object.defineProperty(window, 'RTCPeerConnection', {
            configurable: true,
            value: FakePeerConnection,
        });
    });
}

async function assertExpectedContent(page, flow, viewport) {
    const bodyText = await page.evaluate(() => document.body.innerText);

    if (!bodyText.includes(flow.expected)) {
        throw new Error(
            `${flow.name} did not render expected marker "${flow.expected}" on ${viewport}.`,
        );
    }
}

async function assertAccessibility(page, flow, viewport) {
    await page.addScriptTag({ content: axe.source });
    const result = await page.evaluate(async () => {
        const axeResult = await globalThis.axe.run(document, {
            resultTypes: ['violations'],
        });

        return {
            violations: axeResult.violations.map((violation) => ({
                id: violation.id,
                nodes: violation.nodes.length,
            })),
            missingAlt: Array.from(document.images)
                .filter((image) => !image.hasAttribute('alt'))
                .map((image) => image.currentSrc || image.src),
            horizontalOverflow:
                document.documentElement.scrollWidth > window.innerWidth + 1,
        };
    });

    if (result.violations.length > 0) {
        throw new Error(
            `${flow.name} has axe violations on ${viewport}: ${result.violations.map((item) => `${item.id} (${item.nodes})`).join(', ')}.`,
        );
    }

    if (result.missingAlt.length > 0) {
        throw new Error(
            `${flow.name} has images without alt text on ${viewport}.`,
        );
    }

    if (result.horizontalOverflow) {
        throw new Error(`${flow.name} has horizontal overflow on ${viewport}.`);
    }
}

async function assertKeyboardFocus(page, flow, viewport) {
    await page.keyboard.press('Tab');
    const focused = await page.evaluate(() => {
        const element = document.activeElement;

        return (
            element instanceof HTMLElement &&
            element !== document.body &&
            element.tabIndex >= 0
        );
    });

    if (!focused) {
        throw new Error(
            `${flow.name} has no reachable keyboard focus target on ${viewport}.`,
        );
    }
}

async function assertFakeWebRtcContract(page, viewport) {
    const result = await page.evaluate(async () => {
        const stream = await navigator.mediaDevices.getDisplayMedia({
            video: true,
        });
        const peer = new RTCPeerConnection();
        const offer = await peer.createOffer();
        await peer.setLocalDescription(offer);
        const answer = await peer.createAnswer();
        await peer.setRemoteDescription(answer);
        peer.close();

        return {
            hasStream: stream instanceof MediaStream,
            offerType: offer.type,
            answerType: answer.type,
            localType: peer.localDescription?.type ?? null,
            remoteType: peer.remoteDescription?.type ?? null,
        };
    });

    if (
        !result.hasStream ||
        result.offerType !== 'offer' ||
        result.answerType !== 'answer' ||
        result.localType !== 'offer' ||
        result.remoteType !== 'answer'
    ) {
        throw new Error(
            `Fake WebRTC media/signalling contract failed on ${viewport}.`,
        );
    }
}

async function assertApprovedScreenshot(page, flow, viewport) {
    const snapshotName = `${safeName(flow.name)}-${viewport}.png`;
    const expectedPath = join(screenshotRoot, snapshotName);
    const actualPath = join(
        artifactRoot,
        `${safeName(flow.name)}-${viewport}.actual.png`,
    );
    const actual = await page.screenshot({ fullPage: true });
    writeFileSync(actualPath, actual);

    if (!existsSync(expectedPath)) {
        throw new Error(
            `Missing approved screenshot ${expectedPath}. Review the artifact and commit an approved baseline.`,
        );
    }

    const expected = PNG.sync.read(readFileSync(expectedPath));
    const received = PNG.sync.read(actual);

    if (
        expected.width !== received.width ||
        expected.height !== received.height
    ) {
        throw new Error(`Screenshot dimensions changed for ${snapshotName}.`);
    }

    const diff = new PNG({ width: expected.width, height: expected.height });
    const changedPixels = pixelmatch(
        expected.data,
        received.data,
        diff.data,
        expected.width,
        expected.height,
        { threshold: 0.1 },
    );

    if (changedPixels > 0) {
        writeFileSync(
            join(artifactRoot, `${safeName(flow.name)}-${viewport}.diff.png`),
            PNG.sync.write(diff),
        );

        throw new Error(
            `Unapproved screenshot change for ${snapshotName}: ${changedPixels} pixels differ.`,
        );
    }
}

function absoluteUrl(path) {
    return new URL(path, baseUrl).toString();
}

function safeName(value) {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
}
