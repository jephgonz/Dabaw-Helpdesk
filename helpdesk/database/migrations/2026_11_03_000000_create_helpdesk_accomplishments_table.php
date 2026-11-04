<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_accomplishments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('todo');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['created_by', 'status', 'created_at'], 'idx_accomplishments_owner_status');
            $table->index(['created_by', 'completed_at'], 'idx_accomplishments_owner_completed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_accomplishments');
    }
};
