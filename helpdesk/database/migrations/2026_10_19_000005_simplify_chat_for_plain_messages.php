<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('chat_message_pending_users');
        Schema::dropIfExists('chat_message_archives');
        Schema::dropIfExists('chat_message_keys');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_participants');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('chat_devices');

        Schema::create('chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_one_id');
            $table->unsignedBigInteger('user_two_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['user_one_id', 'user_two_id'], 'uq_chat_direct_users');
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('sender_id');
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index(['conversation_id', 'id'], 'idx_chat_messages_conversation');
        });
    }

    public function down(): void
    {
        throw new LogicException('Plain-chat migration is destructive and cannot be rolled back.');
    }
};
