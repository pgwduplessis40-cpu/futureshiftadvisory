import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const roots = [
    'resources/js/components/co-browse',
    'resources/js/lib/co-browse.ts',
    'app/Http/Controllers/CoBrowse',
    'app/Services/CoBrowse',
];
const forbidden = [
    'createDataChannel',
    'getDisplayMedia',
    'getUserMedia',
    'MediaRecorder',
    'canvas.captureStream',
    'drawImage',
    'robotjs',
    'dispatchEvent(new KeyboardEvent',
    'dispatchEvent(new MouseEvent',
    'KeyboardEvent(',
    'MouseEvent(',
];
const violations = [];

for (const file of roots.flatMap(filesFor)) {
    const source = readFileSync(file, 'utf8');
    for (const term of forbidden) {
        if (source.includes(term)) {
            violations.push(file + ': forbidden co-browsing capability "' + term + '"');
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
