<?php
declare(strict_types=1);

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#10254a">
    <title>SP ID Generator · Dabaw Helpdesk</title><link rel="icon" type="image/x-icon" href="<?= $escape($basePath) ?>/public/assets/images/SP.ico">
    <link rel="stylesheet" href="<?= $escape($basePath) ?>/public/assets/css/app.css">
</head>
<body class="dashboard-body">
    <div class="dashboard-shell">
        <?php require __DIR__ . '/partials/sidebar.php'; ?>
        <main class="dashboard-main">
            <?php require __DIR__ . '/partials/topbar.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-title-row"><div><p class="eyebrow">ADMIN TOOL</p><h1>SP ID Generator</h1><p class="dashboard-subtitle">Generate and download employee identification cards.</p></div></div>
                <?php if ($error !== null): ?><div class="dashboard-notice error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
                <?php if (is_array($notice) && !empty($notice['message'])): ?><div class="dashboard-notice" role="status"><?= $escape((string) $notice['message']) ?></div><?php endif; ?>
                <div class="admin-grid">
                    <section class="admin-card" aria-labelledby="form-title">
                        <div class="admin-card-heading"><div><span class="step-label">STEP 01</span><h2 id="form-title">Employee details</h2><p>Enter the information exactly as it should appear on the card.</p></div><span class="section-mark" aria-hidden="true">✎</span></div>
                        <form class="account-form idgen-form" method="post" action="<?= $escape($basePath) ?>/admin/tools/id-generator" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="_csrf" value="<?= $escape(csrf_token()) ?>">
                            <input type="hidden" name="_id_generator_csrf" value="<?= $escape($generatorCsrf) ?>">
                            <label class="id-picture-field" for="image">ID picture <span aria-hidden="true">*</span><input id="image" type="file" name="image" accept="image/jpeg,.jpg,.jpeg" aria-describedby="image-help" required><small id="image-help">Choose a clear JPEG photo with a white background.</small></label>
                            <div class="idgen-fields"><label for="nickname">Nickname<input id="nickname" type="text" placeholder="e.g. Alex" name="nickname" value="<?= $escape((string) ($_POST['nickname'] ?? '')) ?>" required></label>
                            <label for="fullname">Full name<input id="fullname" type="text" placeholder="e.g. Alex Santos" name="fullname" value="<?= $escape((string) ($_POST['fullname'] ?? '')) ?>" required></label>
                            <label for="position">Position<input id="position" type="text" placeholder="e.g. Administrative Officer" name="position" value="<?= $escape((string) ($_POST['position'] ?? '')) ?>" required></label>
                            <label for="empnumber">Employee number<input id="empnumber" type="number" placeholder="Enter employee number" name="empnumber" value="<?= $escape((string) ($_POST['empnumber'] ?? '')) ?>" required></label>
                            <label for="fontsize">Name font size<input id="fontsize" type="number" min="10" max="120" name="fontsize" value="<?= $escape((string) ($_POST['fontsize'] ?? '70')) ?>"><small>Reduce if the name is long.</small></label>
                            <label for="fontsize2">Position font size<input id="fontsize2" type="number" min="10" max="80" name="fontsize2" value="<?= $escape((string) ($_POST['fontsize2'] ?? '35')) ?>"><small>Adjust to fit the position.</small></label>
                            </div><button class="sign-in-button" type="submit" name="process">Generate ID <span aria-hidden="true">→</span></button>
                        </form>
                    </section>
                    <section class="admin-card" aria-labelledby="preview-title">
                        <div class="admin-card-heading"><div><span class="step-label">STEP 02</span><h2 id="preview-title">Card preview</h2><p>Review the generated card before downloading.</p></div></div>
                        <div class="idgen-preview-stage"><img src="<?= $escape($previewSource) ?>" alt="<?= $hasGeneratedCard ? 'Generated employee ID card' : 'Sample employee ID card template' ?>" width="320"></div>
                        <div class="idgen-preview-actions"><?php if ($hasGeneratedCard): ?><span class="account-count">Generated card</span><a class="secondary-action primary-action idgen-download" href="<?= $escape($basePath) ?>/public/tools/sp-id-generator/download.php?file1=<?= rawurlencode($cardName) ?>&amp;token=<?= rawurlencode($generatorCsrf) ?>">↓ Download ID card</a><?php else: ?><span class="account-count">Sample template</span><span class="dashboard-subtitle">Generate a card to see your result here.</span><?php endif; ?></div>
                    </section>
                </div>
            </div>
        </main>
    </div>
</body>
</html>