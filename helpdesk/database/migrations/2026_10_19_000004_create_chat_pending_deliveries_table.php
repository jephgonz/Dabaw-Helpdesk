<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_message_pending_users')) {
            Schema::create('chat_message_pending_users', function (Blueprint $table): void {
                $table->unsignedBigInteger('message_id');
                $table->unsignedBigInteger('user_id');
                $table->primary(['message_id', 'user_id']);
                $table->index('user_id', 'idx_chat_pending_users_user');
            });
        }
    }

    public function down(): void
    {
        // Pending delivery records are retained with the encrypted message history.
    }
};
