<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_accounting_syncs', function (Blueprint $table): void {
            $table->string('external_credit_note_payment_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_accounting_syncs', function (Blueprint $table): void {
            $table->dropColumn('external_credit_note_payment_id');
        });
    }
};
