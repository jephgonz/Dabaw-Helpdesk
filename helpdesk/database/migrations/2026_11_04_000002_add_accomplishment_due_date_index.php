<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('helpdesk_accomplishments')) {
            return;
        }

        foreach (Schema::getIndexes('helpdesk_accomplishments') as $index) {
            if ($index['columns'] === ['created_by', 'status', 'due_date']) {
                return;
            }
        }

        Schema::table('helpdesk_accomplishments', function (Blueprint $table): void {
            $table->index(['created_by', 'status', 'due_date'], 'idx_accomplishments_owner_due');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('helpdesk_accomplishments')) {
            return;
        }

        foreach (Schema::getIndexes('helpdesk_accomplishments') as $index) {
            if (($index['name'] ?? null) === 'idx_accomplishments_owner_due') {
                Schema::table('helpdesk_accomplishments', function (Blueprint $table): void {
                    $table->dropIndex('idx_accomplishments_owner_due');
                });
                return;
            }
        }
    }
};
