import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import process from 'node:process';

// Type generation is deliberately an explicit source-change operation
// (`npm run wayfinder:generate`). A production asset build must be
// reproducible and must never mutate tracked route/action helpers.
const require = createRequire(import.meta.url);
const viteEntrypoint = join(
    dirname(dirname(dirname(require.resolve('vite')))),
    'bin',
    'vite.js',
);
const result = spawnSync(process.execPath, [viteEntrypoint, 'build', ...process.argv.slice(2)], {
    env: {
        ...process.env,
        WAYFINDER_GENERATE: 'false',
    },
    stdio: 'inherit',
});

if (result.error) {
    throw result.error;
}

process.exit(result.status ?? 1);
