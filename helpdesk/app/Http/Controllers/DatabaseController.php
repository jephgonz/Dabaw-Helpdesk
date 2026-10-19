<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use PDO;
use RuntimeException;

class DatabaseController extends Controller
{
    public function index(): Response
    {
        $connected = false;
        try {
            $pdo = DB::connection()->getPdo();
            $databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            $serverVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $tables = DB::select(
                'SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_ROWS AS rows_estimate, DATA_LENGTH + INDEX_LENGTH AS bytes FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
                ['BASE TABLE']
            );
            $connected = true;
        } catch (\Throwable $exception) {
            Log::error('Database overview could not load.', ['exception' => $exception]);
            $databaseName = '';
            $serverVersion = '';
            $tables = [];
            session()->flash('error', 'The MySQL database could not be reached. Check the database configuration.');
        }

        return Inertia::render('Admin/Database', [
            'databaseName' => $databaseName,
            'serverVersion' => $serverVersion,
            'tables' => $tables,
            'connected' => $connected,
            'activePage' => 'database',
        ]);
    }

    public function metrics()
    {
        try {
            $pdo = DB::connection()->getPdo();
            $status = $pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected', 'Threads_running', 'Max_used_connections', 'Connections', 'Uptime')")->fetchAll(PDO::FETCH_KEY_PAIR);
            $variables = $pdo->query("SHOW GLOBAL VARIABLES WHERE Variable_name = 'max_connections'")->fetchAll(PDO::FETCH_KEY_PAIR);

            return response()->json([
                'connected' => (int) ($status['Threads_connected'] ?? 0),
                'running' => (int) ($status['Threads_running'] ?? 0),
                'maxUsed' => (int) ($status['Max_used_connections'] ?? 0),
                'maxConnections' => (int) ($variables['max_connections'] ?? 0),
                'connectionsSinceUptime' => (int) ($status['Connections'] ?? 0),
                'uptime' => (int) ($status['Uptime'] ?? 0),
                'sampledAt' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Database metrics are unavailable.', ['exception' => $exception]);
            return response()->json(['error' => 'Database metrics are unavailable.'], 503);
        }
    }

    public function table(Request $request): Response
    {
        $table = (string) $request->query('table', '');
        $meta = $this->tableMeta($table);
        $visible = array_values(array_filter($meta['columns'], fn (array $column): bool => !$this->isHidden($column['name'])));
        $selectColumns = array_values(array_unique(array_merge(
            array_column($visible, 'name'),
            $meta['primary_keys']
        )));
        $rows = DB::select(
            'SELECT '.implode(', ', array_map($this->quote(...), $selectColumns)).
            ' FROM '.$this->quote($table).
            ($meta['primary_keys'] ? ' ORDER BY '.implode(', ', array_map($this->quote(...), $meta['primary_keys'])) : '').
            ' LIMIT 100'
        );
        $rows = array_map(static fn (object $row): array => (array) $row, $rows);

        return Inertia::render('Admin/DatabaseTable', [
            'table' => $table,
            'columns' => $visible,
            'rows' => $rows,
            'keys' => array_map(fn (array $row): string => $this->encodeKey($row, $meta['primary_keys']), $rows),
            'editable' => $meta['primary_keys'] !== [],
            'canCreate' => $meta['primary_keys'] !== [] && $table !== 'helpdesk_users',
            'activePage' => 'database',
        ]);
    }

    public function createForm(Request $request): Response
    {
        $table = (string) $request->query('table', '');
        $meta = $this->tableMeta($table);
        abort_if(!$meta['primary_keys'] || $table === 'helpdesk_users', 403);

        return Inertia::render('Admin/DatabaseRecord', [
            'table' => $table,
            'mode' => 'create',
            'fields' => array_values(array_filter($meta['columns'], fn (array $column): bool => !$this->isHidden($column['name']) && !str_contains($column['extra'], 'auto_increment'))),
            'record' => [],
            'key' => '',
            'activePage' => 'database',
        ]);
    }

    public function editForm(Request $request): Response
    {
        $table = (string) $request->query('table', '');
        $meta = $this->tableMeta($table);
        abort_if(!$meta['primary_keys'], 403);
        $key = $this->decodeKey((string) $request->query('key', ''), $meta['primary_keys']);
        $record = $this->findRecord($table, $meta, $key);
        abort_if($record === null, 404);

        return Inertia::render('Admin/DatabaseRecord', [
            'table' => $table,
            'mode' => 'edit',
            'fields' => array_values(array_filter($meta['columns'], fn (array $column): bool => !$this->isHidden($column['name']) && !str_contains($column['extra'], 'auto_increment'))),
            'record' => $record,
            'key' => (string) $request->query('key'),
            'activePage' => 'database',
        ]);
    }

    public function saveRecord(Request $request): RedirectResponse
    {
        $table = (string) $request->input('table', '');
        $mode = (string) $request->input('mode', '');
        $meta = $this->tableMeta($table);
        abort_if(!$meta['primary_keys'] || !in_array($mode, ['create', 'edit'], true), 403);
        abort_if($mode === 'create' && $table === 'helpdesk_users', 403);
        $allowed = array_values(array_map(
            static fn (array $column): string => $column['name'],
            array_filter($meta['columns'], fn (array $column): bool => !$this->isHidden($column['name']) && !str_contains($column['extra'], 'auto_increment'))
        ));
        $submitted = $request->input('values', []);
        if (!is_array($submitted)) {
            return back()->withErrors(['form' => 'Invalid record values.']);
        }
        $values = array_intersect_key($submitted, array_flip($allowed));
        unset($values['created_at'], $values['updated_at']);
        foreach ($values as &$value) {
            if ($value === '') {
                $value = null;
            }
        }
        unset($value);

        try {
            if ($mode === 'create') {
                if ($values === []) {
                    throw new RuntimeException('Enter values for the new row.');
                }
                DB::table($table)->insert($values);
            } else {
                $key = $this->decodeKey((string) $request->input('key', ''), $meta['primary_keys']);
                if ($values === []) {
                    throw new RuntimeException('No editable values were submitted.');
                }
                if ($table === 'helpdesk_users') {
                    $this->updateHelpdeskUser($request, $key, $values);
                    return redirect()->route('admin.database.table', ['table' => $table])->with('success', 'Account row saved.');
                }
                DB::table($table)->where($key)->update($values);
            }
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            Log::error('Database record could not be saved.', ['table' => $table, 'exception' => $exception]);
            return back()->withErrors(['form' => 'The row could not be saved. Check required values and database constraints.'])->withInput();
        }

        return redirect()->route('admin.database.table', ['table' => $table])->with('success', 'Row saved.');
    }

    public function deleteRecord(Request $request): RedirectResponse
    {
        $table = (string) $request->input('table', '');
        $meta = $this->tableMeta($table);
        abort_if(!$meta['primary_keys'] || $table === 'helpdesk_users', 403);
        $key = $this->decodeKey((string) $request->input('key', ''), $meta['primary_keys']);

        try {
            DB::table($table)->where($key)->delete();
        } catch (\Throwable $exception) {
            Log::error('Database record could not be deleted.', ['table' => $table, 'exception' => $exception]);
            return back()->withErrors(['form' => 'The row could not be deleted. Check related records and database constraints.']);
        }

        return back()->with('success', 'Row deleted.');
    }

    public function export()
    {
        $pdo = DB::connection()->getPdo();
        $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $tables = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $database) ?: 'database';

        return response()->streamDownload(function () use ($pdo, $tables, $database): void {
            echo "-- Jephthah Helpdesk MySQL database export\n-- Database: {$database}\n-- Includes credentials and application data; store securely.\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
            foreach ($tables as $table) {
                $quoted = $this->quote((string) $table);
                $create = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(PDO::FETCH_NUM);
                if (!isset($create[1])) {
                    throw new RuntimeException('Could not read table definition for export.');
                }
                echo "DROP TABLE IF EXISTS {$quoted};\n{$create[1]};\n";
                $columnMeta = $pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
                $columnMeta->execute([$table]);
                $columns = $columnMeta->fetchAll(PDO::FETCH_ASSOC);
                $columnList = implode(', ', array_map(fn (array $column): string => $this->quote($column['COLUMN_NAME']), $columns));
                $binaryTypes = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'bit', 'geometry', 'point', 'linestring', 'polygon'];
                $rows = $pdo->query("SELECT * FROM {$quoted}");
                $batch = [];
                while ($row = $rows->fetch(PDO::FETCH_NUM)) {
                    $values = [];
                    foreach ($row as $index => $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } elseif (in_array(strtolower($columns[$index]['DATA_TYPE']), $binaryTypes, true)) {
                            $values[] = "X'".bin2hex((string) $value)."'";
                        } else {
                            $values[] = $pdo->quote((string) $value);
                        }
                    }
                    $batch[] = '('.implode(', ', $values).')';
                    if (count($batch) === 100) {
                        echo "INSERT INTO {$quoted} ({$columnList}) VALUES\n".implode(",\n", $batch).";\n";
                        $batch = [];
                    }
                }
                if ($batch !== []) {
                    echo "INSERT INTO {$quoted} ({$columnList}) VALUES\n".implode(",\n", $batch).";\n";
                }
                echo "\n";
            }
            echo "SET FOREIGN_KEY_CHECKS = 1;\n";
        }, $filename.'-'.now()->format('Y-m-d-His').'.sql', ['Content-Type' => 'application/sql; charset=utf-8']);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'confirm_replace' => ['accepted'],
            'sql_file' => ['required', 'file', 'max:25600', 'extensions:sql'],
        ]);
        $contents = file_get_contents($validated['sql_file']->getRealPath());
        if (!is_string($contents)) {
            return back()->withErrors(['sql_file' => 'The uploaded SQL file could not be read.']);
        }

        try {
            $statements = $this->splitStatements($contents);
            if ($statements === []) {
                throw new RuntimeException('The SQL file contains no statements.');
            }
            foreach ($statements as $statement) {
                $this->assertSafeStatement($statement);
            }
            $pdo = DB::connection()->getPdo();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            try {
                foreach ($statements as $statement) {
                    $pdo->exec($statement);
                }
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
        } catch (\Throwable $exception) {
            Log::warning('SQL import was rejected or failed.', ['exception' => $exception]);
            return back()->withErrors(['sql_file' => $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The import failed. Check that the dump matches the configured database.']);
        }

        try {
            if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
                throw new RuntimeException('Laravel migrations returned a non-zero exit status.');
            }
        } catch (\Throwable $exception) {
            Log::error('Laravel migrations could not be applied after SQL restore.', ['exception' => $exception]);

            return back()->withErrors(['sql_file' => 'The SQL restore was applied, but Laravel could not finish updating the schema. Run php artisan migrate --force and review the migration output.']);
        }

        return back()->with('success', 'SQL database restored and Laravel migrations checked.');
    }

    private function tableMeta(string $table): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            abort(404);
        }
        $columns = DB::select(
            'SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable, COLUMN_DEFAULT AS default_value, EXTRA AS extra, COLUMN_KEY AS column_key FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$table]
        );
        abort_if($columns === [], 404);
        $primary = DB::select(
            "SELECT COLUMN_NAME AS name FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION",
            [$table]
        );

        return [
            'columns' => array_map(static fn (object $column): array => [
                'name' => $column->name,
                'type' => $column->type,
                'nullable' => $column->nullable === 'YES',
                'default' => $column->default_value,
                'extra' => $column->extra,
                'key' => $column->column_key,
            ], $columns),
            'primary_keys' => array_map(static fn (object $column): string => $column->name, $primary),
        ];
    }

    private function findRecord(string $table, array $meta, array $key): ?array
    {
        $selected = array_values(array_unique(array_merge(
            array_column(array_filter($meta['columns'], fn (array $column): bool => !$this->isHidden($column['name'])), 'name'),
            $meta['primary_keys']
        )));
        $where = implode(' AND ', array_map(fn (string $name): string => $this->quote($name).' = ?', array_keys($key)));
        $row = DB::selectOne('SELECT '.implode(', ', array_map($this->quote(...), $selected)).' FROM '.$this->quote($table).' WHERE '.$where.' LIMIT 1', array_values($key));

        return $row ? (array) $row : null;
    }

    private function encodeKey(array $row, array $primary): string
    {
        $key = [];
        foreach ($primary as $column) {
            $key[$column] = $row[$column];
        }

        return rtrim(strtr(base64_encode(json_encode($key, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function decodeKey(string $token, array $primary): array
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        $data = is_string($decoded) ? json_decode($decoded, true) : null;
        if (!is_array($data) || count($data) !== count($primary)) {
            abort(422, 'Invalid row key.');
        }
        $key = [];
        foreach ($primary as $column) {
            if (!array_key_exists($column, $data) || !is_scalar($data[$column])) {
                abort(422, 'Invalid row key.');
            }
            $key[$column] = $data[$column];
        }

        return $key;
    }

    private function updateHelpdeskUser(Request $request, array $key, array $values): void
    {
        DB::transaction(function () use ($request, $key, $values): void {
            $user = DB::table('helpdesk_users')->where($key)->lockForUpdate()->first();
            if (!$user) {
                throw new RuntimeException('The account was not found.');
            }
            $final = array_merge((array) $user, $values);
            $final['display_name'] = trim((string) $final['display_name']);
            $final['email'] = strtolower(trim((string) $final['email']));
            $final['role'] = (string) $final['role'];
            $final['username'] = strtolower(trim((string) ($final['username'] ?? '')));
            $final['is_active'] = (int) $final['is_active'];

            if ($final['display_name'] === '' || strlen($final['display_name']) > 120 || !filter_var($final['email'], FILTER_VALIDATE_EMAIL) || strlen($final['email']) > 190) {
                throw new RuntimeException('Enter a valid display name and email address.');
            }
            if (!in_array($final['role'], ['admin', 'staff'], true) || !in_array($final['is_active'], [0, 1], true)) {
                throw new RuntimeException('Choose a valid role and account status.');
            }
            if ($final['role'] === 'staff' && !preg_match('/^[a-z0-9][a-z0-9._-]{2,79}$/', $final['username'])) {
                throw new RuntimeException('Staff usernames must be 3–80 characters and use letters, numbers, dots, underscores, or hyphens.');
            }
            if ($final['role'] === 'admin') {
                $final['username'] = null;
            }
            if ($final['username'] !== null && DB::table('helpdesk_users')
                ->where('username', $final['username'])->whereNot('id', $user->id)->exists()) {
                throw new RuntimeException('That username is already in use.');
            }
            $losesAdminAccess = $user->role === 'admin' && (int) $user->is_active === 1
                && ($final['role'] !== 'admin' || $final['is_active'] !== 1);
            if ($losesAdminAccess) {
                if ((int) $user->id === (int) $request->session()->get('user.id')) {
                    throw new RuntimeException('You cannot deactivate or demote your own account.');
                }
                $activeAdmins = DB::table('helpdesk_users')->where('role', 'admin')->where('is_active', 1)->lockForUpdate()->get(['id']);
                if ($activeAdmins->count() <= 1) {
                    throw new RuntimeException('The last active administrator cannot be deactivated or demoted.');
                }
            }

            $updated = [];
            foreach (['display_name', 'username', 'email', 'role', 'is_active'] as $field) {
                if (array_key_exists($field, $values)) {
                    $updated[$field] = $final[$field];
                }
            }
            if ($updated !== []) {
                $updated['updated_at'] = now();
                DB::table('helpdesk_users')->where('id', $user->id)->update($updated);
            }
        });
    }

    private function isHidden(string $name): bool
    {
        return (bool) preg_match('/password|secret|token|credential|salt|(?:^|_)key(?:_|$)/i', $name);
    }

    private function quote(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $statements = [];
        $statement = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);
        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            $next = $sql[$index + 1] ?? '';
            if ($lineComment) {
                if ($character === "\n") {
                    $lineComment = false;
                    $statement .= "\n";
                }
                continue;
            }
            if ($blockComment) {
                if ($character === '*' && $next === '/') {
                    $blockComment = false;
                    $index++;
                }
                continue;
            }
            if ($quote !== null) {
                $statement .= $character;
                if ($character === '\\' && $quote !== '`' && $next !== '') {
                    $statement .= $next;
                    $index++;
                } elseif ($character === $quote) {
                    if ($next === $quote) {
                        $statement .= $next;
                        $index++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if (($character === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) || $character === '#') {
                $lineComment = true;
                if ($character === '-') {
                    $index++;
                }
                continue;
            }
            if ($character === '/' && $next === '*') {
                $blockComment = true;
                $index++;
                continue;
            }
            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
                $statement .= $character;
            } elseif ($character === ';') {
                if (trim($statement) !== '') {
                    $statements[] = trim($statement);
                }
                $statement = '';
            } else {
                $statement .= $character;
            }
        }
        if ($quote !== null || $blockComment) {
            throw new RuntimeException('The SQL file has an unterminated quote or comment.');
        }
        if (trim($statement) !== '') {
            $statements[] = trim($statement);
        }

        return $statements;
    }

    private function assertSafeStatement(string $statement): void
    {
        if (!preg_match('/^(SET|CREATE\s+TABLE|DROP\s+TABLE|ALTER\s+TABLE|INSERT\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM|LOCK\s+TABLES|UNLOCK\s+TABLES|START\s+TRANSACTION|COMMIT|ROLLBACK)\b/i', $statement, $match)) {
            throw new RuntimeException('The SQL file contains an unsupported statement. Only table definitions and table data can be imported.');
        }
        if (strtoupper($match[1]) === 'SET' && !preg_match('/^SET\s+(?:(?:NAMES|CHARACTER\s+SET)\b|(?:FOREIGN_KEY_CHECKS|UNIQUE_CHECKS|SQL_MODE|TIME_ZONE|AUTOCOMMIT)\b|@OLD_[A-Z0-9_]+\b)/i', $statement)) {
            throw new RuntimeException('The SQL file contains an unsupported session setting.');
        }
        $withoutStrings = preg_replace("/'(?:\\\\\\\\.|''|[^'\\\\\\\\])*'|\"(?:\\\\\\\\.|\"\"|[^\"\\\\\\\\])*\"/s", "''", $statement);
        if (is_string($withoutStrings) && preg_match('/(?<![A-Za-z0-9_$])(?:`[^`]+`|[A-Za-z_][A-Za-z0-9_$]*)\s*\.\s*(?:`[^`]+`|[A-Za-z_][A-Za-z0-9_$]*)/', $withoutStrings)) {
            throw new RuntimeException('Database-qualified table names are not allowed.');
        }
    }
}
