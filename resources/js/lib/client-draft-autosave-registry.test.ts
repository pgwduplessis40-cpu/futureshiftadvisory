import assert from 'node:assert/strict';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { relative, resolve } from 'node:path';
import test from 'node:test';
import { CLIENT_DRAFT_AUTOSAVE_SURFACES } from './client-draft-autosave-registry';

const repositoryRoot = resolve(import.meta.dirname, '../../..');
const portalSourceRoot = resolve(repositoryRoot, 'resources/js/pages/portal');
const mutablePortalFilePattern =
    /\buseForm\s*(?:<[^>]+>)?\s*\(|<form\b|\brouter\.(?:post|put|patch)\s*\(|\b\w+Form\.(?:post|put|patch)\s*\(/;

test('every mutable portal source is classified as draft-protected or intentionally explicit', () => {
    const mutableSources = [
        ...sourceFiles(portalSourceRoot),
        resolve(
            repositoryRoot,
            'resources/js/components/messages/ThreadedMessaging.tsx',
        ),
    ]
        .filter((source) => mutablePortalFilePattern.test(readSource(source)))
        .map(relativeSource)
        .sort();
    const registeredSources = CLIENT_DRAFT_AUTOSAVE_SURFACES.map(
        (surface) => surface.source,
    ).sort();

    assert.deepEqual(
        registeredSources,
        mutableSources,
        'Add every new mutable portal source to the client draft autosave registry. Draft ordinary, reversible input; document upload, payment, acceptance, and final submission as explicit actions.',
    );
});

test('draft-protected surfaces retain verifiable autosave evidence and explicit actions are documented', () => {
    for (const surface of CLIENT_DRAFT_AUTOSAVE_SURFACES) {
        assert.equal(
            existsSync(resolve(repositoryRoot, surface.source)),
            true,
            `${surface.source} must exist`,
        );
        assert.notEqual(
            surface.summary.trim(),
            '',
            `${surface.source} needs a summary`,
        );
        assert.notEqual(
            (surface.explicitAction ?? '').trim(),
            '',
            `${surface.source} needs its explicit-action boundary documented`,
        );

        if (surface.mode === 'explicit') {
            continue;
        }

        assert.ok(
            surface.evidence.length > 0,
            `${surface.source} must name its autosave evidence`,
        );

        for (const evidence of surface.evidence) {
            assert.equal(
                readSource(resolve(repositoryRoot, evidence.source)).includes(
                    evidence.marker,
                ),
                true,
                `${surface.source} expects ${evidence.marker} in ${evidence.source}`,
            );
        }
    }
});

function sourceFiles(directory: string): string[] {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const source = resolve(directory, entry.name);

        if (entry.isDirectory()) {
            return sourceFiles(source);
        }

        return entry.isFile() && /(?<!\.test)\.(?:ts|tsx)$/.test(entry.name)
            ? [source]
            : [];
    });
}

function readSource(source: string): string {
    return readFileSync(source, 'utf8');
}

function relativeSource(source: string): string {
    return relative(repositoryRoot, source).replaceAll('\\', '/');
}
