<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_rate_packages', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_rate_packages', 'deposit_percent')) {
                $table->decimal('deposit_percent', 5, 2)
                    ->default(100)
                    ->after('fixed_fee');
            }
        });

        Schema::table('service_activations', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_activations', 'deposit_paid_at')) {
                $table->timestampTz('deposit_paid_at')
                    ->nullable()
                    ->after('payment_reference');
            }

            if (! Schema::hasColumn('service_activations', 'deposit_paid_by_user_id')) {
                $table->foreignId('deposit_paid_by_user_id')
                    ->nullable()
                    ->after('deposit_paid_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('service_activations', 'deposit_reference')) {
                $table->string('deposit_reference', 160)
                    ->nullable()
                    ->after('deposit_paid_by_user_id');
            }

            if (! Schema::hasColumn('service_activations', 'balance_received_at')) {
                $table->timestampTz('balance_received_at')
                    ->nullable()
                    ->after('deposit_reference');
            }

            if (! Schema::hasColumn('service_activations', 'balance_received_by_user_id')) {
                $table->foreignId('balance_received_by_user_id')
                    ->nullable()
                    ->after('balance_received_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('service_activations', 'balance_reference')) {
                $table->string('balance_reference', 160)
                    ->nullable()
                    ->after('balance_received_by_user_id');
            }

            $table->index(['deposit_paid_at', 'balance_received_at']);
        });
    }

    public function down(): void
    {
        Schema::table('service_rate_packages', function (Blueprint $table): void {
            if (Schema::hasColumn('service_rate_packages', 'deposit_percent')) {
                $table->dropColumn('deposit_percent');
            }
        });

        Schema::table('service_activations', function (Blueprint $table): void {
            if (Schema::hasColumn('service_activations', 'deposit_paid_by_user_id')) {
                $table->dropForeign(['deposit_paid_by_user_id']);
            }

            if (Schema::hasColumn('service_activations', 'balance_received_by_user_id')) {
                $table->dropForeign(['balance_received_by_user_id']);
            }

            if (
                Schema::hasColumn('service_activations', 'deposit_paid_at') &&
                Schema::hasColumn('service_activations', 'balance_received_at')
            ) {
                $table->dropIndex(['deposit_paid_at', 'balance_received_at']);
            }

            foreach ([
                'balance_reference',
                'balance_received_by_user_id',
                'balance_received_at',
                'deposit_reference',
                'deposit_paid_by_user_id',
                'deposit_paid_at',
            ] as $column) {
                if (Schema::hasColumn('service_activations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
