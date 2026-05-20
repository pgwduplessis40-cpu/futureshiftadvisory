<?php

declare(strict_types=1);

namespace App\Services\Storage\Exceptions;

use RuntimeException;

/**
 * Thrown when KeyEnvelope receives a payload that is not a valid envelope
 * (malformed JSON, missing required fields, etc.).
 */
final class InvalidEnvelopeException extends RuntimeException {}
