<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'chat_message_pending_users',
            'chat_message_archives',
            'chat_message_keys',
            'chat_messages',
            'chat_participants',
            'chat_conversations',
            'chat_devices',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasTable('helpdesk_users')
            && Schema::hasColumn('helpdesk_users', 'role')
            && Schema::hasColumn('helpdesk_users', 'is_active')) {
            DB::table('helpdesk_users')
                ->where('role', 'staff')
                ->update(['is_active' => 0]);

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE helpdesk_users ALTER COLUMN role SET DEFAULT 'admin'");
            }
        }
    }

    public function down(): void
    {
        throw new LogicException('Removing chat history and disabling staff accounts cannot be rolled back.');
    }
};
