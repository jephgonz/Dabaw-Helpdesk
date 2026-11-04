<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseWorkbenchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
