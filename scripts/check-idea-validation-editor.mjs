import assert from 'node:assert/strict';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import puppeteer from 'puppeteer';
import { createServer } from 'vite';

// Exercise real layout and input, using the production editor and autosave hook.
// The local draft endpoint keeps this check independent of accounts/databases.
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const server = await createServer({
    configFile: false,
    appType: 'custom',
    root,
    plugins: [react(), tailwindcss()],
    resolve: { alias: { '@': resolve(root, 'resources/js') } },
    server: { host: '127.0.0.1', port: 0, watch: null },
    logLevel: 'error',
});
let savedProblem = '';
// Keep the first save in flight until scrolling and the baseline are complete.
const firstSaveGate = Promise.withResolvers();

server.middlewares.use(async (request, response, next) => {
    if (request.url === '/draft') {
        if (request.method === 'POST') {
            let body = '';

            for await (const chunk of request) {
                body += chunk;
            }

            savedProblem = JSON.parse(body).payload.problem;
            await firstSaveGate.promise;
        }

        response.setHeader('Content-Type', 'application/json');
        response.end(
            JSON.stringify({
                payload: { problem: savedProblem },
                saved_at: new Date().toISOString(),
            }),
        );

        return;
    }

    if (request.url === '/') {
        const html = await server.transformIndexHtml(
            '/',
            '<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Editor check</title></head><body><div id="root"></div><script type="module" src="/scripts/fixtures/idea-validation-editor.tsx"></script></body></html>',
        );

        response.setHeader('Content-Type', 'text/html');
        response.end(html);

        return;
    }

    next();
});

let browser;

