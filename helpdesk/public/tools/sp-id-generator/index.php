<?php
require_once __DIR__ . '/../../../bootstrap/admin-tool-auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_id_generator_csrf((string) ($_POST['_id_generator_csrf'] ?? ''));
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo "<script>alert('Error: No image uploaded or upload failed.');</script>";
    } else {
        $imageTmp = $_FILES['image']['tmp_name'];
        $imageInfo = getimagesize($imageTmp);
        if ($imageInfo === false) {
            echo "<script>alert('Error: Uploaded file is not a valid image.');</script>";
        } elseif ($imageInfo[2] !== IMAGETYPE_JPEG) {
            echo "<script>alert('Error: Image must be a JPEG file.');</script>";
        } else {
            define('IDGEN_GENERATE_REQUEST', true);
            include_once __DIR__ . '/idgen.php';
        }
    }
}

$hasGeneratedCard = isset($img2) && is_file($img2);
$cardName = $hasGeneratedCard ? basename($img2) : '';
$previewSource = $hasGeneratedCard ? 'preview.php?file=' . rawurlencode($cardName) : 'id.png';
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10254a">
    <title>SP ID Generator · Dabaw Helpdesk</title>
    <link rel="icon" type="image/x-icon" href="/helpdesk/public/assets/images/SP.ico">
    <link rel="stylesheet" href="/helpdesk/public/assets/css/app.css">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body class="dashboard-body">
    <main class="dashboard-content idgen-content">
        <div class="dashboard-title-row">
            <div><p class="eyebrow">EMPLOYEE SERVICES</p><h1>ID card generator</h1><p class="dashboard-subtitle">Create and review an employee identification card.</p></div>
            <span class="admin-pill"><span></span> Admin access</span>
        </div>
        <div class="admin-grid idgen-grid">
            <section class="admin-card" aria-labelledby="form-title">
                <div class="admin-card-heading">
                    <div><span class="step-label">STEP 01</span><h2 id="form-title">Employee details</h2><p>Enter the information exactly as it should appear on the card.</p></div>
                    <span class="section-mark" aria-hidden="true">✎</span>
                </div>
                <form class="account-form idgen-form" method="post" enctype="multipart/form-data" id="uploadForm">
                    <label class="idgen-photo-field" for="image">ID picture
                        <input id="image" type="file" name="image" accept="image/jpeg,.jpg,.jpeg" required>
                        <small>JPEG image with a white background.</small>
                    </label>
                    <input type="hidden" name="_id_generator_csrf" value="<?= $escape((string) ($_SESSION['_id_generator_csrf'] ?? '')) ?>">
                    <label for="nickname">Nickname
                        <input id="nickname" type="text" placeholder="e.g. Alex" name="nickname" value="<?= $escape((string) ($_POST['nickname'] ?? '')) ?>" required>
                    </label>
                    <label for="fullname">Full name
                        <input id="fullname" type="text" placeholder="e.g. Alex Santos" name="fullname" value="<?= $escape((string) ($_POST['fullname'] ?? '')) ?>" required>
                    </label>
                    <label for="position">Position
                        <input id="position" type="text" placeholder="e.g. Administrative Officer" name="position" value="<?= $escape((string) ($_POST['position'] ?? '')) ?>" required>
                    </label>
                    <label for="empnumber">Employee number
                        <input id="empnumber" type="number" placeholder="Enter employee number" name="empnumber" value="<?= $escape((string) ($_POST['empnumber'] ?? '')) ?>" required>
                    </label>
                    <label for="fontsize">Name font size
                        <input id="fontsize" type="number" min="10" max="120" name="fontsize" value="<?= $escape((string) ($_POST['fontsize'] ?? '70')) ?>">
                        <small>Reduce this value if the name is long.</small>
                    </label>
                    <label for="fontsize2">Position font size
                        <input id="fontsize2" type="number" min="10" max="80" name="fontsize2" value="<?= $escape((string) ($_POST['fontsize2'] ?? '35')) ?>">
                        <small>Adjust this value to fit the position.</small>
                    </label>
                    <button class="sign-in-button idgen-submit" type="submit" name="process">Generate ID <span aria-hidden="true">→</span></button>
                </form>
            </section>
            <section class="admin-card" aria-labelledby="preview-title">
                <div class="admin-card-heading">
                    <div><span class="step-label">STEP 02</span><h2 id="preview-title">Card preview</h2><p>Review the generated card before downloading.</p></div>
                </div>
                <div class="idgen-preview"><img src="<?= $escape($previewSource) ?>" alt="<?= $hasGeneratedCard ? 'Generated employee ID card' : 'Sample employee ID card template' ?>" width="320"></div>
                <?php if ($hasGeneratedCard): ?>
                    <p><a class="secondary-action primary-action" href="download.php?file1=<?= rawurlencode($cardName) ?>&amp;token=<?= rawurlencode((string) ($_SESSION['_id_generator_csrf'] ?? '')) ?>">↓ Download ID card</a></p>
                <?php else: ?>
                    <p class="dashboard-subtitle">Your generated card will appear here.</p>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>