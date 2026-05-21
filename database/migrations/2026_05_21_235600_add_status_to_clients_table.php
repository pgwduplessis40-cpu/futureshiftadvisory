<?php

declare(strict_types=1);

use App\Enums\ClientStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('status', 40)
                ->default(ClientStatus::ACTIVE->value)
                ->after('engagement_type');
            $table->index('status');
        });

        if ($this->onPostgres()) {
            DB::statement(sprintf(
                "ALTER TABLE clients ADD CONSTRAINT clients_status_check CHECK (status IN ('%s'))",
                implode("','", array_map(static fn (ClientStatus $status): string => $status->value, ClientStatus::cases())),
            ));
        }
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::statement('ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_status_check');
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
