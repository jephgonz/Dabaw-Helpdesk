<?php
declare(strict_types=1);

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#10254a">
    <title><?= $escape($table) ?> · MySQL database</title><link rel="icon" type="image/x-icon" href="<?= $escape($basePath) ?>/public/assets/images/SP.ico">
    <link rel="stylesheet" href="<?= $escape($basePath) ?>/public/assets/css/app.css">
</head>
<body class="dashboard-body">
    <div class="dashboard-shell">
        <?php require __DIR__ . '/partials/sidebar.php'; ?>
        <main class="dashboard-main">
            <?php require __DIR__ . '/partials/topbar.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-title-row"><div><p class="eyebrow">MYSQL TABLE</p><h1><?= $escape($table) ?></h1><p class="dashboard-subtitle">Browse up to 100 rows. Hidden password and secret columns are excluded.</p></div><div class="title-actions"><a class="secondary-action" href="<?= $escape($basePath) ?>/admin/database">← Tables</a><?php if ($meta['primary_keys']): ?><a class="secondary-action primary-action" href="<?= $escape($basePath) ?>/admin/database/create?table=<?= rawurlencode($table) ?>">＋ Add row</a><?php endif; ?></div></div>
                <?php if ($notice): ?><div class="dashboard-notice <?= $escape($notice['type']) ?>" role="status"><?= $escape($notice['message']) ?></div><?php endif; ?>
                <section class="admin-card accounts-card database-tables-card">
                    <div class="admin-card-heading accounts-heading"><div><span class="step-label">TABLE DATA</span><h2><?= count($rows) ?> rows shown</h2><p><?= $meta['primary_keys'] ? 'Edit or delete individual records.' : 'This table has no primary key, so its rows are read-only.' ?></p></div><span class="account-count"><?= count($meta['columns']) ?> fields</span></div>
                    <?php if (!$rows): ?>
                        <div class="empty-state"><span>▤</span><strong>Table is empty</strong><small>Add the first row using the button above.</small></div>
                    <?php else: ?>
                        <div class="table-wrap"><table class="data-manager-table"><thead><tr><?php foreach ($columns as $column): ?><th><?= $escape($column['name']) ?></th><?php endforeach; ?><?php if ($meta['primary_keys']): ?><th>Actions</th><?php endif; ?></tr></thead><tbody>
                        <?php foreach ($rows as $rowIndex => $row): ?>
                            <tr><?php foreach ($columns as $column): $value = $row[$column['name']] ?? null; ?><td><?php if ($value === null): ?><span class="null-value">NULL</span><?php else: ?><?= $escape(strlen((string) $value) > 160 ? substr((string) $value, 0, 157) . '…' : (string) $value) ?><?php endif; ?></td><?php endforeach; ?>
                                <?php if ($meta['primary_keys']): ?><td><div class="row-actions"><a class="row-edit" href="<?= $escape($basePath) ?>/admin/database/row?table=<?= rawurlencode($table) ?>&amp;key=<?= rawurlencode($rowTokens[$rowIndex]) ?>">Edit</a><form method="post" action="<?= $escape($basePath) ?>/admin/database/delete" onsubmit="return confirm('Delete this row permanently?')"><input type="hidden" name="_csrf" value="<?= $escape(csrf_token()) ?>"><input type="hidden" name="table" value="<?= $escape($table) ?>"><input type="hidden" name="key" value="<?= $escape($rowTokens[$rowIndex]) ?>"><button class="row-delete" type="submit">Delete</button></form></div></td><?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </section>
                <footer class="dashboard-footer">Dabaw Helpdesk <span>·</span> <?= $escape($table) ?></footer>
            </div>
        </main>
    </div>
</body>
</html>
