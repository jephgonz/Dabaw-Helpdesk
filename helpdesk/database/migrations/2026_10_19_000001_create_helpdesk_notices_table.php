<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('helpdesk_notices')) {
            Schema::create('helpdesk_notices', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('posted_by');
                $table->string('title', 180);
                $table->text('body');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->index('created_at', 'idx_helpdesk_notices_created_at');
            });
        }
    }

    public function down(): void
    {
        // Preserve announcements during rollback.
    }
};
