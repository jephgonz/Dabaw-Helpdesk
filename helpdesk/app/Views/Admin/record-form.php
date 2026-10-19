<?php
declare(strict_types=1);

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$isCreating = $mode === 'create';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#10254a">
    <title><?= $escape($pageTitle) ?> · <?= $escape($table) ?></title><link rel="icon" type="image/x-icon" href="<?= $escape($basePath) ?>/public/assets/images/SP.ico">
    <link rel="stylesheet" href="<?= $escape($basePath) ?>/public/assets/css/app.css">
</head>
<body class="dashboard-body">
    <div class="dashboard-shell">
        <?php require __DIR__ . '/partials/sidebar.php'; ?>
        <main class="dashboard-main">
            <?php require __DIR__ . '/partials/topbar.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-title-row"><div><p class="eyebrow">MYSQL TABLE · <?= $escape($table) ?></p><h1><?= $isCreating ? 'Add row' : 'Edit row' ?></h1><p class="dashboard-subtitle"><?= $isCreating ? 'Enter values for the new database record.' : 'Update the selected database record.' ?></p></div><a class="secondary-action" href="<?= $escape($basePath) ?>/admin/database/table?table=<?= rawurlencode($table) ?>">← Back to table</a></div>
                <?php if (is_array($error)): ?><div class="dashboard-notice <?= $escape($error['type']) ?>" role="alert"><?= $escape($error['message']) ?></div><?php endif; ?>
                <section class="admin-card create-user-card">
                    <div class="admin-card-heading"><div><span class="step-label">RECORD DATA</span><h2><?= $escape($table) ?></h2><p>Only columns in this table can be changed.</p></div><span class="section-mark"><?= $isCreating ? '＋' : '✎' ?></span></div>
                    <form class="account-form" method="post" action="<?= $escape($basePath) ?>/admin/database/save">
                        <input type="hidden" name="_csrf" value="<?= $escape(csrf_token()) ?>">
                        <input type="hidden" name="table" value="<?= $escape($table) ?>">
                        <input type="hidden" name="mode" value="<?= $escape($mode) ?>">
                        <?php if (!$isCreating): ?><input type="hidden" name="key" value="<?= $escape($recordKey) ?>"><?php endif; ?>
                        <?php foreach ($meta['columns'] as $column):
                            $name = $column['name'];
                            $isAuto = str_contains($column['extra'], 'auto_increment') || str_contains($column['extra'], 'generated');
                            if (preg_match('/password|secret|token|credential|salt|(?:^|_)key(?:_|$)/i', $name) || $isAuto || (!$isCreating && in_array($name, $meta['primary_keys'], true))) { continue; }
                            $required = !$column['nullable'] && $column['default'] === null;
                            $value = $record[$name] ?? '';
                            $label = ucwords(str_replace('_', ' ', $name));
                            $isBoolean = $column['data_type'] === 'tinyint' && preg_match('/^tinyint\(1\)/', $column['column_type']);
                        ?>
                            <label><?= $escape($label) ?>
                                <?php if ($column['options']): ?>
                                    <select name="values[<?= $escape($name) ?>]" <?= $required ? 'required' : '' ?>>
                                        <?php if ($column['nullable'] || $column['default'] !== null): ?><option value="">Use default<?= $column['nullable'] ? ' / NULL' : '' ?></option><?php endif; ?>
                                        <?php foreach ($column['options'] as $option): ?><option value="<?= $escape($option) ?>" <?= (string) $value === (string) $option ? 'selected' : '' ?>><?= $escape($option) ?></option><?php endforeach; ?>
                                    </select>
                                <?php elseif ($isBoolean): ?>
                                    <select name="values[<?= $escape($name) ?>]" <?= $required ? 'required' : '' ?>>
                                        <?php if ($column['nullable']): ?><option value="">NULL</option><?php endif; ?>
                                        <option value="1" <?= (string) $value === '1' ? 'selected' : '' ?>>Enabled</option>
                                        <option value="0" <?= (string) $value === '0' ? 'selected' : '' ?>>Disabled</option>
                                    </select>
                                <?php elseif (in_array($column['data_type'], ['text', 'mediumtext', 'longtext', 'json'], true)): ?>
                                    <textarea name="values[<?= $escape($name) ?>]" rows="4" <?= $required ? 'required' : '' ?>><?= $escape($value) ?></textarea>
                                <?php else: ?>
                                    <input name="values[<?= $escape($name) ?>]" type="<?= in_array($column['data_type'], ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'decimal', 'float', 'double'], true) ? 'number' : 'text' ?>" <?= in_array($column['data_type'], ['decimal', 'float', 'double'], true) ? 'step="any"' : '' ?> value="<?= $escape($value) ?>" placeholder="<?= $escape(is_string($column['default']) && !str_contains($column['default'], 'CURRENT_TIMESTAMP') ? 'Default: ' . $column['default'] : '') ?>" <?= $required ? 'required' : '' ?>>
                                <?php endif; ?>
                                <small><?= $column['nullable'] ? 'Optional; blank values are stored as NULL or use the column default.' : 'Required unless a MySQL default is configured.' ?></small>
                            </label>
                        <?php endforeach; ?>

                        <?php if ($table === 'helpdesk_users'): ?>
                            <label>Account password<?= $isCreating ? '' : ' (leave blank to keep current)' ?><input name="account_password" type="password" minlength="12" autocomplete="new-password" <?= $isCreating ? 'required' : '' ?> placeholder="At least 12 characters"><small>Passwords are hashed before saving and are never displayed.</small></label>
                        <?php endif; ?>
                        <div class="form-actions"><button class="sign-in-button" type="submit"><?= $isCreating ? 'Add row' : 'Save changes' ?> <span aria-hidden="true">→</span></button><a class="cancel-action" href="<?= $escape($basePath) ?>/admin/database/table?table=<?= rawurlencode($table) ?>">Cancel</a></div>
                    </form>
                </section>
                <footer class="dashboard-footer">Dabaw Helpdesk <span>·</span> Database records</footer>
            </div>
        </main>
    </div>
</body>
</html>
