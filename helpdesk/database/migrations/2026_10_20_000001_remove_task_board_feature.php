<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('helpdesk_tasks');
    }

    public function down(): void
    {
        throw new LogicException('Removing task boards and their data cannot be rolled back.');
    }
};
