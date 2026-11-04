<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TicketUpdateTest extends TestCase
{
    public function test_admin_can_edit_support_request_details(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('role');
            $table->boolean('is_active');
        });
        DB::table('helpdesk_users')->insert(['id' => 1, 'role' => 'admin', 'is_active' => true]);
        Schema::create('helpdesk_tickets', function ($table): void {
            $table->id();
            $table->string('requester_name');
            $table->string('requester_email');
            $table->string('subject');
            $table->text('description');
            $table->string('status');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::table('helpdesk_tickets')->insert([
            'id' => 1,
            'requester_name' => 'Original User',
            'requester_email' => 'original@example.com',
            'subject' => 'Original subject',
            'description' => 'Original details',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->post('/admin/tickets/1', [
                'requester_name' => 'Updated User',
                'requester_email' => 'UPDATED@example.com',
                'subject' => 'Updated subject',
                'description' => 'Updated details',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('helpdesk_tickets', [
            'id' => 1,
            'requester_name' => 'Updated User',
            'requester_email' => 'updated@example.com',
            'subject' => 'Updated subject',
            'description' => 'Updated details',
            'status' => 'new',
        ]);

        Schema::drop('helpdesk_tickets');
        Schema::drop('helpdesk_users');
    }
}
