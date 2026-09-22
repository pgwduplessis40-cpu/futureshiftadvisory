<?php

declare(strict_types=1);

use App\Models\ServiceRatePackage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove only the historic monitor fixtures that were incorrectly stored
     * as commercial Service Rates. Their activation snapshots remain intact.
     */
    public function up(): void
    {
        $fixtureIds = DB::table('service_rate_packages')
            ->where('service_type', ServiceRatePackage::SERVICE_DD_PLAN_BUDGET)
            ->whereIn('package_name', [
                'Operational Health Business Plan & Budget add-on',
                'Operational Health Advisory Business Plan & Budget add-on',
            ])
            ->pluck('id');

        if ($fixtureIds->isEmpty()) {
            return;
        }

        DB::table('service_activations')
            ->whereIn('service_rate_package_id', $fixtureIds)
            ->update([
                'service_rate_package_id' => null,
                'updated_at' => now(),
            ]);

        DB::table('service_rate_packages')
            ->whereIn('id', $fixtureIds)
            ->delete();
    }

    public function down(): void
    {
        // Deliberately do not recreate synthetic monitor fixtures as commercial rates.
    }
};
