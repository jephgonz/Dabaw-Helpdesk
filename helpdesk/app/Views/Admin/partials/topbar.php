<?php
declare(strict_types=1);
?>
<header class="dashboard-topbar">
    <div><span class="dashboard-crumb">Helpdesk /</span> <?= $escape($pageTitle) ?></div>
    <div class="topbar-user"><span class="admin-pill"><span></span>Admin access</span><span class="user-badge"><?= $escape(strtoupper(substr($admin['name'], 0, 1))) ?></span><span><?= $escape($admin['name']) ?></span><form method="post" action="<?= $escape($basePath) ?>/logout"><input type="hidden" name="_csrf" value="<?= $escape(csrf_token()) ?>"><button class="logout-button" type="submit">Sign out</button></form></div>
</header>
