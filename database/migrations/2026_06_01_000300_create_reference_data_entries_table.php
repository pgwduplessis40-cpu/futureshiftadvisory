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
        Schema::create('reference_data_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('dataset', 80);
            $table->jsonb('payload');
            $table->date('as_at');
            $table->string('source');
            $table->foreignId('entered_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('learning_update_id')->constrained('learning_updates')->cascadeOnDelete();
            $table->timestampsTz();

            $table->index(['dataset', 'as_at']);
            $table->index('learning_update_id');
        });

        $this->installPostgresGuards();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS reference_data_entries_append_only ON reference_data_entries;');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_reference_data_entry_mutation();');
        }

        Schema::dropIfExists('reference_data_entries');
    }

    private function installPostgresGuards(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(
            <<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_reference_data_entry_mutation()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'reference_data_entries are append-only';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER reference_data_entries_append_only
                    BEFORE UPDATE OR DELETE ON reference_data_entries
                    FOR EACH ROW EXECUTE FUNCTION prevent_reference_data_entry_mutation();

                ALTER TABLE reference_data_entries ENABLE ROW LEVEL SECURITY;
                ALTER TABLE reference_data_entries FORCE ROW LEVEL SECURITY;

                CREATE POLICY reference_data_entries_super_admin_scope ON reference_data_entries
                    USING (fsa_current_role() IN ('super_admin', 'system'))
                    WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));
            SQL
        );
    }
};
