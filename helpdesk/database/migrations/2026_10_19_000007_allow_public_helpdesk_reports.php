<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('helpdesk_tickets') && Schema::hasColumn('helpdesk_tickets', 'submitted_by')) {
            Schema::table('helpdesk_tickets', function (Blueprint $table): void {
                $table->unsignedBigInteger('submitted_by')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Anonymous reports must remain valid after rollback.
    }
};
