<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\DocumentVerification;
use Illuminate\Support\Collection;
use RuntimeException;

final class DocumentVerificationBlockedException extends RuntimeException
{
    /**
     * @param  Collection<int, DocumentVerification>  $flags
     */
    public function __construct(public readonly Collection $flags)
    {
        parent::__construct('Analysis is paused until outstanding document verification flags are resolved.');
    }
}
