<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-1 slice of the `prospect_leads` table.
 * The full schema (status, assignee, dedupe key, conversion tracking) lands
 * with the Advisor Prospect Inbox work order. Columns below are intentionally
 * forward-compatible — additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_leads', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('engagement_interest')->nullable();
            $table->text('message');
            $table->string('source', 64)->default('public_contact_form');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_leads');
    }
};
