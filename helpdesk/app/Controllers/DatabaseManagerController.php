<?php
declare(strict_types=1);

final class DatabaseManagerController
{
    public function index(array $config): void
    {
        $admin = require_admin_user($config['base_path']);
        $database = '';
        $databaseName = '';
        $connectionConfig = require __DIR__ . '/../../config/database.php';
        $serverVersion = '';
        $tables = [];
        $error = null;
        try {
            $pdo = DatabaseConnection::connect();
            $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            $databaseName = $database;
            $serverVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $tables = $pdo->query("SELECT TABLE_NAME AS table_name, ENGINE AS engine, TABLE_ROWS AS estimated_rows, DATA_LENGTH + INDEX_LENGTH AS total_bytes FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll();
        } catch (Throwable $exception) {
            error_log('Helpdesk database overview error: ' . $exception->getMessage());
            $error = 'The MySQL database could not be reached. Check the database configuration.';
        }
        $basePath = $config['base_path'];
        $activePage = 'database';
        $pageTitle = 'MySQL database';
        $notice = take_flash_message();
        require __DIR__ . '/../Views/Admin/database.php';
    }

    public function metrics(array $config): void
    {
        require_admin_user($config['base_path']);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');

        try {
            $pdo = DatabaseConnection::connect();
            $statusRows = $pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected', 'Threads_running', 'Max_used_connections', 'Connections', 'Uptime')")->fetchAll(PDO::FETCH_KEY_PAIR);
            $variableRows = $pdo->query("SHOW GLOBAL VARIABLES WHERE Variable_name = 'max_connections'")->fetchAll(PDO::FETCH_KEY_PAIR);
            echo json_encode([
                'connected' => (int) ($statusRows['Threads_connected'] ?? 0),
                'running' => (int) ($statusRows['Threads_running'] ?? 0),
                'maxUsed' => (int) ($statusRows['Max_used_connections'] ?? 0),
                'maxConnections' => (int) ($variableRows['max_connections'] ?? 0),
                'connectionsSinceUptime' => (int) ($statusRows['Connections'] ?? 0),
                'uptime' => (int) ($statusRows['Uptime'] ?? 0),
                'sampledAt' => gmdate('c'),
            ], JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            error_log('Helpdesk database metrics error: ' . $exception->getMessage());
            http_response_code(503);
            echo json_encode(['error' => 'Database metrics are unavailable.']);
        }
    }
    public function table(array $config): void
    {
        $admin = require_admin_user($config['base_path']);
        $pdo = DatabaseConnection::connect();
        $table = trim((string) ($_GET['table'] ?? ''));
        try {
            $meta = $this->tableMeta($pdo, $table);
            $columns = array_values(array_filter($meta['columns'], fn (array $column): bool => !$this->isHiddenColumn($column['name'])));
            if (!$columns) {
                throw new RuntimeException('No visible columns in this table.');
            }
            $selectedColumns = array_values(array_filter($meta['columns'], fn (array $column): bool => !$this->isHiddenColumn($column['name']) || in_array($column['name'], $meta['primary_keys'], true)));
            $select = implode(', ', array_map(fn (array $column): string => $this->quoteIdentifier($column['name']), $selectedColumns));
            $order = $meta['primary_keys'] ? ' ORDER BY ' . implode(', ', array_map(fn (string $key): string => $this->quoteIdentifier($key), $meta['primary_keys'])) : '';
            $statement = $pdo->query('SELECT ' . $select . ' FROM ' . $this->quoteIdentifier($table) . $order . ' LIMIT 100');
            $rows = $statement->fetchAll();
            $rowTokens = [];
            if ($meta['primary_keys']) {
                foreach ($rows as $row) {
                    $rowTokens[] = $this->encodeKey($row, $meta['primary_keys']);
                }
            }
            $basePath = $config['base_path'];
            $activePage = 'database';
            $pageTitle = $table;
            $notice = take_flash_message();
            require __DIR__ . '/../Views/Admin/table.php';
        } catch (Throwable $exception) {
            error_log('Helpdesk database table view error: ' . $exception->getMessage());
            flash_message('error', 'That table could not be opened.');
            redirect_to('/admin/database', $config['base_path']);
        }
    }

    public function createForm(array $config): void
    {
        $admin = require_admin_user($config['base_path']);
        $pdo = DatabaseConnection::connect();
        try {
            $table = trim((string) ($_GET['table'] ?? ''));
            $meta = $this->tableMeta($pdo, $table);
            $this->assertEditable($meta, true);
            $record = [];
            $recordKey = '';
            $mode = 'create';
            $basePath = $config['base_path'];
            $activePage = 'database';
            $pageTitle = 'Add row';
            $error = take_flash_message();
            require __DIR__ . '/../Views/Admin/record-form.php';
        } catch (Throwable $exception) {
            error_log('Helpdesk database create form error: ' . $exception->getMessage());
            flash_message('error', 'A new row cannot be created in that table.');
            redirect_to('/admin/database', $config['base_path']);
        }
    }

    public function editForm(array $config): void
    {
        $admin = require_admin_user($config['base_path']);
        $pdo = DatabaseConnection::connect();
        try {
            $table = trim((string) ($_GET['table'] ?? ''));
            $meta = $this->tableMeta($pdo, $table);
            $this->assertEditable($meta, false);
            $recordKey = $this->decodeKey((string) ($_GET['key'] ?? ''), $meta['primary_keys']);
            $record = $this->findRecord($pdo, $meta, $recordKey, false);
            if ($record === null) {
                throw new RuntimeException('Row not found.');
            }
            $mode = 'edit';
            $basePath = $config['base_path'];
            $activePage = 'database';
            $pageTitle = 'Edit row';
            $error = take_flash_message();
            require __DIR__ . '/../Views/Admin/record-form.php';
        } catch (Throwable $exception) {
            error_log('Helpdesk database edit form error: ' . $exception->getMessage());
            flash_message('error', 'That row could not be opened for editing.');
            redirect_to('/admin/database', $config['base_path']);
        }
    }

    public function save(array $config): void
    {
        require_admin_user($config['base_path']);
        verify_csrf();
        $pdo = DatabaseConnection::connect();
        $table = trim((string) ($_POST['table'] ?? ''));
        $mode = (string) ($_POST['mode'] ?? '');

        try {
            if (!in_array($mode, ['create', 'edit'], true)) {
                throw new RuntimeException('Invalid operation.');
            }
            $meta = $this->tableMeta($pdo, $table);
            $this->assertEditable($meta, $mode === 'create');
            $key = $mode === 'edit' ? $this->decodeKey((string) ($_POST['key'] ?? ''), $meta['primary_keys']) : [];
            $original = $mode === 'edit' ? $this->findRecord($pdo, $meta, $key, true) : null;
            if ($mode === 'edit' && $original === null) {
                throw new RuntimeException('Row not found.');
            }

            $submitted = is_array($_POST['values'] ?? null) ? $_POST['values'] : [];
            $fields = [];
            $values = [];
            foreach ($meta['columns'] as $column) {
                $name = $column['name'];
                if ($this->isHiddenColumn($name) || ($mode === 'edit' && in_array($name, $meta['primary_keys'], true)) || str_contains($column['extra'], 'auto_increment') || str_contains($column['extra'], 'generated') || !array_key_exists($name, $submitted)) {
                    continue;
                }
                $raw = is_scalar($submitted[$name]) ? trim((string) $submitted[$name]) : '';
                if ($raw === '' && $column['nullable'] && $column['default'] === null) {
                    $values[$name] = null;
                } elseif ($raw === '' && $column['default'] !== null) {
                    continue;
                } else {
                    $values[$name] = $raw;
                }
                $fields[] = $name;
            }

            if ($table === 'helpdesk_users') {
                $this->validateHelpdeskUser($values, $mode);
                $password = (string) ($_POST['account_password'] ?? '');
                if ($mode === 'create' || $password !== '') {
                    if (strlen($password) < 12) {
                        throw new RuntimeException('Use a password with at least 12 characters.');
                    }
                    $values['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    $fields[] = 'password_hash';
                }
                if ($mode === 'edit') {
                    $this->preventLastAdminChange($pdo, $original, $values, $key);
                }
            }

            foreach ($meta['columns'] as $column) {
                $name = $column['name'];
                if (($mode === 'edit' && in_array($name, $meta['primary_keys'], true)) || $this->isHiddenColumn($name) || str_contains($column['extra'], 'auto_increment') || str_contains($column['extra'], 'generated')) {
                    continue;
                }
                if ($mode === 'create' && !$column['nullable'] && $column['default'] === null && !array_key_exists($name, $values)) {
                    throw new RuntimeException('Enter a value for ' . $name . '.');
                }
            }

            if ($mode === 'create') {
                $this->insertRow($pdo, $table, $values);
            } else {
                $this->updateRow($pdo, $meta, $key, $values, $fields);
            }
            flash_message('success', $mode === 'create' ? 'Row added.' : 'Row updated.');
        } catch (Throwable $exception) {
            error_log('Helpdesk database save error: ' . $exception->getMessage());
            flash_message('error', $exception instanceof RuntimeException && $exception->getMessage() !== 'Row not found.' ? $exception->getMessage() : 'The row could not be saved. Check field values and database constraints.');
            if ($mode === 'edit') {
                $keyToken = rawurlencode((string) ($_POST['key'] ?? ''));
                redirect_to('/admin/database/row?table=' . rawurlencode($table) . '&key=' . $keyToken, $config['base_path']);
            }
            redirect_to('/admin/database/create?table=' . rawurlencode($table), $config['base_path']);
        }

        redirect_to('/admin/database/table?table=' . rawurlencode($table), $config['base_path']);
    }

    public function delete(array $config): void
    {
        require_admin_user($config['base_path']);
        verify_csrf();
        $pdo = DatabaseConnection::connect();
        $table = trim((string) ($_POST['table'] ?? ''));
        try {
            $meta = $this->tableMeta($pdo, $table);
            $this->assertEditable($meta, false);
            $key = $this->decodeKey((string) ($_POST['key'] ?? ''), $meta['primary_keys']);
            if ($table === 'helpdesk_users') {
                $record = $this->findRecord($pdo, $meta, $key, true);
                if ($record === null) {
                    throw new RuntimeException('Row not found.');
                }
                if ((int) $record['id'] === (int) ($_SESSION['user']['id'] ?? 0)) {
                    throw new RuntimeException('You cannot delete your own administrator account.');
                }
                $this->preventLastAdminChange($pdo, $record, ['is_active' => 0], $key);
            }
            $where = $this->whereClause($key, $params);
            $statement = $pdo->prepare('DELETE FROM ' . $this->quoteIdentifier($table) . ' WHERE ' . $where);
            $statement->execute($params);
            flash_message('success', 'Row deleted.');
        } catch (Throwable $exception) {
            error_log('Helpdesk database delete error: ' . $exception->getMessage());
            flash_message('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'The row could not be deleted. It may be referenced by another record.');
        }
        redirect_to('/admin/database/table?table=' . rawurlencode($table), $config['base_path']);
    }

    private function tableMeta(PDO $pdo, string $table): array
    {
        $statement = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME = :table LIMIT 1");
        $statement->execute(['table' => $table]);
        $found = $statement->fetchColumn();
        if (!is_string($found)) {
            throw new RuntimeException('Unknown table.');
        }

        $statement = $pdo->prepare('SELECT COLUMN_NAME AS name, DATA_TYPE AS data_type, COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default, COLUMN_KEY AS column_key, EXTRA AS extra FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION');
        $statement->execute(['table' => $found]);
        $columns = $statement->fetchAll();
        $primaryKeys = [];
        foreach ($columns as &$column) {
            $column['nullable'] = $column['is_nullable'] === 'YES';
            $column['default'] = $column['column_default'];
            $column['options'] = [];
            if (in_array($column['data_type'], ['enum', 'set'], true)) {
                $definition = (string) $column['column_type'];
                $start = strpos($definition, '(');
                $column['options'] = $start === false ? [] : str_getcsv(substr($definition, $start + 1, -1), ',', "'", '\\');
            }
            if ($column['column_key'] === 'PRI') {
                $primaryKeys[] = $column['name'];
            }
        }
        unset($column);
        return ['name' => $found, 'columns' => $columns, 'primary_keys' => $primaryKeys];
    }

    private function assertEditable(array $meta, bool $creating): void
    {
        if (!$meta['primary_keys']) {
            throw new RuntimeException('This table has no primary key and is read-only here.');
        }
        if ($creating) {
            foreach ($meta['columns'] as $column) {
                if ($this->isHiddenColumn($column['name']) && $column['default'] === null && !$column['nullable'] && !str_contains($column['extra'], 'auto_increment')) {
                    if ($meta['name'] !== 'helpdesk_users' || $column['name'] !== 'password_hash') {
                        throw new RuntimeException('This table has a required protected field and cannot be created from this page.');
                    }
                }
            }
        }
    }

    private function isHiddenColumn(string $name): bool
    {
        return (bool) preg_match('/password|secret|token|credential|salt|(?:^|_)key(?:_|$)/i', $name);
    }

    private function decodeKey(string $token, array $primaryKeys): array
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        $key = is_string($decoded) ? json_decode($decoded, true) : null;
        if (!is_array($key) || count($key) !== count($primaryKeys)) {
            throw new RuntimeException('Invalid row key.');
        }
        $result = [];
        foreach ($primaryKeys as $name) {
            if (!array_key_exists($name, $key) || !is_scalar($key[$name])) {
                throw new RuntimeException('Invalid row key.');
            }
            $result[$name] = $key[$name];
        }
        return $result;
    }

    private function encodeKey(array $row, array $primaryKeys): string
    {
        $key = [];
        foreach ($primaryKeys as $name) {
            $key[$name] = $row[$name];
        }
        return rtrim(strtr(base64_encode((string) json_encode($key, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function findRecord(PDO $pdo, array $meta, array $key, bool $includeHidden): ?array
    {
        $columns = array_filter($meta['columns'], fn (array $column): bool => $includeHidden || !$this->isHiddenColumn($column['name']) || in_array($column['name'], $meta['primary_keys'], true));
        $select = implode(', ', array_map(fn (array $column): string => $this->quoteIdentifier($column['name']), $columns));
        $where = $this->whereClause($key, $params);
        $statement = $pdo->prepare('SELECT ' . $select . ' FROM ' . $this->quoteIdentifier($meta['name']) . ' WHERE ' . $where . ' LIMIT 1');
        $statement->execute($params);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    private function whereClause(array $key, ?array &$params): string
    {
        $conditions = [];
        $params = [];
        foreach ($key as $index => $value) {
            $placeholder = 'key_' . $index;
            $conditions[] = $this->quoteIdentifier((string) $index) . ' = :' . $placeholder;
            $params[$placeholder] = $value;
        }
        return implode(' AND ', $conditions);
    }

    private function insertRow(PDO $pdo, string $table, array $values): void
    {
        if (!$values) {
            throw new RuntimeException('Enter values for the new row.');
        }
        $names = array_keys($values);
        $columns = implode(', ', array_map($this->quoteIdentifier(...), $names));
        $placeholders = implode(', ', array_map(static fn (int $index): string => ':value_' . $index, array_keys($names)));
        $params = [];
        foreach ($names as $index => $name) {
            $params['value_' . $index] = $values[$name];
        }
        $pdo->prepare('INSERT INTO ' . $this->quoteIdentifier($table) . ' (' . $columns . ') VALUES (' . $placeholders . ')')->execute($params);
    }

    private function updateRow(PDO $pdo, array $meta, array $key, array $values, array $fields): void
    {
        $fields = array_values(array_unique($fields));
        if (!$fields) {
            throw new RuntimeException('No editable fields were submitted.');
        }
        $sets = [];
        $params = [];
        foreach ($fields as $index => $name) {
            $placeholder = 'value_' . $index;
            $sets[] = $this->quoteIdentifier($name) . ' = :' . $placeholder;
            $params[$placeholder] = $values[$name];
        }
        $where = $this->whereClause($key, $whereParams);
        $statement = $pdo->prepare('UPDATE ' . $this->quoteIdentifier($meta['name']) . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where);
        $statement->execute(array_merge($params, $whereParams));
    }

    private function validateHelpdeskUser(array &$values, string $mode): void
    {
        $name = trim((string) ($values['display_name'] ?? ''));
        $email = strtolower(trim((string) ($values['email'] ?? '')));
        $role = (string) ($values['role'] ?? 'agent');
        $username = strtolower(trim((string) ($values['username'] ?? '')));
        if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            throw new RuntimeException('Enter a valid display name and email address.');
        }
        if (!in_array($role, ['admin', 'agent'], true)) {
            throw new RuntimeException('Choose a valid user role.');
        }
        if ($role === 'agent' && !preg_match('/^[a-z0-9][a-z0-9._-]{2,79}$/', $username)) {
            throw new RuntimeException('Staff usernames must be 3–80 characters and use letters, numbers, dots, underscores, or hyphens.');
        }
        $values['display_name'] = $name;
        $values['email'] = $email;
        $values['role'] = $role;
        $values['username'] = $role === 'admin' ? null : $username;
    }

    private function preventLastAdminChange(PDO $pdo, array $original, array $changes, array $key): void
    {
        $wasActiveAdmin = ($original['role'] ?? '') === 'admin' && (int) ($original['is_active'] ?? 0) === 1;
        $willBeActiveAdmin = ($changes['role'] ?? $original['role'] ?? '') === 'admin' && (int) ($changes['is_active'] ?? $original['is_active'] ?? 0) === 1;
        if ($wasActiveAdmin && !$willBeActiveAdmin) {
            $activeAdmins = (int) $pdo->query("SELECT COUNT(*) FROM helpdesk_users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
            if ($activeAdmins <= 1) {
                throw new RuntimeException('The last active administrator cannot be disabled, demoted, or deleted.');
            }
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
