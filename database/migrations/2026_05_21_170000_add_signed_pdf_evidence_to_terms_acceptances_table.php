<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms_acceptances', function (Blueprint $table): void {
            $table->text('signed_pdf_sha256_envelope')->nullable()->after('signed_pdf_path');
            $table->jsonb('signed_pdf_envelope_meta')->nullable()->after('signed_pdf_sha256_envelope');
            $table->unsignedBigInteger('signed_pdf_byte_size')->nullable()->after('signed_pdf_envelope_meta');
        });
    }

    public function down(): void
    {
        Schema::table('terms_acceptances', function (Blueprint $table): void {
            $table->dropColumn([
                'signed_pdf_sha256_envelope',
                'signed_pdf_envelope_meta',
                'signed_pdf_byte_size',
            ]);
        });
    }
};