try {
    await server.listen();
    browser = await puppeteer.launch({
        headless: true,
        pipe: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });
    const page = await browser.newPage();
    const errors = [];
    const fixtureOrigin = new URL(server.resolvedUrls.local[0]).origin;

    // Remote font timing must not change layout during this interaction check.
    await page.setRequestInterception(true);
    page.on('request', (request) => {
        if (new URL(request.url()).origin === fixtureOrigin) {
            void request.continue();
        } else {
            void request.abort();
        }
    });

    page.on('pageerror', (error) => {
        errors.push(error.message);
        console.error(error.message);
    });
    await page.setViewport({ width: 1100, height: 900 });
    await page.goto(server.resolvedUrls.local[0]);
    await page.waitForSelector('#problem');
    await page.waitForFunction(
        () => document.querySelector('#draft-state').textContent === 'saved',
    );
    await page.evaluate(() => document.fonts.ready);

    const metrics = () =>
        page.$eval('#problem', (textarea) => ({
            height: textarea.getBoundingClientRect().height,
            clientHeight: textarea.clientHeight,
            scrollHeight: textarea.scrollHeight,
            scrollTop: textarea.scrollTop,
            selectionStart: textarea.selectionStart,
            value: textarea.value,
            lineHeight: parseFloat(getComputedStyle(textarea).lineHeight),
        }));
    const initial = await metrics();
    const longAnswer = Array.from(
        { length: 70 },
        (_, index) => `Paragraph ${index + 1}: A customer problem to validate.`,
    ).join('\n');
    const waitForSave = (answer) =>
        page.waitForResponse(
            (response) =>
                response.url().endsWith('/draft') &&
                response.request().method() === 'POST' &&
                JSON.parse(response.request().postData()).payload.problem ===
                    answer,
        );
    await page.focus('#problem');
    await page.keyboard.sendCharacter(longAnswer);
    const filled = await metrics();

    assert.ok(
        filled.height > initial.height,
        'The field grows for long answers',
    );
    assert.ok(
        filled.height <= initial.height + 6 * filled.lineHeight + 1,
        'Automatic growth stops at twelve lines',
    );
    assert.ok(
        filled.scrollHeight > filled.clientHeight,
        'Long answers overflow',
    );
    await page.waitForFunction(
        () => document.querySelector('#draft-state').textContent === 'saving',
    );
    await page.keyboard.down('Control');
    await page.keyboard.press('Home');
    await page.keyboard.up('Control');
    const atStart = await metrics();

    assert.equal(
        atStart.selectionStart,
        0,
        'Keyboard can reach the first character',
    );
    assert.ok(
        atStart.scrollTop <= atStart.lineHeight,
        'The first line is visible',
    );

    await page.hover('#problem');
    await page.$eval('#problem', (textarea) => {
        const start = textarea.scrollTop;
        textarea.dataset.wheelScrollEnded = 'false';
        const onScrollEnd = () => {
            if (textarea.scrollTop >= start + 199) {
                textarea.dataset.wheelScrollEnded = 'true';
                textarea.removeEventListener('scrollend', onScrollEnd);
            }
        };

        textarea.addEventListener('scrollend', onScrollEnd);
    });
    await page.mouse.wheel({ deltaY: 200 });
    // Reaching the target offset can precede the final compositor adjustment.
    await page.waitForFunction(
        () =>
            document.querySelector('#problem').dataset.wheelScrollEnded ===
            'true',
    );
    const beforeSave = await metrics();
    const firstSave = waitForSave(longAnswer);

    firstSaveGate.resolve();
    await firstSave;
    await page.waitForFunction(
        () => document.querySelector('#draft-state').textContent === 'saved',
    );
    const afterSave = await metrics();

    assert.equal(
        savedProblem,
        longAnswer,
        'Autosave receives the entire answer',
    );
    assert.equal(
        afterSave.scrollTop,
        beforeSave.scrollTop,
        'Autosave preserves scroll',
    );
    assert.equal(
        afterSave.selectionStart,
        beforeSave.selectionStart,
        'Autosave preserves the caret',
    );

    await page.focus('[aria-controls="problem"]');
    await page.keyboard.press('Enter');
    const expanded = await metrics();

    assert.ok(
        expanded.height > filled.height,
        'Expand provides a larger editor',
    );
    assert.equal(expanded.value, longAnswer, 'Expand preserves the answer');
    assert.equal(
        expanded.selectionStart,
        beforeSave.selectionStart,
        'Expand preserves selection',
    );
    assert.equal(
        await page.$eval(
            '#problem',
            (textarea) => textarea === document.activeElement,
        ),
        true,
    );
    await page.keyboard.down('Control');
    await page.keyboard.press('End');
    await page.keyboard.up('Control');
    await page.keyboard.sendCharacter('\nLast detail');
    await page.click('[aria-controls="problem"]');
    assert.equal(
        (await metrics()).value,
        `${longAnswer}\nLast detail`,
        'Expanded edits survive collapse',
    );
    assert.ok((await metrics()).height < expanded.height);

    // Native drag resizing remains useful and is not undone by the next edit.
    const textarea = await page.$('#problem');
    const box = await textarea.boundingBox();

    await page.mouse.move(box.x + box.width - 4, box.y + box.height - 4);
    await page.mouse.down();
    await page.mouse.move(box.x + box.width - 4, box.y + box.height + 65, {
        steps: 8,
    });
    await page.mouse.up();
    const resized = await metrics();

    assert.ok(resized.height > filled.height, 'The native resize handle works');
    const lastSave = waitForSave(`${longAnswer}\nLast detail!`);

    await page.focus('#problem');
    await page.keyboard.sendCharacter('!');
    assert.equal(
        (await metrics()).height,
        resized.height,
        'Typing keeps the chosen height',
    );
    await lastSave;
    await page.waitForFunction(
        () => document.querySelector('#draft-state').textContent === 'saved',
    );

    await page.setViewport({
        width: 390,
        height: 844,
        isMobile: true,
        hasTouch: true,
    });
    await page.reload();
    await page.waitForFunction(() =>
        document.querySelector('#problem')?.value.includes('Last detail'),
    );
    assert.equal(
        (await metrics()).value,
        savedProblem,
        'Saved answers rehydrate in full',
    );
    assert.equal(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
        true,
        'Mobile layout fits the viewport',
    );
    const mobileBox = await (await page.$('#problem')).boundingBox();
    const client = await page.createCDPSession();
    const x = Math.round(mobileBox.x + mobileBox.width / 2);
    const y = Math.round(mobileBox.y + mobileBox.height - 30);

    await client.send('Input.dispatchTouchEvent', {
        type: 'touchStart',
        touchPoints: [{ x, y }],
    });

    for (let step = 1; step <= 6; step++) {
        await client.send('Input.dispatchTouchEvent', {
            type: 'touchMove',
            touchPoints: [{ x, y: y - step * 20 }],
        });
        await page.evaluate(() => new Promise(requestAnimationFrame));
    }

    await client.send('Input.dispatchTouchEvent', {
        type: 'touchEnd',
        touchPoints: [],
    });
    assert.ok((await metrics()).scrollTop > 0, 'Touch can scroll long answers');
    await page.click('[aria-controls="problem"]');
    assert.ok(
        (await metrics()).height > filled.height,
        'Expansion works on mobile',
    );
    assert.equal(
        await page.$eval('#plan-detail', (element) => element.rows),
        8,
        'The existing plan editor keeps its size',
    );
    assert.equal(
        await page.$('[aria-controls="plan-detail"]'),
        null,
        'Expansion is opt-in',
    );
    assert.deepEqual(errors, [], 'No browser runtime errors');
    console.log(
        'Idea Validation editor: growth, scrolling, expansion, manual resize, autosave, hydration, keyboard and mobile touch checks passed.',
    );
} finally {
    firstSaveGate.resolve();
    await browser?.close();
    await server.close();
}
