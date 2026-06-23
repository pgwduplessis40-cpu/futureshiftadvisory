<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invite_tokens', 'token_envelope')) {
            return;
        }

        Schema::table('invite_tokens', function (Blueprint $table): void {
            $table->text('token_envelope')->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invite_tokens', 'token_envelope')) {
            return;
        }

        Schema::table('invite_tokens', function (Blueprint $table): void {
            $table->dropColumn('token_envelope');
        });
    }
};
