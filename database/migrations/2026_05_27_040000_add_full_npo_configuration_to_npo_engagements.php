<?php

declare(strict_types=1);

use App\Enums\NpoSocialEnterpriseType;
use App\Enums\NpoTiritiMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('npo_engagements', function (Blueprint $table): void {
            $table->string('tiriti_mode', 24)->nullable()->after('legal_structure');
            $table->jsonb('tiriti_decision_guide')->nullable()->after('tiriti_mode');
            $table->boolean('social_enterprise')->default(false)->after('tiriti_decision_guide');
            $table->string('social_enterprise_type', 40)->nullable()->after('social_enterprise');
            $table->unsignedTinyInteger('commercial_weight')->nullable()->after('social_enterprise_type');
            $table->unsignedTinyInteger('mission_weight')->nullable()->after('commercial_weight');

            $table->index(['client_id', 'tiriti_mode']);
            $table->index(['client_id', 'social_enterprise']);
        });

        $this->installChecks();
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::statement('ALTER TABLE npo_engagements DROP CONSTRAINT IF EXISTS npo_engagements_tiriti_mode_check');
            DB::statement('ALTER TABLE npo_engagements DROP CONSTRAINT IF EXISTS npo_engagements_social_enterprise_type_check');
            DB::statement('ALTER TABLE npo_engagements DROP CONSTRAINT IF EXISTS npo_engagements_social_enterprise_weights_check');
        }

        Schema::table('npo_engagements', function (Blueprint $table): void {
            $table->dropIndex(['client_id', 'social_enterprise']);
            $table->dropIndex(['client_id', 'tiriti_mode']);
            $table->dropColumn([
                'mission_weight',
                'commercial_weight',
                'social_enterprise_type',
                'social_enterprise',
                'tiriti_decision_guide',
                'tiriti_mode',
            ]);
        });
    }

    private function installChecks(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        $tiritiModes = $this->quotedValues(array_map(
            static fn (NpoTiritiMode $mode): string => $mode->value,
            NpoTiritiMode::cases(),
        ));
        $socialEnterpriseTypes = $this->quotedValues(array_map(
            static fn (NpoSocialEnterpriseType $type): string => $type->value,
            NpoSocialEnterpriseType::cases(),
        ));

        DB::unprepared(<<<SQL
            ALTER TABLE npo_engagements
                ADD CONSTRAINT npo_engagements_tiriti_mode_check
                    CHECK (tiriti_mode IS NULL OR tiriti_mode IN ({$tiritiModes})),
                ADD CONSTRAINT npo_engagements_social_enterprise_type_check
                    CHECK (social_enterprise_type IS NULL OR social_enterprise_type IN ({$socialEnterpriseTypes})),
                ADD CONSTRAINT npo_engagements_social_enterprise_weights_check
                    CHECK (
                        (
                            social_enterprise = false
                            AND social_enterprise_type IS NULL
                            AND commercial_weight IS NULL
                            AND mission_weight IS NULL
                        )
                        OR (
                            social_enterprise = true
                            AND social_enterprise_type IS NOT NULL
                            AND commercial_weight BETWEEN 0 AND 100
                            AND mission_weight BETWEEN 0 AND 100
                            AND commercial_weight + mission_weight = 100
                        )
                    );
        SQL);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function quotedValues(array $values): string
    {
        return collect($values)
            ->map(static fn (string $value): string => "'".str_replace("'", "''", $value)."'")
            ->implode(', ');
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
