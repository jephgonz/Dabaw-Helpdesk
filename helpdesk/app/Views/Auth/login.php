<?php
declare(strict_types=1);

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10254a">
    <title>Sign in · <?= $escape($appName) ?></title>
    <link rel="icon" type="image/x-icon" href="<?= $escape($basePath) ?>/public/assets/images/SP.ico">
    <link rel="stylesheet" href="<?= $escape($basePath) ?>/public/assets/css/app.css">
</head>
<body>
    <main class="login-shell">
        <section class="welcome-panel" aria-label="Helpdesk introduction">
            <a class="brand" href="<?= $escape($basePath) ?>/" aria-label="Dabaw Helpdesk home"><span class="brand-mark brand-logo"><img src="<?= $escape($basePath) ?>/public/assets/images/SP.ico" alt=""></span><span class="brand-name">Dabaw <strong>Helpdesk</strong></span></a>
            <div class="welcome-copy">
                <span class="eyebrow"><span class="eyebrow-dot"></span> STAFF SUPPORT PORTAL</span>
                <h1>How can we<br>help you today?</h1>
                <p>Sign in to request support, follow your tickets, and keep work moving.</p>
                <div class="support-illustration" aria-hidden="true"><div class="illustration-orbit orbit-one"></div><div class="illustration-orbit orbit-two"></div><div class="ticket-card"><div class="ticket-top"><span class="ticket-symbol">✓</span><span class="ticket-status">SUPPORT REQUEST</span><span class="ticket-dots">•••</span></div><div class="ticket-line line-long"></div><div class="ticket-line line-short"></div><div class="ticket-bottom"><span class="ticket-avatar">SP</span><span>Help is on the way</span><span class="ticket-arrow">↗</span></div></div><span class="float-star star-one">✦</span><span class="float-star star-two">✦</span></div>
            </div>
            <div class="welcome-footer"><span class="online-dot"></span> Support portal <span class="footer-divider">·</span> City of Davao</div>
        </section>

        <section class="form-panel" aria-labelledby="login-title">
            <div class="mobile-brand"><span class="brand-mark brand-logo"><img src="<?= $escape($basePath) ?>/public/assets/images/SP.ico" alt=""></span><span>Dabaw <strong>Helpdesk</strong></span></div>
            <div class="login-card">
                <div class="login-heading">
                    <span class="login-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M7 10V7a5 5 0 0 1 10 0v3M6 10h12a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Zm6 5v2"/></svg></span>
                    <p class="eyebrow">WELCOME BACK</p>
                    <h2 id="login-title"><?= $mode === 'admin' ? 'Administrator sign in' : 'Staff sign in' ?></h2>
                    <p class="login-subtitle"><?= $mode === 'admin' ? 'Use your administrator email and password.' : 'Use the username and password provided by your administrator.' ?></p>
                </div>
                <nav class="auth-tabs" aria-label="Choose account type">
                    <a class="<?= $mode === 'staff' ? 'active' : '' ?>" href="<?= $escape($basePath) ?>/login?type=staff" <?= $mode === 'staff' ? 'aria-current="page"' : '' ?>>Staff</a>
                    <a class="<?= $mode === 'admin' ? 'active' : '' ?>" href="<?= $escape($basePath) ?>/login?type=admin" <?= $mode === 'admin' ? 'aria-current="page"' : '' ?>>Administrator</a>
                </nav>
                <?php if ($error !== null): ?><div class="notice" role="alert"><?= $escape($error) ?></div><?php endif; ?>
                <form class="login-form" method="post" action="<?= $escape($basePath) ?>/login" autocomplete="on">
                    <input type="hidden" name="_csrf" value="<?= $escape(csrf_token()) ?>">
                    <input type="hidden" name="login_action" value="<?= $mode === 'admin' ? 'admin_login' : 'staff_login' ?>">
                    <?php if ($mode === 'admin'): ?>
                        <div class="field"><label for="email">Administrator email</label><div class="input-wrap"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg><input id="email" name="email" type="email" autocomplete="username" placeholder="admin@organization.gov.ph" required></div></div>
                    <?php else: ?>
                        <div class="field"><label for="username">Username</label><div class="input-wrap"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><input id="username" name="username" type="text" maxlength="80" autocomplete="username" placeholder="Enter your staff username" required></div></div>
                    <?php endif; ?>
                    <div class="field"><label for="password">Password</label><div class="input-wrap"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required></div></div>
                    <button class="sign-in-button" type="submit">Sign in <span aria-hidden="true">→</span></button>
                </form>
                <p class="access-note"><span aria-hidden="true">ⓘ</span> Need an account? Contact your helpdesk administrator.</p>
                <div class="login-divider"><span></span><span><?= $mode === 'admin' ? 'ADMINISTRATOR ACCESS' : 'SECURE STAFF ACCESS' ?></span><span></span></div>
            </div>
            <footer class="form-footer"><span>© <?= date('Y') ?> Dabaw Helpdesk</span><span>For authorized staff</span></footer>
        </section>
    </main>
</body>
</html>
