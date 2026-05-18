<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enables the Postgres extensions the Future Shift Advisory platform depends on.
 *
 * - pgcrypto: gen_random_uuid() for UUID primary keys.
 * - uuid-ossp: legacy uuid_generate_v4() compatibility (kept available for any
 *   third-party package that expects it; the app prefers gen_random_uuid()).
 *
 * Postgres-only by design. This migration is intentionally the first to run
 * (the 0000_ prefix sorts before the framework's 0001_ migrations) so that any
 * subsequent migration may rely on gen_random_uuid() in defaults.
 *
 * See: PLAN.md section 5 and section 6.1; docs/architecture/postgres-rls.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
    }

    public function down(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        // We deliberately do NOT drop the extensions: other databases on the
        // same Postgres cluster may rely on them, and removing pgcrypto would
        // break any DEFAULT gen_random_uuid() columns in older migrations.
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
