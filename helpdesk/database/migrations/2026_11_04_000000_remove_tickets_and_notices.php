<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $attachments = Storage::disk('local')->allFiles('notice-attachments');
        if ($attachments !== [] && ! Storage::disk('local')->delete($attachments)) {
            throw new RuntimeException('Notice attachments could not be removed from storage.');
        }

        Schema::dropIfExists('helpdesk_tickets');
        Schema::dropIfExists('helpdesk_notices');

        if (Schema::hasTable('helpdesk_notification_settings')
            && Schema::hasColumn('helpdesk_notification_settings', 'new_support_requests')) {
            Schema::table('helpdesk_notification_settings', function (Blueprint $table): void {
                $table->dropColumn('new_support_requests');
            });
        }
    }

    public function down(): void
    {
        throw new LogicException('Deleted support requests, notices, and notice attachments cannot be restored.');
    }
};
