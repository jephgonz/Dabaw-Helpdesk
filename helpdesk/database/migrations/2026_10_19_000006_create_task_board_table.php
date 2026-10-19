<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('helpdesk_tasks')) {
            return;
        }

        Schema::create('helpdesk_tasks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('board_user_id');
            $table->unsignedBigInteger('created_by');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('todo');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index(['board_user_id', 'status', 'created_at'], 'idx_helpdesk_tasks_board_status');
            $table->index('created_by', 'idx_helpdesk_tasks_created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_tasks');
    }
};
