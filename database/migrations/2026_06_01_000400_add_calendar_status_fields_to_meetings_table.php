<?php

declare(strict_types=1);

use App\Models\Meeting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->string('status', 32)->default(Meeting::STATUS_SCHEDULED)->after('attendees');
            $table->timestampTz('cancelled_at')->nullable()->after('status');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->timestampTz('reminder_sent_at')->nullable()->after('cancelled_by_user_id');
            $table->index(['created_by_user_id', 'status', 'scheduled_at'], 'meetings_owner_status_scheduled_idx');
            $table->index(['status', 'scheduled_at'], 'meetings_status_scheduled_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(sprintf(
                "ALTER TABLE meetings ADD CONSTRAINT meetings_status_check CHECK (status IN ('%s'))",
                implode("','", Meeting::statuses()),
            ));
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE meetings DROP CONSTRAINT IF EXISTS meetings_status_check');
        }

        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropIndex('meetings_owner_status_scheduled_idx');
            $table->dropIndex('meetings_status_scheduled_idx');
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['status', 'cancelled_at', 'reminder_sent_at']);
        });
    }
};
