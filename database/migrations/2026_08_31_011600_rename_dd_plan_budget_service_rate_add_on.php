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
            ->whereIn('client_label', [
                'DD + Business Plan & Budget',
                'Operational Health DD + Business Plan & Budget',
            ])
            ->update([
                'package_name' => 'Business Plan & Budget add-on',
                'client_label' => 'Business Plan & Budget add-on',
                'scope_description' => 'Single Business Plan & Budget add-on fee. Explore Buying a Business keeps its matched purchase-price band, and this BP&B fee is added only when BP&B is included for the client.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('service_rate_packages')
            ->where('service_type', ServiceRatePackage::SERVICE_DD_PLAN_BUDGET)
            ->where('package_scope', ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON)
            ->where('client_label', 'Business Plan & Budget add-on')
            ->update([
                'package_name' => 'DD + Business Plan & Budget',
                'client_label' => 'DD + Business Plan & Budget',
                'scope_description' => 'Single Business Plan & Budget add-on rate. The client quote combines the matched DD purchase-price band with this BP&B fee before approval.',
                'updated_at' => now(),
            ]);
    }
};
