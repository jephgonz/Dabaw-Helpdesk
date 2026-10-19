<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_is_rendered_by_inertia(): void
    {
        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('type', 'staff'));
    }

    public function test_admin_login_view_can_be_selected(): void
    {
        $this->get('/login?type=admin')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('type', 'admin'));
    }

    public function test_admin_dashboard_requires_an_admin_session(): void
    {
        $this->get('/admin')->assertRedirect('/login?type=admin');
    }

    public function test_admin_notices_require_an_admin_session(): void
    {
        $this->get('/admin/notices')->assertRedirect('/login?type=admin');
    }

    public function test_staff_dashboard_requires_a_staff_session(): void
    {
        $this->get('/dashboard')->assertRedirect('/login?type=staff');
    }

    public function test_legacy_staff_session_receives_username_in_shared_profile_data(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('username')->nullable();
            $table->string('role');
            $table->boolean('is_active');
        });
        DB::table('helpdesk_users')->insert([
            'id' => 1,
            'username' => 'staff.one',
            'role' => 'staff',
            'is_active' => 1,
        ]);
        $taskMigration = require database_path('migrations/2026_10_19_000006_create_task_board_table.php');
        $taskMigration->up();
        foreach (['todo', 'in_progress', 'finished', 'todo'] as $status) {
            DB::table('helpdesk_tasks')->insert([
                'board_user_id' => 1,
                'created_by' => 1,
                'title' => "Task {$status}",
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->withSession(['user' => [
            'id' => 1,
            'name' => 'Staff One',
            'role' => 'staff',
        ]])->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Dashboard')
                ->where('auth.user.username', 'staff.one')
                ->where('taskStatusCounts.todo', 2)
                ->where('taskStatusCounts.in_progress', 1)
                ->where('taskStatusCounts.finished', 1));

        Schema::drop('helpdesk_tasks');
        Schema::drop('helpdesk_users');
    }

    public function test_staff_support_requests_require_a_staff_session(): void
    {
        $this->get('/dashboard/requests')->assertRedirect('/login?type=staff');
    }

    public function test_chat_is_available_to_all_roles_but_requires_login(): void
    {
        $this->get('/chat')->assertRedirect('/login?type=staff');

        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('role');
            $table->boolean('is_active');
        });
        DB::table('helpdesk_users')->insert(['id' => 1, 'role' => 'staff', 'is_active' => 1]);
        $this->withSession(['user' => ['id' => 1, 'name' => 'Staff', 'role' => 'staff']])
            ->get('/chat')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Chat/Index'));
        Schema::drop('helpdesk_users');
    }

    public function test_admin_sidebar_count_is_shared_with_other_admin_views(): void
    {
        $admin = Mockery::mock();
        $admin->shouldReceive('where')->once()->with('id', 1)->andReturnSelf();
        $admin->shouldReceive('where')->once()->with('role', 'admin')->andReturnSelf();
        $admin->shouldReceive('where')->once()->with('is_active', 1)->andReturnSelf();
        $admin->shouldReceive('exists')->once()->andReturnTrue();

        $tickets = Mockery::mock();
        $tickets->shouldReceive('where')->once()->with('status', 'new')->andReturnSelf();
        $tickets->shouldReceive('count')->once()->andReturn(4);
        DB::shouldReceive('table')->once()->with('helpdesk_users')->andReturn($admin);
        DB::shouldReceive('table')->once()->with('helpdesk_tickets')->andReturn($tickets);

        $this->withSession(['user' => [
            'id' => 1,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]])->get('/admin/tools/id-generator')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/IdGenerator')
                ->where('newTicketCount', 4));
    }

    public function test_notice_migration_creates_the_notice_board_table(): void
    {
        $migration = require database_path('migrations/2026_10_19_000001_create_helpdesk_notices_table.php');
        $migration->up();
        $attachmentMigration = require database_path('migrations/2026_10_19_000002_add_attachment_to_helpdesk_notices.php');
        $attachmentMigration->up();

        $this->assertTrue(Schema::hasTable('helpdesk_notices'));
        $this->assertEqualsCanonicalizing(
            ['id', 'posted_by', 'title', 'body', 'attachment_path', 'created_at', 'updated_at'],
            Schema::getColumnListing('helpdesk_notices')
        );

        Schema::drop('helpdesk_notices');
    }

    public function test_admin_can_post_a_scanned_notice_and_staff_can_view_its_image(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('role');
            $table->boolean('is_active');
        });
        Schema::create('helpdesk_notices', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('posted_by');
            $table->string('title', 180);
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->timestamps();
        });
        DB::table('helpdesk_users')->insert([
            ['id' => 1, 'role' => 'admin', 'is_active' => 1],
            ['id' => 2, 'role' => 'staff', 'is_active' => 1],
        ]);
        Storage::fake('local');

        $this->withSession(['user' => [
            'id' => 1,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]])->post('/admin/notices', [
            'title' => 'Office memo',
            'body' => 'Please review the attached memo.',
            'attachment' => UploadedFile::fake()->image('memo.png'),
        ])->assertRedirect()
            ->assertSessionHas('success');

        $notice = DB::table('helpdesk_notices')->first();
        $this->assertNotNull($notice->attachment_path);
        Storage::disk('local')->assertExists($notice->attachment_path);

        $this->withSession(['user' => [
            'id' => 2,
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'role' => 'staff',
        ]])->get("/dashboard/notices/{$notice->id}/attachment")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        Schema::drop('helpdesk_notices');
        Schema::drop('helpdesk_users');
    }

    public function test_accounts_can_exchange_plain_text_messages_without_device_registration(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('display_name');
            $table->string('role');
            $table->boolean('is_active');
        });
        DB::table('helpdesk_users')->insert([
            ['id' => 1, 'display_name' => 'Admin', 'role' => 'admin', 'is_active' => 1],
            ['id' => 2, 'display_name' => 'Staff', 'role' => 'staff', 'is_active' => 1],
            ['id' => 3, 'display_name' => 'Other staff', 'role' => 'staff', 'is_active' => 1],
            ['id' => 4, 'display_name' => 'Inactive staff', 'role' => 'staff', 'is_active' => 0],
        ]);
        $encryptedMigration = require database_path('migrations/2026_10_19_000003_create_encrypted_chat_tables.php');
        $encryptedMigration->up();
        $pendingMigration = require database_path('migrations/2026_10_19_000004_create_chat_pending_deliveries_table.php');
        $pendingMigration->up();
        $simpleMigration = require database_path('migrations/2026_10_19_000005_simplify_chat_for_plain_messages.php');
        $simpleMigration->up();

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->getJson('/chat/state')
            ->assertOk()
            ->assertJsonStructure(['csrfToken'])
            ->assertJsonCount(2, 'users')
            ->assertJsonPath('users.0.name', 'Other staff');

        $conversationId = $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->postJson('/chat/conversations', ['user_id' => 2])
            ->assertCreated()
            ->json('conversation_id');
        $sameConversationId = $this->withSession(['user' => ['id' => 2, 'name' => 'Staff', 'role' => 'staff']])
            ->postJson('/chat/conversations', ['user_id' => 1])
            ->assertCreated()
            ->json('conversation_id');
        $this->assertSame($conversationId, $sameConversationId);

        $messageId = $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->postJson("/chat/conversations/{$conversationId}/messages", ['body' => 'Hello from the server-stored chat.'])
            ->assertCreated()
            ->json('message_id');
        $this->assertDatabaseHas('chat_messages', [
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'sender_id' => 1,
            'body' => 'Hello from the server-stored chat.',
        ]);

        $this->withSession(['user' => ['id' => 2, 'name' => 'Staff', 'role' => 'staff']])
            ->getJson("/chat/conversations/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.id', $messageId)
            ->assertJsonPath('messages.0.body', 'Hello from the server-stored chat.');
        $this->withSession(['user' => ['id' => 3, 'name' => 'Other staff', 'role' => 'staff']])
            ->getJson("/chat/conversations/{$conversationId}/messages")
            ->assertNotFound();
        $this->assertFalse(Schema::hasTable('chat_devices'));
        $this->assertFalse(Schema::hasTable('chat_message_keys'));
        $this->assertFalse(Schema::hasTable('chat_participants'));

        Schema::drop('chat_messages');
        Schema::drop('chat_conversations');
        Schema::drop('helpdesk_users');
    }

    public function test_staff_can_add_to_staff_boards_but_only_board_owner_can_change_task_status(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('display_name');
            $table->string('username')->nullable();
            $table->string('role');
            $table->boolean('is_active');
        });
        DB::table('helpdesk_users')->insert([
            ['id' => 1, 'display_name' => 'Admin One', 'username' => null, 'role' => 'admin', 'is_active' => 1],
            ['id' => 2, 'display_name' => 'Staff Owner', 'username' => 'owner', 'role' => 'staff', 'is_active' => 1],
            ['id' => 3, 'display_name' => 'Staff Peer', 'username' => 'peer', 'role' => 'staff', 'is_active' => 1],
        ]);
        $migration = require database_path('migrations/2026_10_19_000006_create_task_board_table.php');
        $migration->up();

        $this->withSession(['user' => ['id' => 3, 'name' => 'Staff Peer', 'role' => 'staff']])
            ->get('/tasks?search=Owner')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->where('board.id', 3)
                ->where('users.0.username', 'owner')
                ->missing('users.0.email'));

        $this->withSession(['user' => ['id' => 3, 'name' => 'Staff Peer', 'role' => 'staff']])
            ->get('/tasks?board=1')
            ->assertNotFound();

        $this->withSession(['user' => ['id' => 3, 'name' => 'Staff Peer', 'role' => 'staff']])
            ->post('/tasks', ['board_user_id' => 1, 'title' => 'Admin task'])
            ->assertForbidden();

        $this->withSession(['user' => ['id' => 3, 'name' => 'Staff Peer', 'role' => 'staff']])
            ->post('/tasks', [
                'board_user_id' => 2,
                'title' => 'Prepare report',
                'description' => 'Gather this week’s figures.',
            ])->assertRedirect()
            ->assertSessionHas('success', 'Task added to the board.');

        $taskId = (int) DB::table('helpdesk_tasks')->value('id');
        $this->assertDatabaseHas('helpdesk_tasks', [
            'id' => $taskId,
            'board_user_id' => 2,
            'created_by' => 3,
            'status' => 'todo',
        ]);

        $this->withSession(['user' => ['id' => 3, 'name' => 'Staff Peer', 'role' => 'staff']])
            ->patch("/tasks/{$taskId}/status", ['status' => 'in_progress'])
            ->assertForbidden();

        $this->withSession(['user' => ['id' => 2, 'name' => 'Staff Owner', 'role' => 'staff']])
            ->patch("/tasks/{$taskId}/status", ['status' => 'in_progress'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Task status updated.');

        $this->assertDatabaseHas('helpdesk_tasks', ['id' => $taskId, 'status' => 'in_progress']);
        Schema::drop('helpdesk_tasks');
        Schema::drop('helpdesk_users');
    }

    public function test_admin_can_manage_tasks_on_any_user_board(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('display_name');
            $table->string('username')->nullable();
            $table->string('role');
            $table->boolean('is_active');
        });
        DB::table('helpdesk_users')->insert([
            ['id' => 1, 'display_name' => 'Admin One', 'username' => null, 'role' => 'admin', 'is_active' => 1],
            ['id' => 2, 'display_name' => 'Staff One', 'username' => 'staff.one', 'role' => 'staff', 'is_active' => 1],
        ]);
        $migration = require database_path('migrations/2026_10_19_000006_create_task_board_table.php');
        $migration->up();

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->get('/tasks?search=Admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->where('users.0.role', 'admin'));

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->post('/tasks', ['board_user_id' => 2, 'title' => 'Review request'])
            ->assertRedirect();

        $taskId = (int) DB::table('helpdesk_tasks')->value('id');
        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->post("/tasks/{$taskId}/update", ['title' => 'Review support request', 'description' => 'Prioritize this.'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Task updated.');

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->patch("/tasks/{$taskId}/status", ['status' => 'finished'])
            ->assertRedirect();
        $this->assertDatabaseHas('helpdesk_tasks', [
            'id' => $taskId,
            'title' => 'Review support request',
            'status' => 'finished',
        ]);

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->delete("/tasks/{$taskId}")
            ->assertRedirect()
            ->assertSessionHas('success', 'Task deleted.');
        $this->assertDatabaseMissing('helpdesk_tasks', ['id' => $taskId]);

        Schema::drop('helpdesk_tasks');
        Schema::drop('helpdesk_users');
    }

    public function test_staff_cannot_update_profile_but_admin_can_and_email_can_be_shared(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('display_name');
            $table->string('username')->nullable()->unique();
            $table->string('email');
            $table->string('password_hash');
            $table->string('role');
            $table->boolean('is_active');
            $table->timestamps();
        });
        DB::table('helpdesk_users')->insert([
            ['id' => 1, 'display_name' => 'Staff One', 'username' => 'staff.one', 'email' => 'old@example.com', 'password_hash' => 'hash', 'role' => 'staff', 'is_active' => 1],
            ['id' => 2, 'display_name' => 'Admin One', 'username' => null, 'email' => 'admin@example.com', 'password_hash' => 'hash', 'role' => 'admin', 'is_active' => 1],
            ['id' => 3, 'display_name' => 'Staff Two', 'username' => 'staff.two', 'email' => 'same@example.com', 'password_hash' => 'hash', 'role' => 'staff', 'is_active' => 1],
        ]);

        $this->withSession(['user' => [
            'id' => 1,
            'name' => 'Staff One',
            'username' => 'staff.one',
            'email' => 'old@example.com',
            'role' => 'staff',
        ]])->post('/profile', [
            'display_name' => 'Updated Name',
            'username' => 'updated.staff',
            'email' => 'same@example.com',
        ])->assertForbidden();

        $this->assertDatabaseHas('helpdesk_users', [
            'id' => 1,
            'display_name' => 'Staff One',
            'username' => 'staff.one',
            'email' => 'old@example.com',
        ]);

        $this->withSession(['user' => [
            'id' => 2,
            'name' => 'Admin One',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]])->post('/profile', [
            'display_name' => 'Updated Admin',
            'email' => 'same@example.com',
        ])->assertRedirect()
            ->assertSessionHas('success', 'Profile updated.')
            ->assertSessionHas('user.name', 'Updated Admin');

        $this->assertDatabaseHas('helpdesk_users', [
            'id' => 2,
            'display_name' => 'Updated Admin',
            'email' => 'same@example.com',
        ]);

        Schema::drop('helpdesk_users');
    }
}
