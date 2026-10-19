<?php
declare(strict_types=1);

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#10254a">
    <title>Staff accounts · Dabaw Helpdesk</title><link rel="icon" type="image/x-icon" href="<?= $escape($basePath) ?>/public/assets/images/SP.ico">
    <link rel="stylesheet" href="<?= $escape($basePath) ?>/public/assets/css/app.css">
</head>
<body class="dashboard-body">
    <div class="dashboard-shell">
        <?php require __DIR__ . '/partials/sidebar.php'; ?>
        <main class="dashboard-main">
            <?php require __DIR__ . '/partials/topbar.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-title-row"><div><p class="eyebrow">ACCESS CONTROL</p><h1>Staff accounts</h1><p class="dashboard-subtitle">Review accounts and create new staff or administrator access.</p></div></div>
                <?php if ($notice): ?><div class="dashboard-notice <?= $escape($notice['type']) ?>" role="status"><?= $escape($notice['message']) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="dashboard-notice error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
                <details class="create-account-details" id="add-account" <?= $showCreateForm ? 'open' : '' ?>>
                    <summary class="secondary-action">＋ Create account</summary>
                    <section class="admin-card create-user-card">
                        <div class="admin-card-heading"><div><span class="step-label">NEW ACCOUNT</span><h2>Account details</h2><p>Staff sign in with a username; administrators sign in with email.</p></div><span class="section-mark" aria-hidden="true">＋</span></div>
                        <form class="account-form" method="post" action="<?= $escape($basePath) ?>/admin/users">
                            <input type="hidden" name="_csrf" value="<?= $escape(csrf_token()) ?>">
                            <label>Full name<input name="display_name" type="text" maxlength="120" placeholder="Staff member name" required></label>
                            <label id="username-field">Staff username<input id="new-username" name="username" type="text" minlength="3" maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,79}" placeholder="e.g. juan.delacruz" required><small>Use 3–80 letters, numbers, dots, underscores, or hyphens.</small></label>
                            <label>Email address<input name="email" type="email" maxlength="190" placeholder="name@organization.gov.ph" required><small>Contact address for the account.</small></label>
                            <label>Initial password<input name="password" type="password" minlength="12" autocomplete="new-password" placeholder="At least 12 characters" required><small>Share this password securely with the account holder.</small></label>
                            <label>Account role<select id="account-role" name="role"><option value="agent">Staff</option><option value="admin">Administrator</option></select></label>
                            <div class="form-actions"><button class="sign-in-button" type="submit">Create account <span aria-hidden="true">→</span></button></div>
                        </form>
                    </section>
                </details>
                <section class="admin-card accounts-card directory-card">
                    <div class="admin-card-heading accounts-heading"><div><span class="step-label">USER DIRECTORY</span><h2>All accounts</h2><p>Staff sign in with a username. Administrators sign in with email.</p></div><span class="account-count"><?= count($users) ?> total</span></div>
                    <?php if (!$users): ?>
                        <div class="empty-state"><span>♙</span><strong>No accounts yet</strong><small>Create an account to begin.</small></div>
                    <?php else: ?>
                        <div class="table-wrap"><table><thead><tr><th>Account</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th></tr></thead><tbody>
                        <?php foreach ($users as $account): ?>
                            <tr><td><strong><?= $escape($account['display_name']) ?></strong><small><?= $escape($account['email']) ?></small></td><td><?= $account['username'] !== null ? $escape($account['username']) : '<span class="muted-cell">Email sign-in</span>' ?></td><td><span class="role-tag"><?= $escape(ucfirst($account['role'])) ?></span></td><td><span class="state-tag <?= (int) $account['is_active'] === 1 ? 'is-active' : 'is-inactive' ?>"><?= (int) $account['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td><td><?= $escape(date('M j, Y', strtotime((string) $account['created_at']))) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </section>
                <footer class="dashboard-footer">Dabaw Helpdesk <span>·</span> Staff directory</footer>
            </div>
        </main>
    </div>
    <script>
        (() => {
            const role = document.getElementById('account-role');
            const field = document.getElementById('username-field');
            const username = document.getElementById('new-username');
            if (!role || !field || !username) return;
            const updateUsername = () => {
                const isStaff = role.value === 'agent';
                field.hidden = !isStaff;
                username.required = isStaff;
                username.disabled = !isStaff;
            };
            role.addEventListener('change', updateUsername);
            updateUsername();
        })();
    </script>
</body>
</html>
