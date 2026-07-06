<?php

declare(strict_types=1);

use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_rate_packages', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_rate_packages', 'package_scope')) {
                $table->string('package_scope', 80)->nullable()->after('service_type');
                $table->index(['service_type', 'package_scope']);
            }
        });

        ServiceRatePackage::query()
            ->where('service_type', ServiceRatePackage::SERVICE_ENTREPRENEUR)
            ->whereNull('package_scope')
            ->where(function ($query): void {
                $query
                    ->whereRaw('lower(package_name) like ?', ['%stage 1%'])
                    ->orWhereRaw('lower(client_label) like ?', ['%stage 1%'])
                    ->orWhereRaw('lower(package_name) like ?', ['%idea sprint%'])
                    ->orWhereRaw('lower(client_label) like ?', ['%idea sprint%'])
                    ->orWhereRaw('lower(package_name) like ?', ['%idea validation%'])
                    ->orWhereRaw('lower(client_label) like ?', ['%idea validation%']);
            })
            ->update(['package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION]);

        ServiceRatePackage::query()
            ->where('service_type', ServiceRatePackage::SERVICE_ENTREPRENEUR)
            ->whereNull('package_scope')
            ->where(function ($query): void {
                $query
                    ->whereRaw('lower(package_name) like ?', ['%stage 2%'])
                    ->orWhereRaw('lower(client_label) like ?', ['%stage 2%'])
                    ->orWhereRaw('lower(package_name) like ?', ['%full plan%'])
                    ->orWhereRaw('lower(client_label) like ?', ['%full plan%'])
                    ->orWhereRaw('lower(package_name) like ?', ['%assessment%'])
                    ->orWhereRaw('lower(client_label) like ?', ['%assessment%']);
            })
            ->update(['package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET]);

        ServiceRatePackage::query()
            ->where('service_type', ServiceRatePackage::SERVICE_ENTREPRENEUR)
            ->whereNull('package_scope')
            ->update(['package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO]);

        ServiceRatePackage::query()
            ->where('service_type', ServiceRatePackage::SERVICE_DUE_DILIGENCE)
            ->whereNull('package_scope')
            ->where('purchase_price_max', '<=', 300000)
            ->update(['package_scope' => ServiceRatePackage::SCOPE_DD_UNDER_300K]);

        ServiceRatePackage::query()
            ->where('service_type', ServiceRatePackage::SERVICE_DUE_DILIGENCE)
            ->whereNull('package_scope')
            ->where('purchase_price_min', '>=', 300000)
            ->where('purchase_price_max', '<=', 1000000)
            ->update(['package_scope' => ServiceRatePackage::SCOPE_DD_300K_1M]);

        ServiceRatePackage::query()
            ->where('service_type', ServiceRatePackage::SERVICE_DUE_DILIGENCE)
            ->whereNull('package_scope')
            ->where('purchase_price_min', '>=', 1000000)
            ->update(['package_scope' => ServiceRatePackage::SCOPE_DD_1M_3M]);

        Schema::table('service_activations', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_activations', 'payment_status')) {
                $table->string('payment_status', 40)
                    ->default(ServiceActivation::PAYMENT_NOT_REQUIRED)
                    ->after('selected_package_snapshot');
            }

            if (! Schema::hasColumn('service_activations', 'payment_completed_at')) {
                $table->timestampTz('payment_completed_at')
                    ->nullable()
                    ->after('payment_status');
            }

            if (! Schema::hasColumn('service_activations', 'payment_completed_by_user_id')) {
                $table->foreignId('payment_completed_by_user_id')
                    ->nullable()
                    ->after('payment_completed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('service_activations', 'payment_reference')) {
                $table->string('payment_reference', 160)
                    ->nullable()
                    ->after('payment_completed_by_user_id');
            }

            $table->index(['payment_status', 'payment_completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('service_activations', function (Blueprint $table): void {
            if (Schema::hasColumn('service_activations', 'payment_completed_by_user_id')) {
                $table->dropForeign(['payment_completed_by_user_id']);
            }

            if (Schema::hasColumn('service_activations', 'payment_status')) {
                $table->dropIndex(['payment_status', 'payment_completed_at']);
            }

            foreach ([
                'payment_reference',
                'payment_completed_by_user_id',
                'payment_completed_at',
                'payment_status',
            ] as $column) {
                if (Schema::hasColumn('service_activations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('service_rate_packages', function (Blueprint $table): void {
            if (Schema::hasColumn('service_rate_packages', 'package_scope')) {
                $table->dropIndex(['service_type', 'package_scope']);
                $table->dropColumn('package_scope');
            }
        });
    }
};
