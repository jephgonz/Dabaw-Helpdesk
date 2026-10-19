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
    public function test_public_landing_page_is_rendered_at_root(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Landing/Index'));
    }

    public function test_public_landing_page_shows_latest_notices(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('display_name');
        });
        Schema::create('helpdesk_notices', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('posted_by');
            $table->string('title');
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->timestamps();
        });
        DB::table('helpdesk_users')->insert(['id' => 1, 'display_name' => 'Administrator']);
        DB::table('helpdesk_notices')->insert([
            'id' => 1,
            'posted_by' => 1,
            'title' => 'Service update',
            'body' => 'The helpdesk will be available as usual.',
            'attachment_path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Landing/Index')
            ->has('notices', 1)
            ->where('notices.0.title', 'Service update')
            ->where('notices.0.author', 'Administrator'));

        Schema::drop('helpdesk_notices');
        Schema::drop('helpdesk_users');
    }

    public function test_login_url_returns_to_public_landing_page(): void
    {
        $this->get('/login')->assertRedirect('/');
    }

    public function test_only_administrator_login_page_is_available(): void
    {
        $this->get('/admin/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login'));
    }

    public function test_staff_credentials_cannot_sign_in(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('display_name');
            $table->string('username')->nullable();
            $table->string('email');
            $table->string('password_hash');
            $table->string('role');
            $table->boolean('is_active');
        });
        DB::table('helpdesk_users')->insert([
            'id' => 1,
            'display_name' => 'Staff Member',
            'username' => 'staff.member',
            'email' => 'staff@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'role' => 'staff',
            'is_active' => 1,
        ]);

        $this->from('/admin/login')->post('/admin/login', [
            'login_action' => 'admin_login',
            'email' => 'staff@example.com',
            'password' => 'secret',
        ])->assertRedirect('/admin/login')
            ->assertSessionHasErrors('login');

        $this->assertNull(session('user'));
        Schema::drop('helpdesk_users');
    }

    public function test_admin_dashboard_requires_an_admin_session(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_notices_require_an_admin_session(): void
    {
        $this->get('/admin/notices')->assertRedirect('/admin/login');
    }

    public function test_staff_dashboard_redirects_to_public_helpdesk(): void
    {
        $this->get('/dashboard')->assertRedirect('/');
    }

    public function test_legacy_staff_session_is_removed_from_dashboard_access(): void
    {
        $this->withSession(['user' => [
            'id' => 1,
            'name' => 'Staff One',
            'role' => 'staff',
        ]])->get('/dashboard')
            ->assertRedirect('/');
    }

    public function test_staff_cannot_access_admin_task_boards(): void
    {
        $session = ['user' => ['id' => 1, 'name' => 'Staff One', 'username' => 'staff.one', 'role' => 'staff']];

        $this->withSession($session)->get('/tasks')->assertRedirect('/admin/login');
        $this->withSession($session)->post('/tasks', ['board_user_id' => 1, 'title' => 'Task'])->assertRedirect('/admin/login');
        $this->withSession($session)->patch('/tasks/1/status', ['status' => 'finished'])->assertRedirect('/admin/login');
        $this->withSession($session)->delete('/tasks/1')->assertRedirect('/admin/login');
    }

    public function test_legacy_staff_support_route_redirects_to_public_report_form(): void
    {
        $this->get('/dashboard/requests')->assertRedirect('/#report');
    }

    public function test_chat_is_available_only_to_logged_in_administrators(): void
    {
        $this->get('/chat')->assertRedirect('/admin/login');

        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('role');
            $table->boolean('is_active');
        });
        DB::table('helpdesk_users')->insert(['id' => 1, 'role' => 'admin', 'is_active' => 1]);
        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->get('/chat')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Chat/Index'));
        $this->withSession(['user' => ['id' => 2, 'name' => 'Staff', 'role' => 'staff']])
            ->get('/chat')
            ->assertRedirect('/admin/login');
        Schema::drop('helpdesk_users');
    }

    public function test_public_report_can_be_submitted_without_a_staff_account(): void
    {
        Schema::create('helpdesk_tickets', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->string('requester_name', 120);
            $table->string('requester_email', 190);
            $table->string('subject', 160);
            $table->text('description');
            $table->string('status')->default('new');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        $this->post('/reports', [
            'requester_name' => 'Public Reporter',
            'requester_email' => 'reporter@example.com',
            'subject' => 'Cannot access printer',
            'description' => 'The office printer is offline.',
            'website' => '',
        ])->assertRedirect()
            ->assertSessionHas('success', 'Your report was sent to the helpdesk. Thank you.');

        $this->assertDatabaseHas('helpdesk_tickets', [
            'submitted_by' => null,
            'requester_name' => 'Public Reporter',
            'requester_email' => 'reporter@example.com',
            'status' => 'new',
        ]);

        Schema::drop('helpdesk_tickets');
    }

    public function test_public_report_migration_allows_null_submitter(): void
    {
        Schema::create('helpdesk_tickets', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('submitted_by');
        });
        $migration = require database_path('migrations/2026_10_19_000007_allow_public_helpdesk_reports.php');
        $migration->up();

        DB::table('helpdesk_tickets')->insert(['submitted_by' => null]);
        $this->assertDatabaseHas('helpdesk_tickets', ['submitted_by' => null]);

        Schema::drop('helpdesk_tickets');
    }

    public function test_cache_migration_creates_laravel_database_cache_tables(): void
    {
        $migration = require database_path('migrations/2026_10_19_000008_create_cache_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('cache_locks'));
        $this->assertEqualsCanonicalizing(
            ['key', 'value', 'expiration'],
            Schema::getColumnListing('cache')
        );
        $this->assertEqualsCanonicalizing(
            ['key', 'owner', 'expiration'],
            Schema::getColumnListing('cache_locks')
        );

        Schema::drop('cache_locks');
        Schema::drop('cache');
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

    public function test_admin_can_post_a_scanned_notice_and_public_can_view_its_image(): void
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
        ]])->get("/notices/{$notice->id}/attachment")
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
            ['id' => 2, 'display_name' => 'Other Admin', 'role' => 'admin', 'is_active' => 1],
            ['id' => 3, 'display_name' => 'Other staff', 'role' => 'staff', 'is_active' => 1],
            ['id' => 4, 'display_name' => 'Inactive staff', 'role' => 'staff', 'is_active' => 0],
            ['id' => 5, 'display_name' => 'Third Admin', 'role' => 'admin', 'is_active' => 1],
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
            ->assertJsonPath('users.0.name', 'Other Admin');

        $conversationId = $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->postJson('/chat/conversations', ['user_id' => 2])
            ->assertCreated()
            ->json('conversation_id');
        $sameConversationId = $this->withSession(['user' => ['id' => 2, 'name' => 'Other Admin', 'role' => 'admin']])
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

        $this->withSession(['user' => ['id' => 2, 'name' => 'Other Admin', 'role' => 'admin']])
            ->getJson("/chat/conversations/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.id', $messageId)
            ->assertJsonPath('messages.0.body', 'Hello from the server-stored chat.');
        $this->withSession(['user' => ['id' => 5, 'name' => 'Third Admin', 'role' => 'admin']])
            ->getJson("/chat/conversations/{$conversationId}/messages")
            ->assertNotFound();
        $this->withSession(['user' => ['id' => 3, 'name' => 'Other staff', 'role' => 'staff']])
            ->getJson('/chat/state')
            ->assertRedirect('/admin/login');
        $this->assertFalse(Schema::hasTable('chat_devices'));
        $this->assertFalse(Schema::hasTable('chat_message_keys'));
        $this->assertFalse(Schema::hasTable('chat_participants'));

        Schema::drop('chat_messages');
        Schema::drop('chat_conversations');
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
        ])->assertRedirect('/admin/login');

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
