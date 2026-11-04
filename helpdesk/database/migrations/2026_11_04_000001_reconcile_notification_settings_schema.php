<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('helpdesk_notification_settings')
            && Schema::hasColumn('helpdesk_notification_settings', 'ticket_status_changes')) {
            Schema::table('helpdesk_notification_settings', function (Blueprint $table): void {
                $table->dropColumn('ticket_status_changes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('helpdesk_notification_settings')
            && ! Schema::hasColumn('helpdesk_notification_settings', 'ticket_status_changes')) {
            Schema::table('helpdesk_notification_settings', function (Blueprint $table): void {
                $table->boolean('ticket_status_changes')->default(true);
            });
        }
    }
};
