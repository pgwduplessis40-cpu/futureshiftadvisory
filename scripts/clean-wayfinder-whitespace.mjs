import { readFile, writeFile } from 'node:fs/promises';

const generatedFiles = [
    'resources/js/actions/App/Http/Controllers/Admin/ReferenceDataController.ts',
    'resources/js/routes/advisor/entrepreneurs/index.ts',
    'resources/js/routes/admin/reference-data/index.ts',
    'resources/js/routes/public/index.ts',
];

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
