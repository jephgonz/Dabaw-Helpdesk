<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('helpdesk_notices') && !Schema::hasColumn('helpdesk_notices', 'attachment_path')) {
            Schema::table('helpdesk_notices', function (Blueprint $table): void {
                $table->string('attachment_path')->nullable()->after('body');
            });
        }
    }

    public function down(): void
    {
        // Preserve memo attachments during rollback.
    }
};
