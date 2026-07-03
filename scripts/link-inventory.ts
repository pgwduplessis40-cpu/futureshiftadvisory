import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';

const protectedMethods = new Set([
    'post',
    'get',
    'patch',
    'put',
    'delete',
    'submit',
    'visit',
    'reload',
    'transform',
    'flushAll',
]);

const networkIdentifiers = new Set([
    'fetch',
    'axios',
    'sendBeacon',
    'XMLHttpRequest',
    'EventSource',
    'WebSocket',
]);

const fixedJsxAttributes = new Set([
    'href',
    'as',
    'method',
    'target',
    'rel',
    'download',
    'type',
    'disabled',
    'aria-disabled',
    'data-test',
]);

const pluralMapNames = new Set([
    'urls',
    'links',
    'routes',
    'endpoints',
    'actions',
]);

const memberMapNames = new Set(['urls', 'links', 'routes', 'endpoints']);
const urlishNamePattern =
    /^(href|to|url|link|endpoint|action|urls|links|routes|endpoints|actions)$|(_url|Url|Href|Link|Endpoint)$/;
const handlerNamePattern = /^on[A-Z]\w*$/;

type InventoryEntry = {
    file: string;
    kind: string;
    line: number;
    text: string;
};

function main() {
    const args = process.argv.slice(2);

    if (args.includes('--fixture-check')) {
        runFixtureCheck();

        return;
    }

    const files = args.filter((arg) => !arg.startsWith('--'));

    if (files.length === 0) {
        console.error(
            'Usage: node scripts/link-inventory.ts [--fixture-check] <file...>',
        );
        process.exitCode = 1;

        return;
    }

    for (const line of inventoryFiles(files, args.includes('--with-lines'))) {
        console.log(line);
    }
}

function inventoryFiles(files: string[], withLines = false): string[] {
    const entries = files.flatMap((file) => inventoryFile(file));
    const lines = entries.map((entry) =>
        withLines
            ? `${entry.file}\t${entry.kind}\t${entry.line}\t${entry.text}`
            : `${entry.file}\t${entry.kind}\t${entry.text}`,
    );

    return [...new Set(lines)].sort();
}

function inventoryFile(file: string): InventoryEntry[] {
    const absoluteFile = path.resolve(file);
    const source = fs.readFileSync(absoluteFile, 'utf8');
    const sourceFile = ts.createSourceFile(
        absoluteFile,
        source,
        ts.ScriptTarget.Latest,
        true,
        absoluteFile.endsWith('.tsx') ? ts.ScriptKind.TSX : ts.ScriptKind.TS,
    );
    const relativeFile = path
        .relative(process.cwd(), absoluteFile)
        .replaceAll(path.sep, '/');
    const entries: InventoryEntry[] = [];

    const add = (
        kind: string,
        node: ts.Node,
        text = node.getText(sourceFile),
    ) => {
        const { line } = sourceFile.getLineAndCharacterOfPosition(
            node.getStart(sourceFile),
        );

        entries.push({
            file: relativeFile,
            kind,
            line: line + 1,
            text: compact(text),
        });
    };

    const visit = (node: ts.Node) => {
        if (ts.isImportDeclaration(node)) {
            captureImport(node, sourceFile, add);
        }

        if (ts.isJsxAttribute(node)) {
            captureJsxAttribute(node, sourceFile, add);
        }

        if (ts.isCallExpression(node)) {
            captureCallExpression(node, sourceFile, add);
        }

        if (ts.isNewExpression(node)) {
            captureNewExpression(node, add);
        }

        if (ts.isPropertyAssignment(node)) {
            capturePropertyAssignment(node, sourceFile, add);
        }

        if (ts.isPropertyAccessExpression(node)) {
            captureMapMemberRead(node, add);
        }

        ts.forEachChild(node, visit);
    };

    visit(sourceFile);

    return entries;
}

function captureImport(
    node: ts.ImportDeclaration,
    sourceFile: ts.SourceFile,
    add: (kind: string, node: ts.Node, text?: string) => void,
) {
    const moduleName = stringLiteralText(node.moduleSpecifier);

    if (
        !moduleName?.startsWith('@/routes') &&
        !moduleName?.startsWith('@/actions')
    ) {
        return;
    }

    add('import', node, node.getText(sourceFile));
}

function captureJsxAttribute(
    node: ts.JsxAttribute,
    sourceFile: ts.SourceFile,
    add: (kind: string, node: ts.Node, text?: string) => void,
) {
    const name = node.name.getText(sourceFile);

    if (
        fixedJsxAttributes.has(name) ||
        handlerNamePattern.test(name) ||
        shouldCaptureUrlishName(name, node.initializer, sourceFile)
    ) {
        add('jsx-attribute', node, node.getText(sourceFile));
    }
}

function captureCallExpression(
    node: ts.CallExpression,
    sourceFile: ts.SourceFile,
    add: (kind: string, node: ts.Node, text?: string) => void,
) {
    const expression = node.expression;

    if (ts.isPropertyAccessExpression(expression)) {
        const propertyName = expression.name.text;

        if (protectedMethods.has(propertyName)) {
            add('call', node, node.getText(sourceFile));

            return;
        }

        if (propertyName === 'sendBeacon') {
            add('network-call', node, node.getText(sourceFile));

            return;
        }
    }

    if (ts.isIdentifier(expression)) {
        if (expression.text === 'useForm') {
            add('use-form', node, node.getText(sourceFile));

            return;
        }

        if (networkIdentifiers.has(expression.text)) {
            add('network-call', node, node.getText(sourceFile));
        }
    }
}

