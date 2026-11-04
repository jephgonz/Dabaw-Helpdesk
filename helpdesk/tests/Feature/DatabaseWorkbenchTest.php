<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DatabaseWorkbenchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::setDefaultConnection('sqlite');
        config([
            'database.connections.mysql.driver' => 'sqlite',
            'database.connections.mysql.database' => ':memory:',
            'database.connections.mysql.foreign_key_constraints' => false,
        ]);
        DB::purge('mysql');
        Schema::dropIfExists('helpdesk_accomplishments');
        Schema::dropIfExists('helpdesk_settings');
        Schema::dropIfExists('database_workbench_items');
        Schema::dropIfExists('helpdesk_users');
        Schema::create('helpdesk_users', function ($table): void {
            $table->id();
            $table->string('role');
            $table->boolean('is_active');
        });
        DB::table('helpdesk_users')->insert(['id' => 1, 'role' => 'admin', 'is_active' => true]);
        Schema::create('database_workbench_items', function ($table): void {
            $table->id();
            $table->string('name');
        });
        DB::table('database_workbench_items')->insert(['id' => 1, 'name' => 'First row']);
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        Schema::dropIfExists('helpdesk_accomplishments');
        Schema::dropIfExists('helpdesk_settings');
        Schema::dropIfExists('database_workbench_items');
        Schema::dropIfExists('helpdesk_users');

        parent::tearDown();
    }

    public function test_admin_can_run_a_read_query_and_receive_bounded_result_data(): void
    {
        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->postJson('/admin/database/query', ['sql' => 'SELECT id, name FROM database_workbench_items'])
            ->assertOk()
            ->assertJsonPath('columns.0', 'id')
            ->assertJsonPath('columns.1', 'name')
            ->assertJsonPath('rows.0.0', 1)
            ->assertJsonPath('rows.0.1', 'First row')
            ->assertJsonPath('rowCount', 1)
            ->assertJsonPath('truncated', false);
    }

    public function test_database_overview_lists_local_sqlite_tables_and_row_counts(): void
    {
        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->get('/admin/database')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Database')
                ->where('driver', 'sqlite')
                ->has('tables', 2)
                ->where('tables.0.name', 'database_workbench_items')
                ->where('tables.0.rows_estimate', 1));
    }

    public function test_sqlite_table_browser_reads_primary_keys_and_rows(): void
    {
        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->get('/admin/database/table?table=database_workbench_items')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/DatabaseTable')
                ->where('table', 'database_workbench_items')
                ->where('editable', true)
                ->where('canCreate', true)
                ->where('columns.0.name', 'id')
                ->where('rows.0.name', 'First row'));
    }

    public function test_local_backup_clones_to_aiven_and_restore_clones_back_to_local(): void
    {
        $this->prepareSyncDatabases();
        Storage::fake('local');
        DB::table('helpdesk_settings')->insert([
            ['setting_key' => 'shared', 'setting_value' => 'local version'],
            ['setting_key' => 'local-only', 'setting_value' => 'local only'],
        ]);
        DB::connection('mysql')->table('helpdesk_settings')->insert([
            ['setting_key' => 'obsolete', 'setting_value' => 'remove from backup'],
        ]);
        DB::table('helpdesk_accomplishments')->insert([
            ['id' => 1, 'title' => 'Local version'],
        ]);
        DB::connection('mysql')->table('helpdesk_accomplishments')->insert([
            ['id' => 8, 'title' => 'Obsolete Aiven task'],
        ]);

        $session = ['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']];
        $this->withSession($session)
            ->get('/admin/database')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('driver', 'sqlite')
                ->where('backupStatus', 'outdated')
                ->where('backupChanges', 5));

        $previewResponse = $this->withSession($session)
            ->postJson('/admin/database/backup/preview', ['operation' => 'backup'])
            ->assertOk()
            ->assertJsonPath('source', 'local')
            ->assertJsonPath('target', 'aiven')
            ->assertJsonPath('tables.1.source_rows', 2)
            ->assertJsonPath('tables.1.target_rows', 1);

        $this->withSession($session)
            ->post('/admin/database/backup', [
                'operation' => 'backup',
                'preview_token' => $previewResponse->json('token'),
                'confirm_clone' => true,
            ])
            ->assertRedirect(route('admin.database'))
            ->assertSessionHas('backupSnapshot')
            ->assertSessionHas('success');

        $this->get('/admin/database')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('backupStatus', 'current')
                ->where('backupChanges', 0));

        $this->assertDatabaseHas('helpdesk_settings', ['setting_key' => 'shared', 'setting_value' => 'local version'], 'mysql');
        $this->assertDatabaseHas('helpdesk_settings', ['setting_key' => 'local-only', 'setting_value' => 'local only'], 'mysql');
        $this->assertDatabaseMissing('helpdesk_settings', ['setting_key' => 'obsolete'], 'mysql');
        $this->assertDatabaseHas('helpdesk_accomplishments', ['id' => 1, 'title' => 'Local version'], 'mysql');
        $this->assertDatabaseMissing('helpdesk_accomplishments', ['id' => 8], 'mysql');

        $backups = Storage::disk('local')->files('database-backups');
        $this->assertCount(1, $backups);
        $backupContents = Storage::disk('local')->get($backups[0]);
        $backup = unserialize(gzdecode($backupContents), ['allowed_classes' => false]);
        $backupSettings = array_column($backup['tables']['helpdesk_settings'], 'setting_value', 'setting_key');
        $this->assertSame('obsolete', array_key_first($backupSettings));
        $backupName = basename($backups[0]);
        $this->withSession($session)
            ->get('/admin/database/backup-snapshots/'.$backupName)
            ->assertOk();

        DB::connection('mysql')->table('helpdesk_settings')->where('setting_key', 'shared')->update(['setting_value' => 'updated in Aiven']);
        DB::connection('mysql')->table('helpdesk_settings')->where('setting_key', 'local-only')->delete();
        DB::connection('mysql')->table('helpdesk_accomplishments')->update(['title' => 'Restored from Aiven']);
        $reversePreview = $this->withSession($session)
            ->postJson('/admin/database/backup/preview', ['operation' => 'restore'])
            ->assertOk()
            ->assertJsonPath('source', 'aiven')
            ->assertJsonPath('target', 'local');
        $this->withSession($session)
            ->post('/admin/database/backup', [
                'operation' => 'restore',
                'preview_token' => $reversePreview->json('token'),
                'confirm_clone' => true,
            ])
            ->assertRedirect(route('admin.database'))
            ->assertSessionHas('backupSnapshot');
        $this->assertDatabaseHas('helpdesk_settings', ['setting_key' => 'shared', 'setting_value' => 'updated in Aiven']);
        $this->assertDatabaseMissing('helpdesk_settings', ['setting_key' => 'local-only']);
        $this->assertDatabaseHas('helpdesk_accomplishments', ['id' => 1, 'title' => 'Restored from Aiven']);
        $this->assertCount(2, Storage::disk('local')->files('database-backups'));
    }

    public function test_database_clone_rejects_a_preview_if_either_database_changed(): void
    {
        $this->prepareSyncDatabases();
        DB::connection('mysql')->table('helpdesk_settings')->insert([
            'setting_key' => 'setting',
            'setting_value' => 'before preview',
        ]);

        $session = ['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']];
        $previewResponse = $this->withSession($session)
            ->postJson('/admin/database/backup/preview', ['operation' => 'restore'])
            ->assertOk();
        DB::connection('mysql')->table('helpdesk_settings')->update(['setting_value' => 'changed after preview']);

        $this->withSession($session)
            ->from('/admin/database')
            ->post('/admin/database/backup', [
                'operation' => 'restore',
                'preview_token' => $previewResponse->json('token'),
                'confirm_clone' => true,
            ])
            ->assertRedirect('/admin/database')
            ->assertSessionHasErrors('clone');

        $this->assertDatabaseMissing('helpdesk_settings', ['setting_key' => 'setting']);
    }

    public function test_clone_blocks_when_managed_table_schemas_do_not_match(): void
    {
        $this->prepareSyncDatabases();
        Storage::fake('local');
        DB::connection('mysql')->table('helpdesk_settings')->insert([
            'setting_key' => 'keep',
            'setting_value' => 'untouched',
        ]);
        Schema::connection('mysql')->table('helpdesk_settings', function ($table): void {
            $table->string('unexpected_column')->nullable();
        });

        $session = ['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']];
        $previewResponse = $this->withSession($session)
            ->postJson('/admin/database/backup/preview', ['operation' => 'backup'])
            ->assertOk()
            ->assertJsonPath('tables.1.status', 'schema_mismatch');

        $this->withSession($session)
            ->from('/admin/database')
            ->post('/admin/database/backup', [
                'operation' => 'backup',
                'preview_token' => $previewResponse->json('token'),
                'confirm_clone' => true,
            ])
            ->assertRedirect('/admin/database')
            ->assertSessionHasErrors('clone');

        $this->assertDatabaseHas('helpdesk_settings', [
            'setting_key' => 'keep',
            'setting_value' => 'untouched',
        ], 'mysql');
        $this->assertCount(0, Storage::disk('local')->files('database-backups'));
    }

    public function test_restore_is_blocked_if_it_would_remove_the_current_admin_account(): void
    {
        $this->prepareSyncDatabases();
        Storage::fake('local');
        DB::connection('mysql')->table('helpdesk_users')->where('id', 1)->delete();
        DB::connection('mysql')->table('helpdesk_settings')->insert([
            'setting_key' => 'remote',
            'setting_value' => 'do not restore',
        ]);

        $session = ['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']];
        $previewResponse = $this->withSession($session)
            ->postJson('/admin/database/backup/preview', ['operation' => 'restore'])
            ->assertOk();

        $this->withSession($session)
            ->from('/admin/database')
            ->post('/admin/database/backup', [
                'operation' => 'restore',
                'preview_token' => $previewResponse->json('token'),
                'confirm_clone' => true,
            ])
            ->assertRedirect('/admin/database')
            ->assertSessionHasErrors('clone');

        $this->assertDatabaseMissing('helpdesk_settings', ['setting_key' => 'remote']);
        $this->assertCount(0, Storage::disk('local')->files('database-backups'));
    }

    private function prepareSyncDatabases(): void
    {
        config([
            'database.connections.mysql.driver' => 'sqlite',
            'database.connections.mysql.database' => ':memory:',
            'database.connections.mysql.foreign_key_constraints' => false,
        ]);
        DB::purge('mysql');

        foreach (['sqlite', 'mysql'] as $connection) {
            $schema = Schema::connection($connection);
            foreach (['helpdesk_users', 'helpdesk_settings', 'helpdesk_notification_settings', 'helpdesk_accomplishments', 'migrations'] as $table) {
                $schema->dropIfExists($table);
            }
            $schema->create('helpdesk_users', function ($table): void {
                $table->id();
                $table->string('role');
                $table->boolean('is_active');
            });
            $schema->create('helpdesk_settings', function ($table): void {
                $table->string('setting_key')->primary();
                $table->text('setting_value');
            });
            $schema->create('helpdesk_notification_settings', function ($table): void {
                $table->unsignedBigInteger('admin_id')->primary();
                $table->boolean('enabled');
            });
            $schema->create('helpdesk_accomplishments', function ($table): void {
                $table->id();
                $table->string('title');
            });
            $schema->create('migrations', function ($table): void {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
            DB::connection($connection)->table('helpdesk_users')->insert([
                'id' => 1,
                'role' => 'admin',
                'is_active' => true,
            ]);
        }
    }

    public function test_admin_must_confirm_data_changes_before_running_them(): void
    {
        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->postJson('/admin/database/query', ['sql' => "UPDATE database_workbench_items SET name = 'Changed' WHERE id = 1"])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Confirm the query before running a statement that can change data or schema.');

        $this->postJson('/admin/database/query', [
            'sql' => "UPDATE database_workbench_items SET name = 'Changed' WHERE id = 1",
            'confirm_changes' => true,
        ])->assertOk()->assertJsonPath('affectedRows', 1);

        $this->assertDatabaseHas('database_workbench_items', ['id' => 1, 'name' => 'Changed']);
    }

    public function test_workbench_rejects_multiple_statements_in_a_single_request(): void
    {
        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->postJson('/admin/database/query', ['sql' => 'SELECT 1; SELECT 2'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Run one SQL statement at a time.');
    }

    public function test_workbench_limits_result_output_and_rejects_database_level_commands(): void
    {
        $rows = [];
        for ($id = 2; $id <= 205; $id++) {
            $rows[] = ['id' => $id, 'name' => 'Row '.$id];
        }
        DB::table('database_workbench_items')->insert($rows);

        $this->withSession(['user' => ['id' => 1, 'name' => 'Admin One', 'role' => 'admin']])
            ->postJson('/admin/database/query', ['sql' => 'SELECT id FROM database_workbench_items ORDER BY id'])
            ->assertOk()
            ->assertJsonPath('rowCount', 200)
            ->assertJsonPath('truncated', true);

        $this->postJson('/admin/database/query', [
            'sql' => 'CREATE DATABASE unrelated',
            'confirm_changes' => true,
        ])->assertUnprocessable()
            ->assertJsonPath('error', 'This SQL command is not supported in the workbench.');
    }
}
