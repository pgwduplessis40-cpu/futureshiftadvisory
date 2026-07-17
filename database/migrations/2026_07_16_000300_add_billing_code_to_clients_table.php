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
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('billing_code', 10)->nullable()->after('id');
        });

        $this->backfillBillingCodes();

        Schema::table('clients', function (Blueprint $table): void {
            $table->unique('billing_code');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropUnique(['billing_code']);
            $table->dropColumn('billing_code');
        });
    }

    private function backfillBillingCodes(): void
    {
        $seen = [];

        DB::table('clients')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($clients) use (&$seen): void {
                foreach ($clients as $client) {
                    $id = (string) $client->id;
                    $base = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $id) ?? '');
                    $base = $base !== '' ? $base : strtoupper(substr(hash('sha256', $id), 0, 6));
                    $suffix = substr($base, 0, 6);
                    $code = 'FSA-'.$suffix;

                    for ($attempt = 1; isset($seen[$code]); $attempt++) {
                        $suffix = strtoupper(substr(hash('sha256', $id.'|'.$attempt), 0, 6));
                        $code = 'FSA-'.$suffix;
                    }

                    $seen[$code] = true;

                    DB::table('clients')
                        ->where('id', $id)
                        ->update(['billing_code' => $code]);
                }
            }, 'id');
    }
};
