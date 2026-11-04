<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_and_admin_login_render_the_sign_in_page(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login'));
        $this->get('/admin/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login'));
    }

    public function test_login_url_redirects_to_the_sign_in_page(): void
    {
        $this->get('/login')->assertRedirect('/');
    }

    public function test_ticketing_and_notice_routes_are_removed(): void
    {
        $this->get('/admin/tickets')->assertNotFound();
        $this->get('/admin/notices')->assertNotFound();
        $this->post('/reports')->assertNotFound();
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

    public function test_account_creation_only_creates_administrators(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('display_name');
            $table->string('username')->nullable();
            $table->string('email');
            $table->string('password_hash');
            $table->string('role');
            $table->boolean('is_active');
            $table->timestamps();
        });
        DB::table('helpdesk_users')->insert([
            ['id' => 1, 'display_name' => 'Admin', 'username' => null, 'email' => 'admin@example.com', 'password_hash' => 'hash', 'role' => 'admin', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'display_name' => 'Legacy Staff', 'username' => 'legacy.staff', 'email' => 'staff@example.com', 'password_hash' => 'hash', 'role' => 'staff', 'is_active' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $session = ['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']];

        $this->withSession($session)->post('/admin/users', [
            'display_name' => 'New Administrator',
            'email' => 'new-admin@example.com',
            'password' => 'a-long-admin-password',
            'role' => 'staff',
            'username' => 'requested.staff',
        ])->assertRedirect('/admin/users');

        $this->assertDatabaseHas('helpdesk_users', [
            'display_name' => 'New Administrator',
            'email' => 'new-admin@example.com',
            'role' => 'admin',
            'username' => null,
            'is_active' => 1,
        ]);

        $this->withSession($session)->post('/admin/users/status', [
            'user_id' => 2,
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHasErrors('form');
        $this->assertDatabaseHas('helpdesk_users', ['id' => 2, 'role' => 'staff', 'is_active' => 0]);

        Schema::drop('helpdesk_users');
    }

    public function test_admin_dashboard_requires_an_admin_session(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_overview_shows_only_the_current_admin_open_tasks(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('display_name');
            $table->string('username')->nullable();
            $table->string('email');
            $table->string('role');
            $table->boolean('is_active');
            $table->timestamp('created_at')->nullable();
        });
        DB::table('helpdesk_users')->insert([
            ['id' => 1, 'display_name' => 'Admin One', 'username' => null, 'email' => 'admin@example.com', 'role' => 'admin', 'is_active' => 1, 'created_at' => now()],
            ['id' => 2, 'display_name' => 'Admin Two', 'username' => null, 'email' => 'other@example.com', 'role' => 'admin', 'is_active' => 1, 'created_at' => now()],
        ]);
        Schema::create('helpdesk_accomplishments', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('todo');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        DB::table('helpdesk_accomplishments')->insert([
            ['created_by' => 1, 'title' => 'Prepare report', 'description' => null, 'due_date' => '2026-11-10', 'status' => 'todo', 'completed_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['created_by' => 1, 'title' => 'Review requests', 'description' => null, 'due_date' => '2026-11-05', 'status' => 'todo', 'completed_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['created_by' => 1, 'title' => 'Finished work', 'description' => null, 'due_date' => null, 'status' => 'completed', 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['created_by' => 2, 'title' => 'Another admin task', 'description' => null, 'due_date' => null, 'status' => 'todo', 'completed_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('accountCounts.total', 2)
                ->where('accountCounts.active', 2)
                ->where('accountCounts.administrators', 2)
                ->where('openTaskCount', 2)
                ->where('pendingTaskCount', 2)
                ->where('completedThisQuarterCount', 1)
                ->has('pendingTasks', 2)
                ->where('pendingTasks.0.title', 'Review requests')
                ->where('pendingTasks.1.title', 'Prepare report'));

        Schema::drop('helpdesk_accomplishments');
        Schema::drop('helpdesk_users');
    }

    public function test_monthly_allocation_planner_is_an_admin_dashboard_page(): void
    {
        $this->createMonthlyPlannerAdmin();

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->get('/admin/tools/monthly-allocation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MonthlyAllocationPlanner')
                ->where('activePage', 'monthly-allocation')
                ->where('monthlyTarget', 4500)
                ->where('weekdayAllocation', 200));

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->postJson('/admin/tools/monthly-allocation/generate', ['month' => '2026-08'])
            ->assertNotFound();

        Schema::drop('helpdesk_users');
    }

    public function test_monthly_allocation_planner_does_not_expose_receipt_generation(): void
    {
        $this->createMonthlyPlannerAdmin();

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->postJson('/admin/tools/receipt-generator/generate')
            ->assertNotFound();

        Schema::drop('helpdesk_users');
    }

    public function test_monthly_csv_download_rejects_an_invalid_month(): void
    {
        $this->createMonthlyPlannerAdmin();

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->postJson('/admin/tools/monthly-allocation/download', ['month' => 'not-a-month'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('month');

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->postJson('/admin/tools/monthly-allocation/download', [
                'month' => '2026-08',
                'target' => 0,
                'base_amount' => 200,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target');

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->postJson('/admin/tools/monthly-allocation/download', [
                'month' => '2026-08',
                'target' => 4500,
                'base_amount' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('base_amount');

        Schema::drop('helpdesk_settings');
        Schema::drop('helpdesk_users');
    }

    public function test_monthly_csv_download_matches_example_columns_and_allocation_rules(): void
    {
        $this->createMonthlyPlannerAdmin();
        $adDirectory = storage_path('framework/testing/monthly-plan-ads');
        File::ensureDirectoryExists($adDirectory);
        File::put($adDirectory.DIRECTORY_SEPARATOR.'ad1.png', 'ad');
        File::put($adDirectory.DIRECTORY_SEPARATOR.'ad2.png', 'ad');
        $adPaths = [
            realpath($adDirectory.DIRECTORY_SEPARATOR.'ad1.png'),
            realpath($adDirectory.DIRECTORY_SEPARATOR.'ad2.png'),
        ];
        $references = array_fill(0, 21, '123456789');
        $selectedAds = array_map(
            static fn (int $index): string => $adPaths[$index % count($adPaths)],
            range(0, 20),
        );
        config([
            'monthly-allocation.ad_directory' => $adDirectory,
            'monthly-allocation.number' => '0910 822 1327',
        ]);

        $response = $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->post('/admin/tools/monthly-allocation/download', [
                'month' => '2026-08',
                'target' => 5000,
                'base_amount' => 200,
                'references' => $references,
                'ads' => $selectedAds,
            ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'monthly-plan-August-2026.csv',
            $response->headers->get('content-disposition')
        );
        $lines = preg_split('/\r\n|\n|\r/', trim($response->streamedContent()));
        $rows = array_map(static fn (string $line): array => str_getcsv($line), $lines);

        $this->assertSame(['number', 'datetime', 'value', 'refnumber', 'ads'], $rows[0]);
        $this->assertCount(22, $rows);
        $dataRows = array_slice($rows, 1);
        foreach ($dataRows as $index => $row) {
            $this->assertSame('0910 822 1327', $row[0]);
            $this->assertMatchesRegularExpression('/^Aug \d{2}, 2026 (?:8|9|10|11):[0-5]\d AM$/', $row[1]);
            $timestamp = strtotime($row[1]);
            $this->assertNotFalse($timestamp);
            $this->assertLessThanOrEqual(5, (int) date('N', $timestamp));
            $expectedAmount = $index >= count($dataRows) - 2 ? '600' : '200';
            $this->assertSame($expectedAmount, $row[2]);
            $this->assertSame($references[$index], $row[3]);
            $this->assertSame($selectedAds[$index], $row[4]);
        }
        $this->assertSame(5000, array_sum(array_map(static fn (array $row): int => (int) $row[2], $dataRows)));
        $this->assertDatabaseHas('helpdesk_settings', [
            'setting_key' => 'monthly_target',
            'setting_value' => '5000',
        ]);
        $this->assertDatabaseHas('helpdesk_settings', [
            'setting_key' => 'weekday_allocation',
            'setting_value' => '200',
        ]);
        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->get('/admin/tools/monthly-allocation')
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthlyTarget', 5000)
                ->where('weekdayAllocation', 200));

        $splitThresholdResponse = $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->post('/admin/tools/monthly-allocation/download', [
                'month' => '2026-08',
                'target' => 4500,
                'base_amount' => 200,
                'references' => $references,
                'ads' => $selectedAds,
            ]);
        $thresholdRows = array_map(
            static fn (string $line): array => str_getcsv($line),
            preg_split('/\r\n|\n|\r/', trim($splitThresholdResponse->streamedContent()))
        );
        $this->assertSame(['350', '350'], array_column(array_slice($thresholdRows, -2), 2));

        $customBaseResponse = $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->post('/admin/tools/monthly-allocation/download', [
                'month' => '2026-08',
                'target' => 4500,
                'base_amount' => 150,
                'references' => $references,
                'ads' => $selectedAds,
            ]);
        $customBaseRows = array_map(
            static fn (string $line): array => str_getcsv($line),
            preg_split('/\r\n|\n|\r/', trim($customBaseResponse->streamedContent()))
        );
        $this->assertSame('150', $customBaseRows[1][2]);
        $this->assertSame(['825', '825'], array_column(array_slice($customBaseRows, -2), 2));
        $this->assertSame(
            4500,
            array_sum(array_map(static fn (array $row): int => (int) $row[2], array_slice($customBaseRows, 1)))
        );
        $this->assertDatabaseHas('helpdesk_settings', [
            'setting_key' => 'weekday_allocation',
            'setting_value' => '150',
        ]);
        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']])
            ->get('/admin/tools/monthly-allocation')
            ->assertInertia(fn (Assert $page) => $page->where('weekdayAllocation', 150));

        File::deleteDirectory($adDirectory);
        Schema::drop('helpdesk_settings');
        Schema::drop('helpdesk_users');
    }

    private function createMonthlyPlannerAdmin(): void
    {
        Schema::dropIfExists('helpdesk_settings');
        Schema::dropIfExists('helpdesk_users');
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('role');
            $table->boolean('is_active');
        });
        DB::table('helpdesk_users')->insert(['id' => 1, 'role' => 'admin', 'is_active' => 1]);
        Schema::create('helpdesk_settings', function ($table): void {
            $table->string('setting_key', 100)->primary();
            $table->text('setting_value');
        });
    }

    public function test_dashboard_redirects_to_sign_in_when_signed_out(): void
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

    public function test_task_board_routes_are_removed(): void
    {
        $session = ['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']];

        $this->withSession($session)->get('/tasks')->assertNotFound();
        $this->withSession($session)->post('/tasks', ['board_user_id' => 1, 'title' => 'Task'])->assertNotFound();
        $this->withSession($session)->patch('/tasks/1/status', ['status' => 'finished'])->assertNotFound();
        $this->withSession($session)->delete('/tasks/1')->assertNotFound();
    }

    public function test_chat_routes_are_removed(): void
    {
        $this->get('/chat')->assertNotFound();
        $this->get('/chat/state')->assertNotFound();
        $this->postJson('/chat/conversations', ['user_id' => 1])->assertNotFound();
    }

    public function test_database_cache_removal_migration_drops_cache_and_lock_tables(): void
    {
        Schema::create('cache', function ($table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        Schema::create('cache_locks', function ($table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        $migration = require database_path('migrations/2026_10_20_000002_remove_database_cache_tables.php');
        $migration->up();

        $this->assertFalse(Schema::hasTable('cache'));
        $this->assertFalse(Schema::hasTable('cache_locks'));
    }

    public function test_ticket_and_notice_cleanup_preserves_workbench_data(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('display_name');
        });
        Schema::create('helpdesk_tickets', function ($table): void {
            $table->id();
            $table->string('subject');
        });
        Schema::create('helpdesk_notices', function ($table): void {
            $table->id();
            $table->string('title');
        });
        Schema::create('helpdesk_notification_settings', function ($table): void {
            $table->unsignedBigInteger('admin_id')->primary();
            $table->boolean('task_due')->default(true);
            $table->boolean('new_support_requests')->default(true);
        });
        Schema::create('helpdesk_settings', function ($table): void {
            $table->string('setting_key')->primary();
            $table->text('setting_value');
        });
        DB::table('helpdesk_users')->insert(['id' => 1, 'display_name' => 'Administrator']);
        DB::table('helpdesk_tickets')->insert(['subject' => 'Old request']);
        DB::table('helpdesk_notices')->insert(['title' => 'Old notice']);
        DB::table('helpdesk_notification_settings')->insert(['admin_id' => 1, 'task_due' => 1, 'new_support_requests' => 1]);
        DB::table('helpdesk_settings')->insert(['setting_key' => 'monthly_target', 'setting_value' => '4500']);
        Storage::fake('local');
        Storage::disk('local')->put('notice-attachments/memo.png', 'notice attachment');
        Storage::disk('local')->put('id-cards/keep.png', 'unrelated file');

        $migration = require database_path('migrations/2026_11_04_000000_remove_tickets_and_notices.php');
        $migration->up();

        $this->assertFalse(Schema::hasTable('helpdesk_tickets'));
        $this->assertFalse(Schema::hasTable('helpdesk_notices'));
        $this->assertDatabaseHas('helpdesk_users', ['id' => 1, 'display_name' => 'Administrator']);
        $this->assertDatabaseHas('helpdesk_settings', ['setting_key' => 'monthly_target', 'setting_value' => '4500']);
        $this->assertFalse(Schema::hasColumn('helpdesk_notification_settings', 'new_support_requests'));
        Storage::disk('local')->assertMissing('notice-attachments/memo.png');
        Storage::disk('local')->assertExists('id-cards/keep.png');
    }

    public function test_chat_removal_migration_deletes_history_and_disables_staff_accounts(): void
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
        foreach ([
            'chat_message_pending_users',
            'chat_message_archives',
            'chat_message_keys',
            'chat_messages',
            'chat_participants',
            'chat_conversations',
            'chat_devices',
        ] as $table) {
            Schema::create($table, function ($table): void {
                $table->id();
            });
        }

        $migration = require database_path('migrations/2026_10_20_000000_remove_chat_and_disable_staff_accounts.php');
        $migration->up();

        foreach ([
            'chat_message_pending_users',
            'chat_message_archives',
            'chat_message_keys',
            'chat_messages',
            'chat_participants',
            'chat_conversations',
            'chat_devices',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertDatabaseHas('helpdesk_users', ['id' => 3, 'role' => 'staff', 'is_active' => 0]);
        $this->assertDatabaseHas('helpdesk_users', ['id' => 4, 'role' => 'staff', 'is_active' => 0]);
        $this->assertDatabaseHas('helpdesk_users', ['id' => 1, 'role' => 'admin', 'is_active' => 1]);

        Schema::drop('helpdesk_users');
    }

    public function test_task_board_removal_migration_deletes_task_data(): void
    {
        Schema::create('helpdesk_tasks', function ($table): void {
            $table->id();
            $table->string('title');
        });
        DB::table('helpdesk_tasks')->insert(['title' => 'Old task']);

        $migration = require database_path('migrations/2026_10_20_000001_remove_task_board_feature.php');
        $migration->up();

        $this->assertFalse(Schema::hasTable('helpdesk_tasks'));
    }

    public function test_accomplishment_migration_creates_its_board_table(): void
    {
        $migration = require database_path('migrations/2026_11_03_000000_create_helpdesk_accomplishments_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('helpdesk_accomplishments'));
        $this->assertTrue(Schema::hasColumns('helpdesk_accomplishments', [
            'created_by',
            'title',
            'description',
            'due_date',
            'status',
            'completed_at',
            'created_at',
            'updated_at',
        ]));

        $migration->down();
        $this->assertFalse(Schema::hasTable('helpdesk_accomplishments'));
    }

    public function test_accomplishment_due_date_index_migration_is_safe_to_rerun(): void
    {
        Schema::create('helpdesk_accomplishments', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->string('status');
            $table->date('due_date')->nullable();
        });

        $migration = require database_path('migrations/2026_11_04_000002_add_accomplishment_due_date_index.php');
        $migration->up();
        $migration->up();

        $matchingIndexes = collect(Schema::getIndexes('helpdesk_accomplishments'))
            ->filter(fn (array $index): bool => $index['columns'] === ['created_by', 'status', 'due_date']);
        $this->assertCount(1, $matchingIndexes);

        $migration->down();
        $matchingIndexes = collect(Schema::getIndexes('helpdesk_accomplishments'))
            ->filter(fn (array $index): bool => $index['columns'] === ['created_by', 'status', 'due_date']);
        $this->assertCount(0, $matchingIndexes);

        Schema::drop('helpdesk_accomplishments');
    }

    public function test_in_progress_accomplishments_migrate_to_todo(): void
    {
        Schema::create('helpdesk_accomplishments', function ($table): void {
            $table->id();
            $table->string('status');
        });
        DB::table('helpdesk_accomplishments')->insert([
            ['id' => 1, 'status' => 'in_progress'],
            ['id' => 2, 'status' => 'todo'],
            ['id' => 3, 'status' => 'completed'],
        ]);

        $migration = require database_path('migrations/2026_11_03_000003_remove_in_progress_accomplishment_status.php');
        $migration->up();

        $this->assertDatabaseHas('helpdesk_accomplishments', ['id' => 1, 'status' => 'todo']);
        $this->assertDatabaseHas('helpdesk_accomplishments', ['id' => 2, 'status' => 'todo']);
        $this->assertDatabaseHas('helpdesk_accomplishments', ['id' => 3, 'status' => 'completed']);

        Schema::drop('helpdesk_accomplishments');
    }

    public function test_task_notification_settings_are_saved_per_administrator(): void
    {
        $this->createNotificationTables();
        $adminOne = ['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']];
        $adminTwo = ['user' => ['id' => 2, 'name' => 'Admin Two', 'role' => 'admin']];

        $this->withSession($adminOne)->getJson('/admin/notifications/settings')
            ->assertOk()
            ->assertExactJson([
                'enabled' => false,
                'task_due' => true,
            ]);

        $this->withSession($adminOne)->postJson('/admin/notifications/settings', [
            'enabled' => true,
            'task_due' => true,
        ])->assertOk()->assertJsonPath('enabled', true)
            ->assertJsonPath('task_due', true);

        $this->withSession($adminTwo)->getJson('/admin/notifications/settings')
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('task_due', true);
        $this->withSession($adminOne)->postJson('/admin/notifications/settings', [
            'enabled' => 'sometimes',
            'task_due' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('enabled');

        Schema::drop('helpdesk_notification_settings');
        Schema::drop('helpdesk_users');
    }

    public function test_browser_notification_settings_migration_creates_and_drops_preferences_table(): void
    {
        $migration = require database_path('migrations/2026_11_03_000001_create_helpdesk_notification_settings_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('helpdesk_notification_settings'));
        $this->assertTrue(Schema::hasColumns('helpdesk_notification_settings', [
            'admin_id',
            'enabled',
            'task_due',
            'ticket_status_changes',
            'created_at',
            'updated_at',
        ]));

        $migration->down();
        $this->assertFalse(Schema::hasTable('helpdesk_notification_settings'));
    }

    public function test_ticket_status_notification_migration_removes_the_preference_column(): void
    {
        $createMigration = require database_path('migrations/2026_11_03_000001_create_helpdesk_notification_settings_table.php');
        $createMigration->up();
        $removeMigration = require database_path('migrations/2026_11_03_000002_remove_ticket_status_notification_preference.php');
        $removeMigration->up();

        $this->assertFalse(Schema::hasColumn('helpdesk_notification_settings', 'ticket_status_changes'));

        $removeMigration->down();
        $this->assertTrue(Schema::hasColumn('helpdesk_notification_settings', 'ticket_status_changes'));
        Schema::drop('helpdesk_notification_settings');
    }

    public function test_notification_settings_schema_reconciliation_removes_a_stale_preference_column(): void
    {
        Schema::create('helpdesk_notification_settings', function ($table): void {
            $table->unsignedBigInteger('admin_id')->primary();
            $table->boolean('enabled')->default(false);
            $table->boolean('task_due')->default(true);
            $table->boolean('ticket_status_changes')->default(true);
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_11_04_000001_reconcile_notification_settings_schema.php');
        $migration->up();

        $this->assertFalse(Schema::hasColumn('helpdesk_notification_settings', 'ticket_status_changes'));
        $this->assertTrue(Schema::hasColumns('helpdesk_notification_settings', [
            'admin_id',
            'enabled',
            'task_due',
            'created_at',
            'updated_at',
        ]));

        Schema::drop('helpdesk_notification_settings');
    }

    public function test_browser_notification_poll_returns_due_tasks(): void
    {
        $this->createNotificationTables();
        Schema::create('helpdesk_accomplishments', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        DB::table('helpdesk_notification_settings')->insert([
            'admin_id' => 1,
            'enabled' => 1,
            'task_due' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('helpdesk_accomplishments')->insert([
            ['created_by' => 1, 'title' => 'Submit report', 'description' => null, 'due_date' => now()->toDateString(), 'status' => 'todo', 'completed_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['created_by' => 2, 'title' => 'Another admin task', 'description' => null, 'due_date' => now()->toDateString(), 'status' => 'todo', 'completed_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $response = $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->getJson('/admin/notifications')
            ->assertOk()
            ->assertJsonPath('settings.enabled', true)
            ->assertJsonCount(1, 'events');

        $events = collect($response->json('events'));
        $this->assertTrue($events->contains(fn (array $event): bool => $event['title'] === 'Task due today' && $event['body'] === 'Submit report'));

        Schema::drop('helpdesk_accomplishments');
        Schema::drop('helpdesk_notification_settings');
        Schema::drop('helpdesk_users');
    }

    private function createNotificationTables(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('role');
            $table->boolean('is_active');
        });
        DB::table('helpdesk_users')->insert([
            ['id' => 1, 'role' => 'admin', 'is_active' => 1],
            ['id' => 2, 'role' => 'admin', 'is_active' => 1],
        ]);
        Schema::create('helpdesk_notification_settings', function ($table): void {
            $table->unsignedBigInteger('admin_id')->primary();
            $table->boolean('enabled')->default(false);
            $table->boolean('task_due')->default(true);
            $table->timestamps();
        });
    }

    public function test_accomplishment_board_tracks_admin_tasks_and_exports_quarterly_report(): void
    {
        $this->createAccomplishmentTables();
        DB::table('helpdesk_accomplishments')->insert([
            ['created_by' => 1, 'title' => 'Quarterly service update', 'description' => 'Published helpdesk updates.', 'due_date' => '2026-11-30', 'status' => 'completed', 'completed_at' => '2026-11-02 10:00:00', 'created_at' => '2026-10-25 10:00:00', 'updated_at' => '2026-11-02 10:00:00'],
            ['created_by' => 1, 'title' => '=SUM(1,1)', 'description' => null, 'due_date' => null, 'status' => 'completed', 'completed_at' => '2026-08-02 10:00:00', 'created_at' => '2026-08-01 10:00:00', 'updated_at' => '2026-08-02 10:00:00'],
            ['created_by' => 2, 'title' => 'Private accomplishment', 'description' => null, 'due_date' => null, 'status' => 'completed', 'completed_at' => '2026-11-02 10:00:00', 'created_at' => '2026-10-25 10:00:00', 'updated_at' => '2026-11-02 10:00:00'],
        ]);
        $session = ['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']];

        $this->withSession($session)->get('/admin/accomplishments')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accomplishments')
                ->where('activePage', 'accomplishments')
                ->has('accomplishments', 2)
                ->where('accomplishments.0.title', 'Quarterly service update'));

        $response = $this->withSession($session)->get('/admin/accomplishments/report?year=2026&quarter=4');
        $response->assertOk()->assertDownload('accomplishments-Q4-2026.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Quarterly service update', $csv);
        $this->assertStringNotContainsString('Private accomplishment', $csv);
        $this->assertStringNotContainsString('=SUM(1,1)', $csv);
        $this->assertStringContainsString("'=SUM(1,1)", $this->withSession($session)->get('/admin/accomplishments/report?year=2026&quarter=3')->streamedContent());

        Schema::drop('helpdesk_accomplishments');
    }

    public function test_accomplishment_board_creates_updates_and_deletes_owned_tasks(): void
    {
        $this->createAccomplishmentTables();
        $session = ['user' => ['id' => 1, 'name' => 'Admin', 'role' => 'admin']];

        $this->withSession($session)->post('/admin/accomplishments', [
            'title' => 'Prepare quarterly report',
            'description' => 'Collect completed work.',
            'due_date' => '2026-12-31',
            'status' => 'todo',
        ])->assertRedirect();
        $taskId = DB::table('helpdesk_accomplishments')->value('id');
        $this->assertDatabaseHas('helpdesk_accomplishments', ['id' => $taskId, 'created_by' => 1, 'status' => 'todo']);

        $this->withSession($session)->post("/admin/accomplishments/{$taskId}", [
            'title' => 'Prepare quarterly report',
            'description' => 'Collect completed work.',
            'due_date' => '2026-12-31',
            'status' => 'in_progress',
        ])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('helpdesk_accomplishments', ['id' => $taskId, 'status' => 'todo']);

        $this->withSession(['user' => ['id' => 2, 'name' => 'Other Admin', 'role' => 'admin']])
            ->post("/admin/accomplishments/{$taskId}/complete")
            ->assertSessionHasErrors('form');
        $this->assertDatabaseHas('helpdesk_accomplishments', ['id' => $taskId, 'status' => 'todo']);

        $this->withSession($session)->post("/admin/accomplishments/{$taskId}/complete")
            ->assertRedirect()
            ->assertSessionHas('success', 'Accomplishment marked complete.');
        $this->assertDatabaseHas('helpdesk_accomplishments', ['id' => $taskId, 'status' => 'completed']);
        $this->assertNotNull(DB::table('helpdesk_accomplishments')->where('id', $taskId)->value('completed_at'));

        $this->withSession($session)->post("/admin/accomplishments/{$taskId}", [
            'title' => 'Prepare quarterly report',
            'description' => 'Report is ready.',
            'due_date' => '2026-12-31',
            'status' => 'completed',
        ])->assertRedirect();
        $this->assertDatabaseHas('helpdesk_accomplishments', ['id' => $taskId, 'status' => 'completed']);
        $this->assertNotNull(DB::table('helpdesk_accomplishments')->where('id', $taskId)->value('completed_at'));

        $this->withSession(['user' => ['id' => 2, 'name' => 'Other Admin', 'role' => 'admin']])
            ->post("/admin/accomplishments/{$taskId}/delete")
            ->assertSessionHasErrors('form');
        $this->assertDatabaseHas('helpdesk_accomplishments', ['id' => $taskId]);

        $this->withSession($session)->post("/admin/accomplishments/{$taskId}/delete")
            ->assertRedirect();
        $this->assertDatabaseMissing('helpdesk_accomplishments', ['id' => $taskId]);

        Schema::drop('helpdesk_accomplishments');
    }

    private function createAccomplishmentTables(): void
    {
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('role');
            $table->boolean('is_active');
        });
        DB::table('helpdesk_users')->insert([
            ['id' => 1, 'role' => 'admin', 'is_active' => 1],
            ['id' => 2, 'role' => 'admin', 'is_active' => 1],
        ]);
        Schema::create('helpdesk_accomplishments', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('todo');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
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
