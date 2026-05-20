<?php

declare(strict_types=1);

namespace App\Services\Storage\Exceptions;

use RuntimeException;

/**
 * Thrown when KeyEnvelope is asked to decrypt an envelope whose version is
 * not supported by this build. Most likely a forward-compatibility issue
 * (an envelope written by a future PQC version being read by an older
 * build that lacks Kyber).
 */
final class UnsupportedEnvelopeVersionException extends RuntimeException {}
