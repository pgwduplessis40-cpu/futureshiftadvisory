import { readdir, readFile, writeFile } from 'node:fs/promises';

const generatedRoots = ['resources/js/actions', 'resources/js/routes'];
const maxNormalizePasses = 5;

const stringReplacements = [
    [' ,', ','],
    ['[ ', '['],
    [', }', ' }'],
    ['} )', '})'],
    [' )', ')'],
    ['( ', '('],
    ['\n +', ' +'],
    ['})\n/**', '})\n\n/**'],
    ['}\n/**', '}\n\n/**'],
];

const regexReplacements = [
    [/=> \{\n{2,}/g, '=> {\n'],
    [/\s+\.replace/g, `\n${' '.repeat(12)}.replace`],
    [/\s+\+ queryParams\(options\)/g, ' + queryParams(options)'],
    [/(\n[^\n]+\.form = [^\n]+)\n(?=\/\*\*|const\s)/g, '$1\n\n'],
    [/^((?:import [^\n]+\n)+)(?=const\s)/gm, '$1\n'],
    [/\n{3,}/g, '\n\n'],
];

async function tsFilesIn(path) {
    const entries = await readdir(path, { withFileTypes: true });
    const files = await Promise.all(
        entries.map((entry) => {
            const childPath = `${path}/${entry.name}`;

            if (entry.isDirectory()) {
                return tsFilesIn(childPath);
            }

            return entry.isFile() && entry.name.endsWith('.ts')
                ? [childPath]
                : [];
        }),
    );

    return files.flat();
}

const generatedFiles = (
    await Promise.all(generatedRoots.map(tsFilesIn))
).flat();

function collapseMatchedWhitespace(source, patterns) {
    let cleaned = source;

    for (const pattern of patterns) {
        cleaned = cleaned.replace(pattern, (match) =>
            match.replace(/\s+/g, ' '),
        );
    }

    return cleaned;
}

function normalizeWayfinderTypes(source) {
    let cleaned = source.replace(/\r\n?/g, '\n');

    cleaned = collapseMatchedWhitespace(cleaned, [
        / = \(([^)]+)\)/g,
        /\.url\(\s*args,\s+\{/g,
        /\.url\(\s*args,\s+options\s*\)/g,
        /\.url\(\s*options\s*\)/g,
        /\(\s+\{/g,
        /\}\s+\)/g,
    ]);

    let depth = 0;
    cleaned = cleaned
        .split('\n')
        .map((line) => {
            const trimmed = line.trim();

            if (trimmed === '') {
                return '';
            }

            if (trimmed.startsWith('}') || trimmed.startsWith(']')) {
                depth -= 1;
            }

            const normalized = `${' '.repeat(Math.max(depth, 0) * 4)}${trimmed}`;

            if (trimmed.endsWith('{') || trimmed.endsWith('[')) {
                depth += 1;
            }

            return normalized;
        })
        .join('\n');

    for (const [pattern, replacement] of regexReplacements) {
        cleaned = cleaned.replace(pattern, replacement);
    }

    for (const [search, replacement] of stringReplacements) {
        cleaned = cleaned.replaceAll(search, replacement);
    }

    return `${cleaned.replace(/^[\t ]+$/gm, '').replace(/\n*$/, '')}\n`;
}

function normalizeWayfinderTypesUntilStable(source) {
    let cleaned = source;

    for (let pass = 0; pass < maxNormalizePasses; pass += 1) {
        const next = normalizeWayfinderTypes(cleaned);

        if (next === cleaned) {
            return cleaned;
        }

        cleaned = next;
    }

    return cleaned;
}

let changed = 0;

for (const path of generatedFiles) {
    const source = await readFile(path, 'utf8');
    const cleaned = normalizeWayfinderTypesUntilStable(source);

    if (cleaned !== source) {
        await writeFile(path, cleaned);
        changed += 1;
    }
}

if (changed > 0) {
    console.log(`Cleaned Wayfinder whitespace in ${changed} file(s).`);
}
