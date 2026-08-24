/* global process */

import puppeteer from 'puppeteer';

const required = [
    'E2E_BASE_URL',
    'E2E_ADVISOR_EMAIL',
    'E2E_ADVISOR_PASSWORD',
    'E2E_CLIENT_EMAIL',
    'E2E_CLIENT_PASSWORD',
    'E2E_CLIENT_SCREEN_PATH',
];

const missing = required.filter((name) => !process.env[name]?.trim());

if (missing.length > 0) {
    throw new Error(
        `browser:smoke requires ${missing.join(', ')}. Use isolated, MFA-capable E2E accounts and never commit credentials.`,
    );
}

const baseUrl = new URL(process.env.E2E_BASE_URL);
const failures = [];
const browser = await puppeteer.launch({ headless: true });

try {
    const advisor = {
        email: process.env.E2E_ADVISOR_EMAIL,
        password: process.env.E2E_ADVISOR_PASSWORD,
    };
    const client = {
        email: process.env.E2E_CLIENT_EMAIL,
        password: process.env.E2E_CLIENT_PASSWORD,
    };

    await runFlow(browser, advisor, {
        name: 'Interactive Dashboard',
        path: process.env.E2E_ADVISOR_DASHBOARD_PATH ?? '/dashboard',
        expected: process.env.E2E_ADVISOR_DASHBOARD_EXPECT,
    });
    await runFlow(browser, client, {
        name: 'Client Dashboard',
        path: process.env.E2E_CLIENT_DASHBOARD_PATH ?? '/portal',
        expected: process.env.E2E_CLIENT_DASHBOARD_EXPECT,
    });
    await runFlow(browser, client, {
        name: 'NPO Module',
        path: process.env.E2E_NPO_PATH ?? '/portal/npo-board',
        expected: process.env.E2E_NPO_EXPECT,
    });
    await runFlow(browser, client, {
        name: 'Budget and Runway Builder',
        path: process.env.E2E_BUDGET_PATH ?? '/portal/business-plan-budget',
        expected: process.env.E2E_BUDGET_EXPECT,
    });
    await runFlow(browser, advisor, {
        name: 'Client Screen',
        path: process.env.E2E_CLIENT_SCREEN_PATH,
        expected: process.env.E2E_CLIENT_SCREEN_EXPECT,
    });
} finally {
    await browser.close();
}

if (failures.length > 0) {
    throw new Error(
        `Browser smoke failures:\n${failures.map((failure) => `- ${failure}`).join('\n')}`,
    );
}

async function runFlow(browserInstance, credentials, flow) {
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

    try {
        await login(page, credentials);

        for (const viewport of [
            { name: 'desktop', width: 1440, height: 1000 },
            { name: 'mobile', width: 390, height: 844, isMobile: true },
        ]) {
            await page.setViewport(viewport);
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

            if (flow.expected) {
                const bodyText = await page.evaluate(
                    () => document.body.innerText,
                );

                if (!bodyText.includes(flow.expected)) {
                    throw new Error(
                        `${flow.name} did not render its expected marker on ${viewport.name}.`,
                    );
                }
            }

            const accessibility = await page.evaluate(() => ({
                missingAlt: Array.from(document.images)
                    .filter((image) => !image.hasAttribute('alt'))
                    .map((image) => image.currentSrc || image.src),
                horizontalOverflow:
                    document.documentElement.scrollWidth >
                    window.innerWidth + 1,
            }));

            if (accessibility.missingAlt.length > 0) {
                throw new Error(
                    `${flow.name} has images without alt attributes on ${viewport.name}.`,
                );
            }

            if (accessibility.horizontalOverflow) {
                throw new Error(
                    `${flow.name} has horizontal overflow on ${viewport.name}.`,
                );
            }
        }
    } catch (error) {
        failures.push(
            `${flow.name}: ${error instanceof Error ? error.message : String(error)}`,
        );
    } finally {
        for (const failure of pageFailures) {
            failures.push(`${flow.name}: ${failure}`);
        }

        await context.close();
    }
}

async function login(page, credentials) {
    const response = await page.goto(absoluteUrl('/login'), {
        waitUntil: 'networkidle2',
    });

    if (!response || response.status() >= 400) {
        throw new Error(
            `Login returned HTTP ${response?.status() ?? 'no response'}.`,
        );
    }

    await page.waitForSelector('input[name="email"]', { timeout: 10_000 });
    await page.locator('input[name="email"]').fill(credentials.email);
    await page.locator('input[name="password"]').fill(credentials.password);
    await page.locator('button[type="submit"]').click();
    await page.waitForFunction(
        () => !window.location.pathname.includes('/login'),
        {
            timeout: 15_000,
        },
    );
}

function absoluteUrl(path) {
    return new URL(path, baseUrl).toString();
}
