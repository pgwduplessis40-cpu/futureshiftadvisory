<?php

declare(strict_types=1);

use App\Models\EntrepreneurProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('entrepreneur_profiles') || ! Schema::hasColumn('entrepreneur_profiles', 'gamification_on')) {
            return;
        }

        $this->setDefault(true);
        $this->backfillProfilesWithoutExplicitDisable();
    }

    public function down(): void
    {
        if (! Schema::hasTable('entrepreneur_profiles') || ! Schema::hasColumn('entrepreneur_profiles', 'gamification_on')) {
            return;
        }

        $this->setDefault(false);
    }

    private function setDefault(bool $enabled): void
    {
        $driver = DB::connection()->getDriverName();

        match ($driver) {
            'pgsql' => DB::statement(sprintf(
                'ALTER TABLE entrepreneur_profiles ALTER COLUMN gamification_on SET DEFAULT %s',
                $enabled ? 'true' : 'false',
            )),
            'mysql', 'mariadb' => DB::statement(sprintf(
                'ALTER TABLE entrepreneur_profiles ALTER COLUMN gamification_on SET DEFAULT %d',
                $enabled ? 1 : 0,
            )),
            default => null,
        };
    }

    private function backfillProfilesWithoutExplicitDisable(): void
    {
        $query = DB::table('entrepreneur_profiles')->where('gamification_on', false);

        if (Schema::hasTable('audit_events')) {
            $morphTypes = array_values(array_unique([
                (new EntrepreneurProfile)->getMorphClass(),
                EntrepreneurProfile::class,
                'entrepreneur_profile',
                'entrepreneur_profiles',
            ]));

            $explicitlyDisabledProfileIds = DB::table('audit_events')
                ->where('action', 'gamification.disabled')
                ->whereIn('subject_type', $morphTypes)
                ->whereNotNull('subject_id')
                ->pluck('subject_id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all();

            if ($explicitlyDisabledProfileIds !== []) {
                $query->whereNotIn('id', $explicitlyDisabledProfileIds);
            }
        }

        $query->update(['gamification_on' => true]);
    }
};
