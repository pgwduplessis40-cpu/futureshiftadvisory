<?php

declare(strict_types=1);

use App\Models\ServiceRatePackage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_rate_packages')
            ->where('service_type', ServiceRatePackage::SERVICE_DD_PLAN_BUDGET)
            ->where('package_scope', ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON)
            ->update([
                'package_name' => 'Business Plan & Budget',
                'client_label' => 'Business Plan & Budget',
                'scope_description' => 'Fixed-fee Business Plan & Budget service. It is separate from any Due Diligence purchase-price band.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // The earlier labels described a retired combined-price model. Keep the
        // corrected commercial wording when rolling back code.
    }
};
