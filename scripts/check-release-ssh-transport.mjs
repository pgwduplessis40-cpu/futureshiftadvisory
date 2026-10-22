import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import {
    mkdirSync,
    mkdtempSync,
    readFileSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';
import process from 'node:process';
import { load } from 'js-yaml';

const workflow = load(
    readFileSync('.github/workflows/release-version.yml', 'utf8'),
);
const steps = workflow.jobs['deploy-production'].steps;
const upload = steps.find(
    (step) => step.name === 'Upload deployment Git bundle',
).run;
const deploy = steps.find(
    (step) => step.name === 'Deploy the released commit',
).run;
const bash = process.env.BASH_BINARY || 'bash';
const temporaryRoot = resolve(tmpdir());
const workspace = mkdtempSync(join(temporaryRoot, 'fsa-release-ssh-'));
const shellPath = (path) =>
    process.platform === 'win32'
        ? path
              .replaceAll('\\', '/')
              .replace(/^([A-Za-z]):/, (_, drive) => '/' + drive.toLowerCase())
        : path;

try {
    const stub = join(workspace, 'stub-transport.sh');
    writeFileSync(
        stub,
        [
            'ssh() {',
            '  printf "%s\\n" called >> "$STUB_CALLS"',
            '  printf "%s\\n" "$@" > "$STUB_ARGUMENTS"',
            '  cat > /dev/null',
            '  printf "%s\\n" "Stub deployment output"',
            '  return "$STUB_EXIT"',
            '}',
            'scp() {',
            '  printf "%s\\n" called >> "$STUB_CALLS"',
            '  printf "%s\\n" "$@" > "$STUB_ARGUMENTS"',
            '  return "$STUB_EXIT"',
            '}',
            '',
        ].join('\n'),
    );

    for (const [name, script, expectedExit] of [
        ['upload-success', upload, 0],
        ['upload-disconnect', upload, 255],
        ['deploy-success', deploy, 0],
        ['deploy-command-failure', deploy, 42],
        ['deploy-disconnect', deploy, 255],
    ]) {
        const directory = join(workspace, name);
        mkdirSync(directory);
        const scriptFile = join(directory, 'step.sh');
        const summary = join(directory, 'summary.md');
        const argumentsFile = join(directory, 'arguments.txt');
        writeFileSync(scriptFile, script);
        writeFileSync(summary, '');
        const result = spawnSync(
            bash,
            [
                '--noprofile',
                '--norc',
                '-e',
                '-o',
                'pipefail',
                shellPath(scriptFile),
            ],
            {
                cwd: directory,
                encoding: 'utf8',
                timeout: 15000,
                env: {
                    ...process.env,
                    BASH_ENV: shellPath(stub),
                    RUNNER_TEMP: shellPath(directory),
                    GITHUB_STEP_SUMMARY: shellPath(summary),
                    GITHUB_ENV: shellPath(join(directory, 'environment')),
                    STUB_ARGUMENTS: shellPath(argumentsFile),
                    STUB_CALLS: shellPath(join(directory, 'calls.txt')),
                    STUB_EXIT: String(expectedExit),
                    DEPLOY_PATH: '/unused/site',
                    DEPLOY_COMMIT: '0123456789012345678901234567890123456789',
                    DEPLOY_VERSION: '1.2.3',
                    DEPLOY_REMOTE_BUNDLE: '/unused/release.bundle',
                    DEPLOY_BUNDLE: '/unused/release.bundle',
                    DEPLOY_HOST: 'example.invalid',
                    DEPLOY_USER: 'test-user',
                    DEPLOY_PORT: '2222',
                    EDGE_WATCHDOG_ALERT_WEBHOOK:
                        'https://example.invalid/test-hook',
                },
            },
        );
        assert.ifError(result.error);
        assert.equal(
            result.status,
            expectedExit,
            name + '\n' + result.stdout + result.stderr,
        );
        assert.equal(
            readFileSync(join(directory, 'calls.txt'), 'utf8'),
            'called\n',
            name + ': unexpected transport retry',
        );
        const args = readFileSync(argumentsFile, 'utf8').split('\n');

        for (const option of [
            'BatchMode=yes',
            'StrictHostKeyChecking=yes',
            'ConnectTimeout=30',
            'ServerAliveInterval=30',
            'ServerAliveCountMax=6',
        ]) {
            assert.ok(
                args.some(
                    (argument, index) =>
                        argument === option && args[index - 1] === '-o',
                ),
                name + ': ' + option,
            );
        }

        const report = readFileSync(summary, 'utf8');

        if (name === 'deploy-disconnect') {
            assert.match(report, /remote|deployment completion/);
            assert.match(report, /may still be running/);
            assert.match(report, /No automatic retry/);
            assert.match(result.stdout, /Deployment SSH session interrupted/);
        } else {
            assert.equal(report, '', name + ': misleading disconnect guidance');
        }

        if (name === 'deploy-command-failure') {
            assert.match(result.stdout, /Production deployment failed/);
        }

        process.stdout.write(name + ' passed\n');
    }
} finally {
    assert.ok(resolve(workspace).startsWith(temporaryRoot + sep));
    rmSync(workspace, { recursive: true, force: true });
}
