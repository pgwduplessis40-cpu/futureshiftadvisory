import { spawn } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const bundle = resolve(projectRoot, 'bootstrap/ssr/app.js');
const server = spawn(process.execPath, [bundle], {
    cwd: projectRoot,
    env: process.env,
    stdio: ['ignore', 'pipe', 'pipe'],
});

let output = '';

for (const stream of [server.stdout, server.stderr]) {
    stream.on('data', (chunk) => {
        output += chunk.toString();
    });
}

try {
    await waitForHealthyServer();
    console.log(
        'Production Inertia SSR bundle started and passed its health check.',
    );
} finally {
    stopServer();
}

async function waitForHealthyServer() {
    const deadline = Date.now() + 15_000;

    while (Date.now() < deadline) {
        if (server.exitCode !== null) {
            throw new Error(
                `Production SSR bundle exited with code ${server.exitCode}.\n${output}`,
            );
        }

        try {
            const response = await fetch('http://127.0.0.1:13714/health', {
                signal: AbortSignal.timeout(1_000),
            });

            if (response.ok) {
                return;
            }
        } catch {
            // The bundle is still starting; retry until the deadline.
        }

        await wait(250);
    }

    throw new Error(
        `Production SSR bundle did not become healthy within 15 seconds.\n${output}`,
    );
}

function stopServer() {
    if (server.exitCode !== null) {
        return;
    }

    server.kill('SIGTERM');
}

function wait(milliseconds) {
    return new Promise((resolveWait) => setTimeout(resolveWait, milliseconds));
}
