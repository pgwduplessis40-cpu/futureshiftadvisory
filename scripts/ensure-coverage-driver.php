<?php

declare(strict_types=1);

$xdebugMode = getenv('XDEBUG_MODE');
$xdebugMode = is_string($xdebugMode) && $xdebugMode !== ''
    ? $xdebugMode
    : (string) ini_get('xdebug.mode');
$xdebugModes = array_map('trim', explode(',', strtolower($xdebugMode)));

$hasPcov = extension_loaded('pcov');
$hasXdebugCoverage = extension_loaded('xdebug')
    && in_array('coverage', $xdebugModes, true);

if ($hasPcov || $hasXdebugCoverage) {
    exit(0);
}

fwrite(STDERR, "No PHP coverage driver is available. Install PCOV or enable Xdebug with XDEBUG_MODE=coverage.\n");

exit(1);
