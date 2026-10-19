<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('helpdesk_users')) {
            Schema::create('helpdesk_users', function (Blueprint $table): void {
                $table->id();
                $table->string('display_name', 120);
                $table->string('username', 80)->nullable()->unique();
                $table->string('email', 190);
                $table->string('password_hash');
                $table->enum('role', ['admin', 'staff'])->default('staff');
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->index(['role', 'is_active'], 'idx_helpdesk_users_role_active');
            });
        }

        if (!Schema::hasTable('helpdesk_tickets')) {
            Schema::create('helpdesk_tickets', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('submitted_by');
                $table->string('requester_name', 120);
                $table->string('requester_email', 190);
                $table->string('subject', 160);
                $table->text('description');
                $table->enum('status', ['new', 'in_progress', 'resolved'])->default('new');
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->index(['status', 'created_at'], 'idx_helpdesk_tickets_status_created');
                $table->index('resolved_at', 'idx_helpdesk_tickets_resolved_at');
                $table->index('submitted_by', 'idx_helpdesk_tickets_submitted_by');
            });
        }
    }

    public function down(): void
    {
        // Existing helpdesk data is intentionally never removed by rollback.
    }
};
