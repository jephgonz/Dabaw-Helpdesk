<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('helpdesk_accomplishments')
            ->where('status', 'in_progress')
            ->update(['status' => 'todo']);
    }

    public function down(): void
    {
        // Existing tasks remain valid as "To do"; their previous status cannot be inferred.
    }
};
