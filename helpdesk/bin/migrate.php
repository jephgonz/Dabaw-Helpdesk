<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/env.php';
require __DIR__ . '/../app/Database/Connection.php';

try {
    $pdo = DatabaseConnection::connect();
    $column = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'helpdesk_users' AND COLUMN_NAME = 'username'");
    $column->execute();
    if ((int) $column->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE helpdesk_users ADD COLUMN username VARCHAR(80) NULL AFTER display_name');
    }

    $index = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'helpdesk_users' AND INDEX_NAME = 'uq_helpdesk_users_username'");
    $index->execute();
    if ((int) $index->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE helpdesk_users ADD UNIQUE KEY uq_helpdesk_users_username (username)');
    }

    $existingStaff = $pdo->query("SELECT id, email FROM helpdesk_users WHERE role = 'agent' AND username IS NULL ORDER BY id")->fetchAll();
    $update = $pdo->prepare('UPDATE helpdesk_users SET username = :username WHERE id = :id AND username IS NULL');
    foreach ($existingStaff as $staff) {
        $localPart = strtolower((string) strstr((string) $staff['email'], '@', true));
        $base = preg_replace('/[^a-z0-9._-]+/', '.', $localPart) ?: 'staff';
        $id = (int) $staff['id'];
        $base = trim(substr($base, 0, max(1, 78 - strlen((string) $id))), '._-');
        if ($base === '') {
            $base = 'staff';
        }
        $update->execute(['username' => $base . '-' . $id, 'id' => $id]);
    }

    fwrite(STDOUT, "Username schema is ready. Existing staff usernames were generated from their email addresses and account IDs.\n");
} catch (Throwable $exception) {
    error_log('Helpdesk migration error: ' . $exception->getMessage());
    fwrite(STDERR, "Migration failed. Check database access and the PHP error log.\n");
    exit(1);
}
