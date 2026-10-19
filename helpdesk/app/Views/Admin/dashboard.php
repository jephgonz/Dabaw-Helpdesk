<?php
declare(strict_types=1);

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$activeCount = count(array_filter($users, static fn (array $account): bool => (int) $account['is_active'] === 1));
$adminCount = count(array_filter($users, static fn (array $account): bool => $account['role'] === 'admin'));
$activePage = 'overview';
$pageTitle = 'Administration';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10254a">
    <title>Admin dashboard · Dabaw Helpdesk</title>
    <link rel="icon" type="image/x-icon" href="<?= $escape($basePath) ?>/public/assets/images/SP.ico">
    <link rel="stylesheet" href="<?= $escape($basePath) ?>/public/assets/css/app.css">
</head>
<body class="dashboard-body">
    <div class="dashboard-shell">
        <?php require __DIR__ . '/partials/sidebar.php'; ?>
        <main class="dashboard-main">
            <?php require __DIR__ . '/partials/topbar.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-title-row"><div><p class="eyebrow">ADMIN WORKSPACE</p><h1>Overview</h1><p class="dashboard-subtitle">Manage staff accounts and monitor the helpdesk database.</p></div></div>
                <?php if ($notice): ?><div class="dashboard-notice <?= $escape($notice['type']) ?>" role="status"><?= $escape($notice['message']) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="dashboard-notice error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
                <section class="stat-grid" aria-label="Account summary">
                    <article class="stat-card"><span class="stat-icon blue">♙</span><span class="stat-label">Total accounts</span><strong><?= count($users) ?></strong><small>Helpdesk users</small></article>
                    <article class="stat-card"><span class="stat-icon green">✓</span><span class="stat-label">Active accounts</span><strong><?= $activeCount ?></strong><small>Can sign in</small></article>
                    <article class="stat-card"><span class="stat-icon gold">♜</span><span class="stat-label">Administrators</span><strong><?= $adminCount ?></strong><small>Can manage accounts</small></article>
                </section>
                <section class="quick-links" aria-label="Admin shortcuts">
                    <a class="quick-link" href="<?= $escape($basePath) ?>/admin/users?create=1#add-account"><span class="quick-link-icon">＋</span><span><strong>Create staff account</strong><small>Add a staff member and issue their login details.</small></span><span class="quick-link-arrow">→</span></a>
                    <a class="quick-link" href="<?= $escape($basePath) ?>/admin/users"><span class="quick-link-icon">♙</span><span><strong>Staff accounts</strong><small>Review account names, usernames, roles, and status.</small></span><span class="quick-link-arrow">→</span></a>
                    <a class="quick-link" href="<?= $escape($basePath) ?>/admin/database"><span class="quick-link-icon">▤</span><span><strong>MySQL database</strong><small>View connection status and tables used by Helpdesk.</small></span><span class="quick-link-arrow">→</span></a>
                </section>
                <footer class="dashboard-footer">Dabaw Helpdesk <span>·</span> Account administration</footer>
            </div>
        </main>
    </div>
</body>
</html>
