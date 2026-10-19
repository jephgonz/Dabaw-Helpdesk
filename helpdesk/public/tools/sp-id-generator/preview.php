<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap/admin-tool-auth.php';

$fileName = basename((string) ($_GET['file'] ?? ''));
if ($fileName === '' || strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'png') {
    http_response_code(404);
    exit;
}

$cardsDirectory = realpath(__DIR__ . '/cards');
$filePath = realpath(__DIR__ . '/cards/' . $fileName);
if ($cardsDirectory === false || $filePath === false || !str_starts_with($filePath, $cardsDirectory . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/png');
header('Content-Length: ' . (string) filesize($filePath));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
