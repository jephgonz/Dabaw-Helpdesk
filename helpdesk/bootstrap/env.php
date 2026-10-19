<?php
declare(strict_types=1);

$envFile = dirname(__DIR__) . '/.env';
if (!is_file($envFile) || !is_readable($envFile)) {
    return;
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }

    $separator = strpos($line, '=');
    if ($separator === false) {
        continue;
    }

    $name = trim(substr($line, 0, $separator));
    $value = trim(substr($line, $separator + 1));
    if ($name === '' || getenv($name) !== false) {
        continue;
    }

    if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
        $value = substr($value, 1, -1);
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}
