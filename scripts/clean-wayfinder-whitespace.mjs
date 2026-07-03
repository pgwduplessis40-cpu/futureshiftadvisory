import { readdir, readFile, writeFile } from 'node:fs/promises';

const generatedRoots = ['resources/js/actions', 'resources/js/routes'];

async function tsFilesIn(path) {
    const entries = await readdir(path, { withFileTypes: true });
    const files = await Promise.all(
        entries.map((entry) => {
            const childPath = `${path}/${entry.name}`;

            if (entry.isDirectory()) {
                return tsFilesIn(childPath);
            }

            return entry.isFile() && entry.name.endsWith('.ts') ? [childPath] : [];
        }),
    );

    return files.flat();
}

const generatedFiles = (await Promise.all(generatedRoots.map(tsFilesIn))).flat();

let changed = 0;

for (const path of generatedFiles) {
    const source = await readFile(path, 'utf8');
    const cleaned = source.replace(/^[\t ]+(\r?\n)/gm, '$1').replace(/^[\t ]+$/g, '');

    if (cleaned !== source) {
        await writeFile(path, cleaned);
        changed += 1;
    }
}

if (changed > 0) {
    console.log(`Cleaned Wayfinder whitespace in ${changed} file(s).`);
}
