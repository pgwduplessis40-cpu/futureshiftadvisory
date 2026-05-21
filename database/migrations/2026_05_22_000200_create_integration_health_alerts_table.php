<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_health_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('service', 80);
            $table->timestampTz('stuck_started_at');
            $table->timestampTz('last_red_window_end');
            $table->timestampTz('notified_at');
            $table->uuid('notification_id')->nullable();
            $table->timestampsTz();

            $table->unique(['service', 'stuck_started_at']);
            $table->index(['service', 'last_red_window_end']);
            $table->index('notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_health_alerts');
    }
};
