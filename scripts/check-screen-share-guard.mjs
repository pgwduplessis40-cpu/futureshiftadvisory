import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const roots = [
    'resources/js/components/screen-share',
    'resources/js/lib/screen-share.ts',
    'app/Http/Controllers/ScreenShare',
    'app/Services/ScreenShare',
];
const forbidden = [
    'createDataChannel',
    'MediaRecorder',
    'canvas.captureStream',
    'drawImage',
    'robotjs',
    'dispatchEvent(new KeyboardEvent',
    'dispatchEvent(new MouseEvent',
];
const files = roots.flatMap(filesFor);
const violations = [];

for (const file of files) {
    const source = readFileSync(file, 'utf8');
    for (const term of forbidden) {
        if (source.includes(term)) {
            violations.push(file + ': forbidden screen-support capability "' + term + '"');
        }
    }
}

if (violations.length > 0) {
    console.error(violations.join('\n'));
    process.exit(1);
}

function filesFor(path) {
    const entry = statSync(path);
    if (entry.isFile()) {
        return [path];
    }

    return readdirSync(path, { withFileTypes: true }).flatMap((child) => (
        child.isDirectory()
            ? filesFor(join(path, child.name))
            : [join(path, child.name)]
    ));
}
