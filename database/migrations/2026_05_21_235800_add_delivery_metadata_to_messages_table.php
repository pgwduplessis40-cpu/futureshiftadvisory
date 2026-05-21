<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->string('channel', 32)->default('in_app')->after('sender_user_id');
            $table->string('delivery_state', 32)->default('sent')->after('attachments');
            $table->jsonb('channel_decision')->nullable()->after('delivery_state');
            $table->string('logical_message_key', 120)->nullable()->after('channel_decision');
            $table->string('email_subject')->nullable()->after('logical_message_key');
            $table->jsonb('email_recipients')->nullable()->after('email_subject');

            $table->index(['thread_id', 'channel']);
            $table->index(['channel', 'delivery_state']);
            $table->unique(['logical_message_key', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropUnique(['logical_message_key', 'channel']);
            $table->dropIndex(['channel', 'delivery_state']);
            $table->dropIndex(['thread_id', 'channel']);
            $table->dropColumn([
                'channel',
                'delivery_state',
                'channel_decision',
                'logical_message_key',
                'email_subject',
                'email_recipients',
            ]);
        });
    }
};