function captureNewExpression(
    node: ts.NewExpression,
    add: (kind: string, node: ts.Node, text?: string) => void,
) {
    const expression = node.expression;

    if (
        ts.isIdentifier(expression) &&
        networkIdentifiers.has(expression.text)
    ) {
        add('network-call', node);
    }
}

function capturePropertyAssignment(
    node: ts.PropertyAssignment,
    sourceFile: ts.SourceFile,
    add: (kind: string, node: ts.Node, text?: string) => void,
) {
    const name = propertyNameText(node.name, sourceFile);

    if (!name) {
        return;
    }

    if (handlerNamePattern.test(name)) {
        add('object-handler', node, node.getText(sourceFile));

        return;
    }

    if (shouldCaptureUrlishName(name, node.initializer, sourceFile)) {
        add('url-property', node, node.getText(sourceFile));
    }
}

function captureMapMemberRead(
    node: ts.PropertyAccessExpression,
    add: (kind: string, node: ts.Node, text?: string) => void,
) {
    const expression = node.expression;

    if (!ts.isIdentifier(expression)) {
        return;
    }

    if (memberMapNames.has(expression.text)) {
        add('map-member', node);

        return;
    }

    if (
        expression.text === 'actions' &&
        urlishNamePattern.test(node.name.text)
    ) {
        add('map-member', node);
    }
}

function shouldCaptureUrlishName(
    name: string,
    value: ts.Node | undefined,
    sourceFile: ts.SourceFile,
): boolean {
    if (!urlishNamePattern.test(name)) {
        return false;
    }

    if (!pluralMapNames.has(name)) {
        return true;
    }

    return hasUrlishShape(value, sourceFile);
}

function hasUrlishShape(value: ts.Node | undefined, sourceFile: ts.SourceFile) {
    if (!value) {
        return false;
    }

    const text = value.getText(sourceFile);

    if (text.includes('=>') || text.includes('<')) {
        return false;
    }

    return (
        /https?:\/\//.test(text) ||
        /['"`]\/[^'"`]*/.test(text) ||
        /\b\w+(?:_url|Url|Href|Link|Endpoint)\b/.test(text) ||
        /\b(urls|links|routes|endpoints)\./.test(text)
    );
}

function propertyNameText(name: ts.PropertyName, sourceFile: ts.SourceFile) {
    if (
        ts.isIdentifier(name) ||
        ts.isStringLiteral(name) ||
        ts.isNumericLiteral(name)
    ) {
        return name.text;
    }

    return name.getText(sourceFile);
}

function stringLiteralText(node: ts.Node) {
    return ts.isStringLiteral(node) ? node.text : null;
}

function compact(value: string) {
    return value.replace(/\s+/g, ' ').trim();
}

function runFixtureCheck() {
    const fixtureDir = path.resolve('scripts/fixtures');
    const positiveFile = path.join(fixtureDir, 'link-inventory-positive.tsx');
    const negativeBeforeFile = path.join(
        fixtureDir,
        'link-inventory-negative-before.tsx',
    );
    const negativeAfterFile = path.join(
        fixtureDir,
        'link-inventory-negative-after.tsx',
    );
    const positive = inventoryFiles([positiveFile]).join('\n');
    const negativeBeforeLines = inventoryFiles([negativeBeforeFile]);
    const negativeAfterLines = inventoryFiles([negativeAfterFile]);
    const negativeBefore = negativeBeforeLines.join('\n');
    const requiredMarkers = [
        'createForm.post',
        'href={`/reports/${id}/preview`}',
        'urls.assistRequirement',
        'fetch(urls.assistRequirement',
        'onValueChange',
        'onCheckedChange',
        'target="_blank"',
        'download={urls.download_url}',
        'type="button"',
        'disabled={false}',
        'aria-disabled={false}',
        'data-test="fixture-link"',
        'router.flushAll()',
        'useForm',
        '@/routes',
        '@/actions',
        'onClick: () => review(briefing.review_url)',
    ];

    for (const marker of requiredMarkers) {
        if (!positive.includes(marker)) {
            throw new Error(`Fixture marker missing from snapshot: ${marker}`);
        }
    }

    if (
        negativeBefore.includes('jsx-attribute') &&
        !negativeBefore.includes('href="/kept"')
    ) {
        throw new Error('Negative fixture did not capture nested slot href.');
    }

    if (
        negativeBefore.includes('url-property') &&
        negativeBefore.includes('actions={<')
    ) {
        throw new Error(
            'Negative fixture map-captured a ReactNode actions slot.',
        );
    }

    if (
        normalizeFixtureLines(negativeBeforeLines) !==
        normalizeFixtureLines(negativeAfterLines)
    ) {
        throw new Error(
            'Style-only fixture edit changed the inventory output.',
        );
    }

    console.log('link-inventory fixture check passed');
}

function normalizeFixtureLines(lines: string[]) {
    return lines
        .map((line) => {
            const parts = line.split('\t');

            return parts.slice(1).join('\t');
        })
        .join('\n');
}

main();
