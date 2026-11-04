<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_notification_settings', function (Blueprint $table): void {
            $table->unsignedBigInteger('admin_id')->primary();
            $table->boolean('enabled')->default(false);
            $table->boolean('task_due')->default(true);
            $table->boolean('new_support_requests')->default(true);
            $table->boolean('ticket_status_changes')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_notification_settings');
    }
};
