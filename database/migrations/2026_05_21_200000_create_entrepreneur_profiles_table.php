<?php

declare(strict_types=1);

use App\Enums\EntrepreneurStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrepreneur_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_advisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('invite_token_id')->nullable()->constrained('invite_tokens')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('stage', 40)->default(EntrepreneurStage::INVITED->value);
            $table->text('concept_summary')->nullable();
            $table->timestampsTz();

            $table->unique('email');
            $table->index(['assigned_advisor_id', 'stage']);
            $table->index('user_id');
            $table->index('invite_token_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrepreneur_profiles');
    }
};
