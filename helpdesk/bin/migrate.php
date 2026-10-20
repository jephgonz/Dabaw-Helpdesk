<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

try {
    $app = require __DIR__ . '/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
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

    $existingStaff = $pdo->query("SELECT id, email FROM helpdesk_users WHERE role IN ('agent', 'staff') AND username IS NULL ORDER BY id")->fetchAll();
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

    $emailIndex = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'helpdesk_users' AND INDEX_NAME = 'uq_helpdesk_users_email'");
    $emailIndex->execute();
    if ((int) $emailIndex->fetchColumn() > 0) {
        $pdo->exec('ALTER TABLE helpdesk_users DROP INDEX uq_helpdesk_users_email');
    }

    $pdo->exec("ALTER TABLE helpdesk_users MODIFY role ENUM('admin', 'agent', 'staff') NOT NULL DEFAULT 'admin'");
    $pdo->exec("UPDATE helpdesk_users SET role = 'staff' WHERE role = 'agent'");
    $pdo->exec("ALTER TABLE helpdesk_users MODIFY role ENUM('admin', 'staff') NOT NULL DEFAULT 'admin'");
    $roleType = $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'helpdesk_users' AND COLUMN_NAME = 'role'")->fetchColumn();
    if (strtolower((string) $roleType) !== "enum('admin','staff')") {
        throw new RuntimeException('The helpdesk role column does not have the expected admin/staff values.');
    }

    $ticketMigration = file_get_contents(__DIR__ . '/../database/migrations/002_create_support_tickets.sql');
    if ($ticketMigration === false) {
        throw new RuntimeException('The support ticket migration file could not be read.');
    }
    $pdo->exec($ticketMigration);

    $resolvedAtColumn = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'helpdesk_tickets' AND COLUMN_NAME = 'resolved_at'");
    $resolvedAtColumn->execute();
    if ((int) $resolvedAtColumn->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE helpdesk_tickets ADD COLUMN resolved_at TIMESTAMP NULL DEFAULT NULL AFTER status');
    }
    $pdo->exec("UPDATE helpdesk_tickets SET resolved_at = updated_at WHERE status = 'resolved' AND resolved_at IS NULL");

    $resolvedAtIndex = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'helpdesk_tickets' AND INDEX_NAME = 'idx_helpdesk_tickets_resolved_at'");
    $resolvedAtIndex->execute();
    if ((int) $resolvedAtIndex->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE helpdesk_tickets ADD KEY idx_helpdesk_tickets_resolved_at (resolved_at)');
    }

    fwrite(STDOUT, "Helpdesk account and support ticket schemas are ready. Existing staff usernames and roles were migrated where needed, and duplicate emails are allowed.\n");
} catch (Throwable $exception) {
    error_log('Helpdesk migration error: ' . $exception->getMessage());
    fwrite(STDERR, "Migration failed. Check database access and the PHP error log.\n");
    exit(1);
}
