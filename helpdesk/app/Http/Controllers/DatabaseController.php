<?php

namespace App\Http\Controllers;

use App\Services\DatabaseSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use PDO;
use RuntimeException;

class DatabaseController extends Controller
{
    public function index(): Response
    {
        $connected = false;
        $driver = DB::connection()->getDriverName();
        try {
            $pdo = DB::connection()->getPdo();
            if ($driver === 'sqlite') {
                $databaseName = basename(str_replace('\\', '/', DB::connection()->getDatabaseName()));
                $serverVersion = 'SQLite '.(string) $pdo->query('SELECT sqlite_version()')->fetchColumn();
                $tables = DB::select("SELECT name, 'SQLite' AS engine, 0 AS rows_estimate, 0 AS bytes FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
                foreach ($tables as $table) {
                    $table->rows_estimate = DB::table($table->name)->count();
                }
            } else {
                $databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
                $serverVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
                $tables = DB::select(
                    'SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_ROWS AS rows_estimate, DATA_LENGTH + INDEX_LENGTH AS bytes FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
                    ['BASE TABLE']
                );
            }
            $connected = true;
        } catch (\Throwable $exception) {
            Log::error('Database overview could not load.', ['exception' => $exception]);
            $databaseName = '';
            $serverVersion = '';
            $tables = [];
            session()->flash('error', 'The configured database could not be reached. Check the database configuration.');
        }

        $backupStatus = 'unavailable';
        $backupChanges = 0;
        $backupSkippedTables = [];
        try {
            $comparison = Cache::remember(
                $this->backupComparisonCacheKey(),
                now()->addSeconds(20),
                fn (): array => app(DatabaseSyncService::class)->preview('local')
            );
            $backupStatus = 'current';
            foreach ($comparison['tables'] as $table) {
                if ($table['status'] !== 'ready') {
                    $backupStatus = 'needs_attention';
                    $backupSkippedTables[] = $table['name'];
                    continue;
                }
                $backupChanges += $table['new_rows'] + $table['changed_rows'] + $table['target_only_rows'];
                if ($backupStatus === 'current' && $table['new_rows'] + $table['changed_rows'] + $table['target_only_rows'] > 0) {
                    $backupStatus = 'outdated';
                }
            }
        } catch (RuntimeException $exception) {
            $backupStatus = 'needs_attention';
            Log::warning('Aiven backup comparison needs attention.', ['exception' => $exception]);
        } catch (\Throwable $exception) {
            Log::warning('Aiven backup status could not be checked.', ['exception' => $exception]);
        }

        return Inertia::render('Admin/Database', [
            'driver' => $driver,
            'databaseName' => $databaseName,
            'serverVersion' => $serverVersion,
            'tables' => $tables,
            'connected' => $connected,
            'backupStatus' => $backupStatus,
            'backupChanges' => $backupChanges,
            'backupSkippedTables' => $backupSkippedTables,
            'activePage' => 'database',
        ]);
    }

    public function clonePreview(Request $request, DatabaseSyncService $syncService)
    {
        $validated = $request->validate([
            'operation' => ['required', 'in:backup,restore'],
        ]);
        $source = $validated['operation'] === 'backup' ? 'local' : 'aiven';

        try {
            $preview = $syncService->preview($source);
            $preview['token'] = Crypt::encryptString(json_encode([
                'operation' => $validated['operation'],
                'source' => $preview['source'],
                'target' => $preview['target'],
                'fingerprint' => $preview['fingerprint'],
                'issued_at' => time(),
            ], JSON_THROW_ON_ERROR));

            return response()->json($preview);
        } catch (\Throwable $exception) {
            Log::warning('Database clone preview failed.', ['exception' => $exception]);

            return response()->json([
                'error' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'Could not compare the databases. Check both connections and try again.',
            ], 422);
        }
    }

    public function cloneDatabase(Request $request, DatabaseSyncService $syncService): RedirectResponse
    {
        $validated = $request->validate([
            'operation' => ['required', 'in:backup,restore'],
            'preview_token' => ['required', 'string', 'max:8192'],
            'confirm_clone' => ['accepted'],
        ]);
        $source = $validated['operation'] === 'backup' ? 'local' : 'aiven';

        try {
            $token = json_decode(Crypt::decryptString($validated['preview_token']), true, 512, JSON_THROW_ON_ERROR);
            if (
                !is_array($token)
                || ($token['operation'] ?? null) !== $validated['operation']
                || ($token['source'] ?? null) !== $source
                || !is_int($token['issued_at'] ?? null)
                || $token['issued_at'] > time()
                || time() - $token['issued_at'] > 600
            ) {
                return back()->withErrors(['clone' => 'This preview has expired. Preview the backup or restore again before applying it.']);
            }

            $preview = $syncService->preview($source);
            if (!hash_equals((string) ($token['fingerprint'] ?? ''), $preview['fingerprint'])) {
                return back()->withErrors(['clone' => 'One of the databases changed after this preview. Preview the backup or restore again before applying it.']);
            }
            if (!hash_equals((string) ($token['target'] ?? ''), $preview['target'])) {
                return back()->withErrors(['clone' => 'The clone target changed. Preview the backup or restore again before applying it.']);
            }

            $adminId = $validated['operation'] === 'restore'
                ? (int) $request->session()->get('user.id')
                : null;
            $result = $syncService->cloneDatabase($preview, $adminId);
        } catch (\Throwable $exception) {
            Log::error('Database clone failed.', ['exception' => $exception]);

            return back()->withErrors([
                'clone' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'The clone could not be completed. No partial clone was committed; check the database connections and try again.',
            ]);
        }

        $action = $validated['operation'] === 'backup' ? 'Aiven backup completed' : 'Local database restored from Aiven';
        Cache::forget($this->backupComparisonCacheKey());

        return redirect()->route('admin.database')
            ->with('success', "{$action}: {$result['tables_cloned']} tables and {$result['rows_written']} rows cloned.")
            ->with('backupSnapshot', $result['snapshot']);
    }

    private function backupComparisonCacheKey(): string
    {
        return 'database.backup.comparison.'.hash('sha256', serialize([
            config('database.connections.sqlite.database'),
            config('database.connections.mysql.host'),
            config('database.connections.mysql.database'),
        ]));
    }

    public function downloadBackupSnapshot(string $backup)
    {
        abort_unless(
            preg_match('/^database-backup-(?:local|aiven)-\d{8}T\d{6}Z-[a-f0-9]{12}\.dat\.gz$/', $backup) === 1,
            404
        );
        $path = 'database-backups/'.$backup;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $backup, [
            'Content-Type' => 'application/gzip',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function metrics()
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return response()->json(['error' => 'Live connection metrics are only available for MySQL.'], 503);
        }

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

    public function query(Request $request)
    {
        $validated = $request->validate([
            'sql' => ['required', 'string', 'max:65535'],
            'confirm_changes' => ['sometimes', 'boolean'],
        ]);

        try {
            $statements = $this->splitStatements($validated['sql']);
            if (count($statements) !== 1) {
                return response()->json(['error' => 'Run one SQL statement at a time.'], 422);
            }

            $statement = $statements[0];
            if (!$this->isAllowedWorkbenchStatement($statement)) {
                return response()->json(['error' => 'This SQL command is not supported in the workbench.'], 422);
            }

            $command = strtoupper(strtok(ltrim($statement), " \t\r\n"));
            $readOnlyCommands = ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'];
            if (!in_array($command, $readOnlyCommands, true) && !($validated['confirm_changes'] ?? false)) {
                return response()->json(['error' => 'Confirm the query before running a statement that can change data or schema.'], 422);
            }
            if (preg_match('/\bINTO\s+(?:OUTFILE|DUMPFILE)\b|\bLOAD_FILE\s*\(/i', $statement)) {
                return response()->json(['error' => 'File access statements are not supported in the workbench.'], 422);
            }

            $startedAt = microtime(true);
            $result = DB::connection()->getPdo()->query($statement);
            $columns = [];
            $rows = [];
            $truncated = false;
            if ($result->columnCount() > 0) {
                $columnCount = min($result->columnCount(), 50);
                for ($index = 0; $index < $columnCount; $index++) {
                    $metadata = $result->getColumnMeta($index);
                    $columns[] = is_array($metadata) ? ($metadata['name'] ?? 'Column '.($index + 1)) : 'Column '.($index + 1);
                }
                while (($row = $result->fetch(PDO::FETCH_NUM)) !== false) {
                    if (count($rows) >= 200) {
                        $truncated = true;
                        break;
                    }
                    $boundedRow = [];
                    foreach (array_slice($row, 0, $columnCount) as $value) {
                        if (is_string($value) && preg_match('//u', $value) !== 1) {
                            $value = '[binary data: '.strlen($value).' bytes]';
                        } elseif (is_string($value) && strlen($value) > 4096) {
                            $value = (function_exists('mb_substr') ? mb_substr($value, 0, 4096) : substr($value, 0, 4096)).'… [truncated]';
                        }
                        $boundedRow[] = $value;
                    }
                    $rows[] = $boundedRow;
                }
                if ($result->columnCount() > $columnCount) {
                    $truncated = true;
                }
            }

            return response()->json([
                'columns' => $columns,
                'rows' => $rows,
                'rowCount' => count($rows),
                'affectedRows' => $result->columnCount() === 0 ? $result->rowCount() : null,
                'truncated' => $truncated,
                'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Database workbench query failed.', ['exception' => $exception]);

            return response()->json(['error' => $exception->getMessage()], 422);
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
        if (DB::connection()->getDriverName() === 'sqlite') {
            $databasePath = DB::connection()->getDatabaseName();
            abort_unless(is_file($databasePath), 404);

            return response()->download($databasePath, 'database.sqlite', [
                'Content-Type' => 'application/vnd.sqlite3',
            ]);
        }

        $pdo = DB::connection()->getPdo();
        $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $tables = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $database) ?: 'database';

        return response()->streamDownload(function () use ($pdo, $tables, $database): void {
            echo "-- Jephthah's Workbench MySQL database export\n-- Database: {$database}\n-- Includes credentials and application data; store securely.\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
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
        abort_if(DB::connection()->getDriverName() !== 'mysql', 404);

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
        if (DB::connection()->getDriverName() === 'sqlite') {
            $schema = DB::connection()->getSchemaBuilder();
            $columns = $schema->getColumns($table);
            abort_if($columns === [], 404);
            $primaryKeys = [];
            foreach ($schema->getIndexes($table) as $index) {
                if ($index['primary'] ?? false) {
                    $primaryKeys = $index['columns'];
                    break;
                }
            }

            return [
                'columns' => array_map(static function (array $column) use ($primaryKeys): array {
                    $isPrimary = in_array($column['name'], $primaryKeys, true);

                    return [
                        'name' => $column['name'],
                        'type' => $column['type'],
                        'nullable' => $column['nullable'],
                        'default' => $column['default'],
                        'extra' => $column['name'] === 'id' && !empty($column['auto_increment']) ? 'auto_increment' : '',
                        'key' => $isPrimary ? 'PRI' : '',
                    ];
                }, $columns),
                'primary_keys' => $primaryKeys,
            ];
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
            if ($final['role'] !== $user->role) {
                throw new RuntimeException('Account roles cannot be changed in the database editor.');
            }
            if ($final['role'] === 'staff' && $final['is_active'] !== 0) {
                throw new RuntimeException('Legacy staff accounts must remain inactive.');
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

    private function isAllowedWorkbenchStatement(string $statement): bool
    {
        return (bool) preg_match(
            '/^(?:SELECT|SHOW|DESCRIBE|DESC|EXPLAIN|WITH)\b|^(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\b|^(?:CREATE\s+(?:(?:TEMPORARY\s+)?TABLE|(?:UNIQUE\s+)?INDEX|VIEW)|ALTER\s+TABLE|DROP\s+(?:TEMPORARY\s+)?TABLE|DROP\s+(?:INDEX|VIEW)|TRUNCATE\s+(?:TABLE\s+)?|RENAME\s+TABLE|(?:OPTIMIZE|ANALYZE|CHECK|REPAIR)\s+TABLE)\b/i',
            $statement
        );
    }
}
