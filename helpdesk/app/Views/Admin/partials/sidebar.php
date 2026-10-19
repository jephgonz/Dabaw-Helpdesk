<?php
declare(strict_types=1);
?>
<aside class="dashboard-sidebar">
    <a class="dashboard-brand" href="<?= $escape($basePath) ?>/admin"><span class="brand-mark brand-logo"><img src="<?= $escape($basePath) ?>/public/assets/images/SP.ico" alt=""></span><span>Dabaw <strong>Helpdesk</strong></span></a>
    <p class="dashboard-nav-label">ADMINISTRATION</p>
    <a class="dashboard-nav <?= $activePage === 'overview' ? 'active' : '' ?>" href="<?= $escape($basePath) ?>/admin"><span>▦</span> Overview</a>
    <a class="dashboard-nav <?= $activePage === 'users' ? 'active' : '' ?>" href="<?= $escape($basePath) ?>/admin/users"><span>♙</span> Staff accounts</a>
    <a class="dashboard-nav <?= $activePage === 'database' ? 'active' : '' ?>" href="<?= $escape($basePath) ?>/admin/database"><span>▤</span> MySQL database</a>
    <a class="dashboard-nav <?= $activePage === 'id-generator' ? 'active' : '' ?>" href="<?= $escape($basePath) ?>/admin/tools/id-generator"><span>▧</span> SP ID Generator</a>
    <div class="dashboard-sidebar-foot"><span class="online-dot"></span> Admin workspace</div>
</aside>
