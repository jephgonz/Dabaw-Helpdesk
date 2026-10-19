<?php
declare(strict_types=1);

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff workspace · Dabaw Helpdesk</title>
    <link rel="icon" type="image/x-icon" href="<?= $escape($basePath) ?>/public/assets/images/SP.ico">
    <link rel="stylesheet" href="<?= $escape($basePath) ?>/public/assets/css/app.css">
</head>
<body class="dashboard-body"><main class="staff-welcome"><span class="brand-mark brand-logo"><img src="<?= $escape($basePath) ?>/public/assets/images/SP.ico" alt=""></span><p class="eyebrow">STAFF WORKSPACE</p><h1>Welcome, <?= $escape($user['name']) ?></h1><p>Your account is ready. Ticket management can be added to this workspace next.</p><form method="post" action="<?= $escape($basePath) ?>/logout"><input type="hidden" name="_csrf" value="<?= $escape(csrf_token()) ?>"><button class="sign-in-button" type="submit">Sign out</button></form></main></body>
</html>
