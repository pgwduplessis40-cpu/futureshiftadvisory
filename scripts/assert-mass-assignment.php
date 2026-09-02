<?php

declare(strict_types=1);

$comparisonRef = $argv[1] ?? null;
$config = require __DIR__.'/../config/production_quality.php';
$failures = [];
$broadGuardedModels = broadGuardedModels();
$ceiling = (int) ($config['legacy_broad_guarded_model_ceiling'] ?? 0);

if (count($broadGuardedModels) > $ceiling) {
    $failures[] = sprintf(
        'Unrestricted model count is %d, above the ratcheted ceiling of %d: %s.',
        count($broadGuardedModels),
        $ceiling,
        implode(', ', $broadGuardedModels),
    );
}

foreach ($config['sensitive_models'] as $model => $path) {
    $contents = @file_get_contents($path);

    if ($contents === false) {
        $failures[] = "Sensitive model {$model} is missing from {$path}.";

        continue;
    }

    if (preg_match('/(?:public|protected)\s+\$guarded\s*=\s*\[\s*\]\s*;/', $contents) === 1) {
        $failures[] = "Sensitive model {$model} uses unrestricted \$guarded = [].";
    }

    if (preg_match('/(?:public|protected)\s+\$fillable\s*=\s*\[\s*\S/', $contents) !== 1) {
        $failures[] = "Sensitive model {$model} lacks an explicit \$fillable allow-list.";
    }
}

if ($comparisonRef !== null) {
    enforceNoNewUnrestrictedModels($comparisonRef, $failures);
    enforceNoSensitiveRegistryRemovals($comparisonRef, $config, $failures);
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);

    exit(1);
}

printf(
    'Sensitive-model allow-list registry and unrestricted-model ratchet passed (%d legacy unrestricted models, ceiling %d).%s',
    count($broadGuardedModels),
    $ceiling,
    PHP_EOL,
);

/** @return list<string> */
function broadGuardedModels(): array
{
    $models = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__.'/../app/Models', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if ($contents === false || preg_match('/(?:public|protected)\s+\$guarded\s*=\s*\[\s*\]\s*;/', $contents) !== 1) {
            continue;
        }

        $models[] = str_replace('\\', '/', substr($file->getPathname(), strlen(__DIR__.'/..') + 1));
    }

    sort($models);

    return $models;
}

/** @param list<string> $failures */
function enforceNoNewUnrestrictedModels(string $comparisonRef, array &$failures): void
{
    if (preg_match('/^[A-Za-z0-9._\\/-]+$/', $comparisonRef) !== 1) {
        $failures[] = "Unsafe comparison revision {$comparisonRef}.";

        return;
    }

    $process = proc_open(
        ['git', 'diff', '--unified=0', $comparisonRef, '--', 'app/Models'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    if (! is_resource($process)) {
        $failures[] = 'Could not inspect new unrestricted models.';

        return;
    }

    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0 || ! is_string($output)) {
        $failures[] = 'Could not inspect new unrestricted models: '.$error;

        return;
    }

    $path = null;
    foreach (preg_split('/\R/', $output) ?: [] as $line) {
        if (str_starts_with($line, '+++ b/')) {
            $path = substr($line, 6);

            continue;
        }

        if ($path !== null
            && str_starts_with($line, '+')
            && ! str_starts_with($line, '+++')
            && preg_match('/(?:public|protected)\s+\$guarded\s*=\s*\[\s*\]\s*;/', $line) === 1
        ) {
            $failures[] = "New unrestricted model declaration in {$path}.";
        }
    }
}

/** @param array<string,mixed> $config @param list<string> $failures */
function enforceNoSensitiveRegistryRemovals(string $comparisonRef, array $config, array &$failures): void
{
    $process = proc_open(
        ['git', 'show', "{$comparisonRef}:config/production_quality.php"],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    if (! is_resource($process)) {
        $failures[] = "Could not inspect the sensitive-model registry from {$comparisonRef}.";

        return;
    }

    $contents = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0 || ! is_string($contents)) {
        if (str_contains($error, 'does not exist') || str_contains($error, 'not in ')) {
            return;
        }

        $failures[] = "Could not inspect the sensitive-model registry from {$comparisonRef}: {$error}";

        return;
    }

    $temporaryPath = tempnam(sys_get_temp_dir(), 'production-quality-config-');
    if ($temporaryPath === false || file_put_contents($temporaryPath, $contents) === false) {
        $failures[] = 'Could not create a comparison sensitive-model registry.';

        return;
    }

    try {
        $previous = require $temporaryPath;
    } finally {
        @unlink($temporaryPath);
    }

    if (! is_array($previous) || ! is_array($previous['sensitive_models'] ?? null)) {
        $failures[] = "Sensitive-model registry from {$comparisonRef} is invalid.";

        return;
    }

    foreach ($previous['sensitive_models'] as $model => $_path) {
        if (! array_key_exists($model, $config['sensitive_models'])) {
            $failures[] = "Sensitive model {$model} was removed from the allow-list registry.";
        }
    }
}
