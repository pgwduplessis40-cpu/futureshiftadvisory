<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class AssertProductionSecurityConfiguration extends Command
{
    protected $signature = 'fsa:assert-production-security-config';

    protected $description = 'Fail deployment when production security configuration is unsafe.';

    public function handle(): int
    {
        if (config('app.env') !== 'production') {
            $this->info('Skipping production security configuration checks outside production.');

            return self::SUCCESS;
        }

        $failures = [];

        if ((bool) config('app.debug')) {
            $failures[] = 'APP_DEBUG must be false in production.';
        }

        if ((bool) config('session.secure') !== true) {
            $failures[] = 'SESSION_SECURE_COOKIE must be true in production.';
        }

        if ((bool) config('session.http_only') !== true) {
            $failures[] = 'SESSION_HTTP_ONLY must be true in production.';
        }

        if (! in_array(config('session.same_site'), ['lax', 'strict'], true)) {
            $failures[] = 'SESSION_SAME_SITE must be lax or strict in production.';
        }

        if ((bool) config('security.mfa_required') !== true) {
            $failures[] = 'MFA must be required in production.';
        }

        if ((bool) config('virus-scanner.live') !== true || (bool) config('virus-scanner.fail_open_on_error')) {
            $failures[] = 'Virus scanning must be live and fail closed in production.';
        }

        if ($failures === []) {
            $this->info('Production security configuration is safe.');

            return self::SUCCESS;
        }

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        return self::FAILURE;
    }
}
