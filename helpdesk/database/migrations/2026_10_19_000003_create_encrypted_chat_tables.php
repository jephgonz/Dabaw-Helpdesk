<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_devices')) {
            Schema::create('chat_devices', function (Blueprint $table): void {
                $table->uuid('device_id')->primary();
                $table->unsignedBigInteger('user_id');
                $table->text('public_key');
                $table->timestamp('last_seen')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->index(['user_id', 'last_seen'], 'idx_chat_devices_user_seen');
            });
        }

        if (!Schema::hasTable('chat_conversations')) {
            Schema::create('chat_conversations', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120)->nullable();
                $table->boolean('is_group')->default(false);
                $table->unsignedBigInteger('created_by');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (!Schema::hasTable('chat_participants')) {
            Schema::create('chat_participants', function (Blueprint $table): void {
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('joined_at')->useCurrent();
                $table->primary(['conversation_id', 'user_id']);
                $table->index('user_id', 'idx_chat_participants_user');
            });
        }

        if (!Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('sender_id');
                $table->uuid('sender_device_id');
                $table->mediumText('ciphertext');
                $table->string('iv', 32);
                $table->timestamp('created_at')->useCurrent();
                $table->index(['conversation_id', 'created_at'], 'idx_chat_messages_conversation_created');
            });
        }

        if (!Schema::hasTable('chat_message_keys')) {
            Schema::create('chat_message_keys', function (Blueprint $table): void {
                $table->unsignedBigInteger('message_id');
                $table->uuid('device_id');
                $table->text('wrapped_key');
                $table->primary(['message_id', 'device_id']);
                $table->index('device_id', 'idx_chat_message_keys_device');
            });
        }

        if (!Schema::hasTable('chat_message_archives')) {
            Schema::create('chat_message_archives', function (Blueprint $table): void {
                $table->unsignedBigInteger('message_id');
                $table->uuid('device_id');
                $table->timestamp('archived_at')->useCurrent();
                $table->primary(['message_id', 'device_id']);
            });
        }
    }

    public function down(): void
    {
        // Preserve encrypted conversations and device archives during rollback.
    }
};
