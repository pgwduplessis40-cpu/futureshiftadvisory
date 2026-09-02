import { Buffer } from 'node:buffer';
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
// Hosted Chrome can alter anti-aliased font-edge pixels between otherwise
// identical runs. One changed pixel per thousand is noise, not a visual
// approval bypass; layout and content changes exceed this bound.
const maxScreenshotDifferenceRatio = 0.001;
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
        expected: process.env.E2E_ONBOARDING_EXPECT ?? 'Advisor dashboard',
    },
    {
        name: 'Interactive Dashboard',
        account: accounts.advisor,
        path: process.env.E2E_ADVISOR_DASHBOARD_PATH ?? '/dashboard',
        expected:
            process.env.E2E_ADVISOR_DASHBOARD_EXPECT ?? 'Advisor dashboard',
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
    {
        name: 'DD Client Information',
        account: accounts.advisor,
        path:
            process.env.E2E_DD_CLIENT_SCREEN_PATH ??
            '/advisor/clients/00000000-0000-4000-8000-000000000003',
        expected:
            process.env.E2E_DD_CLIENT_SCREEN_EXPECT ??
            'Browser E2E Due Diligence Client',
        informationTab: true,
        screenshot: false,
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

    try {
        await runScreenSupportCollaboration(browser);
    } catch (error) {
        failures.push(
            `Client Screen collaboration: ${error instanceof Error ? error.message : String(error)}`,
        );
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

/**
 * Exercise the real advisor/client collaboration controls as two isolated
 * browser users. The deterministic media implementation replaces browser
 * device and peer-transport APIs; registration, consent, signalling, guided
 * assistance, and termination still use the production application routes.
 */
async function runScreenSupportCollaboration(browserInstance) {
    const advisorContext = await browserInstance.createBrowserContext();
    const clientContext = await browserInstance.createBrowserContext();
    const advisorPage = await advisorContext.newPage();
    const clientPage = await clientContext.newPage();
    const collaborationFailures = [];

    for (const [role, page] of [
        ['advisor', advisorPage],
        ['client', clientPage],
    ]) {
        page.on('console', (message) => {
            if (message.type() === 'error') {
                collaborationFailures.push(
                    `${role} console error: ${message.text()}`,
                );
            }
        });
        page.on('pageerror', (error) =>
            collaborationFailures.push(`${role} page error: ${error.message}`),
        );
        page.on('requestfailed', (request) => {
            if (!request.failure()?.errorText?.includes('ERR_ABORTED')) {
                collaborationFailures.push(
                    `${role} request failed: ${request.method()} ${request.url()}`,
                );
            }
        });
        page.on('response', (response) => {
            if (
                new URL(response.url()).origin === baseUrl.origin &&
                response.status() >= 400
            ) {
                collaborationFailures.push(
                    `${role} HTTP ${response.status()}: ${response.request().method()} ${response.url()}`,
                );
            }
        });
    }

    try {
        await Promise.all([
            advisorPage.setViewport({ width: 1440, height: 1000 }),
            clientPage.setViewport({ width: 1440, height: 1000 }),
            installFakeMedia(advisorPage),
            installFakeMedia(clientPage),
        ]);
        await Promise.all([
            login(advisorPage, accounts.advisor, 'desktop collaboration'),
            login(clientPage, accounts.client, 'desktop collaboration'),
        ]);
        await clientPage.goto(
            absoluteUrl(process.env.E2E_CLIENT_PORTAL_PATH ?? '/portal'),
            { waitUntil: 'networkidle2' },
        );
        await clientPage.waitForFunction(
            () => {
                const resources = performance.getEntriesByType('resource');

                return [
                    '/screen-share/connections',
                    '/co-browse/connections',
                ].every((path) =>
                    resources.some((entry) => entry.name.includes(path)),
                );
            },
            { timeout: 10_000 },
        );
        await advisorPage.goto(
            absoluteUrl(process.env.E2E_CLIENT_SCREEN_PATH),
            {
                waitUntil: 'networkidle2',
            },
        );

        await clickButton(advisorPage, 'Screen support');
        await clickButton(advisorPage, 'Request view');

        await waitForBodyText(clientPage, 'Screen support request');
        await clickButton(clientPage, 'Continue');

        await advisorPage.waitForFunction(
            () => document.body.innerText.includes('View only. Live for'),
            { timeout: 15_000 },
        );
        await clientPage.waitForFunction(
            () => document.body.innerText.includes('Screen sharing with'),
            { timeout: 15_000 },
        );

        await clickButton(advisorPage, 'Request guidance approval');
        await waitForBodyText(clientPage, 'Allow guided assistance?');
        await clickButton(clientPage, 'Allow assistance');
        await advisorPage.waitForFunction(
            () =>
                document.body.innerText.includes('Guided assistance is active'),
            { timeout: 10_000 },
        );

        await clickButton(advisorPage, 'End guidance');
        await clientPage.waitForFunction(
            () => !document.body.innerText.includes('Stop assistance'),
            { timeout: 10_000 },
        );
        await clickButton(advisorPage, 'End');
        await clientPage.waitForFunction(
            () => !document.body.innerText.includes('Screen sharing with'),
            { timeout: 10_000 },
        );
    } catch (error) {
        for (const [role, page] of [
            ['advisor', advisorPage],
            ['client', clientPage],
        ]) {
            try {
                await page.screenshot({
                    path: join(
                        artifactRoot,
                        `client-screen-collaboration-${role}.failure.png`,
                    ),
                    fullPage: true,
                });
            } catch {
                // Preserve the collaboration failure if Chrome cannot capture it.
            }
        }

        collaborationFailures.push(
            error instanceof Error ? error.message : String(error),
        );
    } finally {
        await Promise.all([advisorContext.close(), clientContext.close()]);
    }

    if (collaborationFailures.length > 0) {
        throw new Error(
            `Client Screen collaboration failed: ${collaborationFailures.join('; ')}`,
        );
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

        if (flow.informationTab) {
            await assertClientInformationTab(page, flow, viewport.name);
            await assertAccessibility(page, flow, viewport.name);
            await assertKeyboardFocus(page, flow, viewport.name);
        }

        if (flow.screenshot !== false) {
            await settleVisualCapture(
                page,
                viewport.isMobile && usesPwaInstallFallback(flow),
            );
            await assertApprovedScreenshot(page, flow, viewport.name);
        }
    } catch (error) {
        try {
            await page.screenshot({
                path: join(
                    artifactRoot,
                    `${safeName(flow.name)}-${viewport.name}.failure.png`,
                ),
                fullPage: true,
            });
        } catch {
            // Preserve the original browser failure if Chrome cannot capture it.
        }

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

    try {
        await page.waitForFunction(
            () => !window.location.pathname.includes('/login'),
            { timeout: 15_000 },
        );
    } catch (error) {
        const visibleText = await page.evaluate(() =>
            document.body.innerText.replace(/\s+/g, ' ').trim().slice(0, 500),
        );

        throw new Error(
            `Login did not advance from ${new URL(page.url()).pathname}: ${visibleText}`,
            { cause: error },
        );
    }

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

async function waitForBodyText(page, text, timeout = 10_000) {
    await page.waitForFunction(
        (expected) => document.body.innerText.includes(expected),
        { timeout },
        text,
    );
}

async function clickButton(page, label, timeout = 10_000) {
    await page.waitForFunction(
        (expected) =>
            Array.from(document.querySelectorAll('button')).some(
                (button) =>
                    button.textContent?.trim() === expected && !button.disabled,
            ),
        { timeout },
        label,
    );
    const clicked = await page.evaluate((expected) => {
        const button = Array.from(document.querySelectorAll('button')).find(
            (candidate) =>
                candidate.textContent?.trim() === expected &&
                !candidate.disabled,
        );

        if (!(button instanceof HTMLButtonElement)) {
            return false;
        }

        button.click();

        return true;
    }, label);

    if (!clicked) {
        throw new Error(`Could not click the ${label} button.`);
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
            connectionState = 'new';

            iceConnectionState = 'connected';

            localDescription = null;

            remoteDescription = null;

            onconnectionstatechange = null;

            async createOffer() {
                return { type: 'offer', sdp: 'v=0\r\n' };
            }

            async createAnswer() {
                return { type: 'answer', sdp: 'v=0\r\n' };
            }

            async setLocalDescription(description) {
                this.localDescription = description;
                this.markConnected();
            }

            async setRemoteDescription(description) {
                this.remoteDescription = description;
                this.markConnected();
            }

            async addIceCandidate() {}

            addTrack() {}

            getSenders() {
                return [];
            }

            close() {
                this.connectionState = 'closed';
            }

            markConnected() {
                if (
                    this.connectionState === 'new' &&
                    this.localDescription !== null &&
                    this.remoteDescription !== null
                ) {
                    this.connectionState = 'connected';
                    const event = new Event('connectionstatechange');
                    this.onconnectionstatechange?.(event);
                    this.dispatchEvent(event);
                }
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
    try {
        await page.waitForFunction(
            (expected) => document.body.innerText.includes(expected),
            { timeout: 10_000 },
            flow.expected,
        );
    } catch {
        const pageIdentity = await page.evaluate(() => {
            const serializedPage = document
                .querySelector('[data-page]')
                ?.getAttribute('data-page');
            let component = null;

            try {
                component = serializedPage
                    ? JSON.parse(serializedPage).component
                    : null;
            } catch {
                component = null;
            }

            return {
                pathname: window.location.pathname,
                component,
                title: document.title,
            };
        });

        throw new Error(
            `${flow.name} did not render expected marker "${flow.expected}" on ${viewport} at ${pageIdentity.pathname} (${pageIdentity.component ?? pageIdentity.title}).`,
        );
    }
}

async function assertClientInformationTab(page, flow, viewport) {
    const tabList = await page.waitForSelector(
        '[role="tablist"][aria-label="Client detail sections"]',
        { timeout: 10_000 },
    );
    const tabs = await tabList.$$('[role="tab"]');
    const informationTab = (
        await Promise.all(
            tabs.map(async (tab) =>
                (await tab.evaluate((element) => element.textContent?.trim())) ===
                'Information'
                    ? tab
                    : null,
            ),
        )
    ).find((tab) => tab !== null);

    if (informationTab === undefined) {
        throw new Error('Client detail navigation did not render an Information tab.');
    }

    await informationTab.click();
    await page.waitForFunction(
        () =>
            document.body.innerText.includes('Client information') ||
            document.body.innerText.includes('We could not load this page'),
        { timeout: 10_000 },
    );

    const result = await page.evaluate(() => ({
        hasErrorBoundary: document.body.innerText.includes(
            'We could not load this page',
        ),
        engagementVisible: Boolean(document.querySelector('#section-engagement')),
    }));

    if (result.hasErrorBoundary) {
        throw new Error(
            `${flow.name} rendered the application error boundary after selecting Information on ${viewport}.`,
        );
    }

    if (!result.engagementVisible) {
        throw new Error(
            `${flow.name} did not render its engagement section after selecting Information on ${viewport}.`,
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
                targets: violation.nodes.slice(0, 3).map((node) => {
                    const target = node.target.join(' ');
                    const summary = node.failureSummary
                        ?.replaceAll(/\s+/g, ' ')
                        .trim();

                    return summary ? `${target}: ${summary}` : target;
                }),
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
            `${flow.name} has axe violations on ${viewport}: ${result.violations.map((item) => `${item.id} (${item.targets.join('; ')})`).join(', ')}.`,
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

        if (
            !(
                element instanceof HTMLElement &&
                element !== document.body &&
                element.tabIndex >= 0
            )
        ) {
            return { reachable: false, visible: false };
        }

        const focusScope = [element];
        let ancestor = element.parentElement;

        while (ancestor instanceof HTMLElement) {
            focusScope.push(ancestor);
            ancestor = ancestor.parentElement;
        }

        const hasVisibleFocusStyle = (candidate) => {
            const style = window.getComputedStyle(candidate);
            const outlineWidth = Number.parseFloat(style.outlineWidth || '0');
            const hasOutline =
                outlineWidth > 0 &&
                style.outlineStyle !== 'none' &&
                style.outlineColor !== 'transparent' &&
                style.outlineColor !== 'rgba(0, 0, 0, 0)';
            const shadowLayers =
                style.boxShadow.match(/(?:[^,(]|\([^)]*\))+/g) ?? [];
            const hasShadow = shadowLayers.some((layer) => {
                const rgba = layer.match(/rgba?\(([^)]+)\)/i);

                if (rgba === null) {
                    return !layer.includes('transparent');
                }

                const channels = rgba[1]
                    .split(',')
                    .map((channel) => channel.trim());

                return (
                    channels.length < 4 || Number.parseFloat(channels[3]) > 0
                );
            });

            return hasOutline || hasShadow;
        };

        return {
            reachable: true,
            visible:
                element.matches(':focus-visible') &&
                focusScope.some(
                    (candidate) =>
                        candidate.matches(':focus-within') &&
                        hasVisibleFocusStyle(candidate),
                ),
        };
    });

    if (!focused.reachable) {
        throw new Error(
            `${flow.name} has no reachable keyboard focus target on ${viewport}.`,
        );
    }

    if (!focused.visible) {
        throw new Error(
            `${flow.name} has no visible keyboard focus indicator on ${viewport}.`,
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
    // Puppeteer returns a Uint8Array here. pngjs requires a Node Buffer; using
    // it directly makes the visual gate fail before comparing any screenshot.
    const actual = Buffer.from(await page.screenshot({ fullPage: true }));
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
        {
            // The hosted Chrome runners can vary at anti-aliased font edges
            // even with the same DOM and browser revision. Keep layout and
            // colour changes strict while excluding that rasterisation noise.
            threshold: 0.2,
        },
    );

    const allowedChangedPixels = Math.ceil(
        expected.width * expected.height * maxScreenshotDifferenceRatio,
    );

    if (changedPixels > allowedChangedPixels) {
        writeFileSync(
            join(artifactRoot, `${safeName(flow.name)}-${viewport}.diff.png`),
            PNG.sync.write(diff),
        );

        throw new Error(
            `Unapproved screenshot change for ${snapshotName}: ${changedPixels} pixels differ (allowed visual-noise budget: ${allowedChangedPixels}).`,
        );
    }
}

function usesPwaInstallFallback(flow) {
    return (
        flow.name === 'Login and onboarding' ||
        flow.name === 'Interactive Dashboard'
    );
}

async function settleVisualCapture(page, waitForPwaInstallFallback) {
    await page.evaluate(async (shouldWaitForPwaInstallFallback) => {
        await document.fonts?.ready;
        await new Promise((resolve) => requestAnimationFrame(resolve));
        await new Promise((resolve) => requestAnimationFrame(resolve));

        if (shouldWaitForPwaInstallFallback) {
            // The approved mobile dashboard baselines include the PWA
            // browser-help fallback, which intentionally appears after 1.5s.
            // Wait only for the affected flows; other mobile pages must retain
            // their normal capture timing.
            await new Promise((resolve) => window.setTimeout(resolve, 1600));
        }
    }, waitForPwaInstallFallback);
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
