<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('helpdesk_settings')) {
            Schema::create('helpdesk_settings', function (Blueprint $table): void {
                $table->string('setting_key', 100)->primary();
                $table->text('setting_value');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_settings');
    }
};
