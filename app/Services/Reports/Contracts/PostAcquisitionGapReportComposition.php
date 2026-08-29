<?php

declare(strict_types=1);

namespace App\Services\Reports\Contracts;

use App\Models\PostAcquisitionMigration;
use App\Models\Report;
use App\Models\User;

/**
 * Typed boundary for post-acquisition gap report composition.
 */
interface PostAcquisitionGapReportComposition
{
    public function compose(PostAcquisitionMigration $migration, ?User $actor = null): Report;
}
