<?php

declare(strict_types=1);

use App\Enums\ClientStatus;
use App\Enums\EntrepreneurStage;
use App\Models\ServiceActivation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('entrepreneur_profiles', 'suspended_from_stage')) {
            Schema::table('entrepreneur_profiles', function ($table): void {
                $table->string('suspended_from_stage', 40)->nullable();
            });
        }

        $timestamp = now();

        DB::table('entrepreneur_profiles')
            ->whereIn('client_id', DB::table('clients')
                ->select('id')
                ->where('status', ClientStatus::SUSPENDED->value))
            ->where('stage', '!=', EntrepreneurStage::CANCELLED->value)
            ->update([
                'suspended_from_stage' => DB::raw('stage'),
                'stage' => EntrepreneurStage::SUSPENDED->value,
                'updated_at' => $timestamp,
            ]);

        DB::table('entrepreneur_profiles')
            ->whereIn('client_id', DB::table('clients')
                ->select('id')
                ->where('status', ClientStatus::OFFBOARDED->value))
            ->update([
                'stage' => EntrepreneurStage::CANCELLED->value,
                'suspended_from_stage' => null,
                'updated_at' => $timestamp,
            ]);

        DB::table('entrepreneur_profiles')
            ->whereIn('id', DB::table('service_activations')
                ->select('related_entrepreneur_profile_id')
                ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
                ->where('status', ServiceActivation::STATUS_CANCELLED))
            ->update([
                'stage' => EntrepreneurStage::CANCELLED->value,
                'suspended_from_stage' => null,
                'updated_at' => $timestamp,
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('entrepreneur_profiles', 'suspended_from_stage')) {
            Schema::table('entrepreneur_profiles', function ($table): void {
                $table->dropColumn('suspended_from_stage');
            });
        }
    }
};
