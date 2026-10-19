<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/env.php';
require __DIR__ . '/../app/Database/Connection.php';

$adminName = trim((string) getenv('INITIAL_ADMIN_NAME'));
$adminEmail = strtolower(trim((string) getenv('INITIAL_ADMIN_EMAIL')));
$adminPassword = (string) getenv('INITIAL_ADMIN_PASSWORD');

if ($adminName === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminPassword) < 12) {
    fwrite(STDERR, "Set INITIAL_ADMIN_NAME, INITIAL_ADMIN_EMAIL, and an INITIAL_ADMIN_PASSWORD of at least 12 characters in the local .env file.\n");
    exit(1);
}

try {
    $pdo = DatabaseConnection::connect();
    $migration = file_get_contents(__DIR__ . '/../database/migrations/001_create_helpdesk_users.sql');
    if ($migration === false) {
        throw new RuntimeException('The user-table migration file could not be read.');
    }
    $pdo->exec($migration);

    $existing = (int) $pdo->query('SELECT COUNT(*) FROM helpdesk_users')->fetchColumn();
    if ($existing > 0) {
        fwrite(STDERR, "Helpdesk accounts already exist. No initial admin was created.\n");
        exit(1);
    }

    $statement = $pdo->prepare('INSERT INTO helpdesk_users (display_name, email, password_hash, role, is_active) VALUES (:name, :email, :password_hash, \'admin\', 1)');
    $statement->execute([
        'name' => $adminName,
        'email' => $adminEmail,
        'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
    ]);

    $envFile = dirname(__DIR__) . '/.env';
    if (is_file($envFile) && is_writable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES);
        $lines = array_filter($lines, static fn (string $line): bool => !preg_match('/^\s*INITIAL_ADMIN_(?:NAME|EMAIL|PASSWORD)\s*=/', $line));
        file_put_contents($envFile, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
    }

    fwrite(STDOUT, "Initial admin account created. Sign in at http://localhost/helpdesk/ using the configured admin email and password.\n");
} catch (Throwable $exception) {
    error_log('Helpdesk setup error: ' . $exception->getMessage());
    fwrite(STDERR, "Setup failed. Check the local MySQL settings and Laragon's pdo_mysql extension. Details are in the PHP error log.\n");
    exit(1);
}
