<?php
declare(strict_types=1);

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $submitted = (string) ($_POST['_csrf'] ?? '');
    $known = (string) ($_SESSION['_csrf'] ?? '');
    if ($known === '' || $submitted === '' || !hash_equals($known, $submitted)) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }
}

function redirect_to(string $path, string $basePath): void
{
    header('Location: ' . $basePath . $path, true, 303);
    exit;
}

function require_authenticated_user(string $basePath): array
{
    if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
        redirect_to('/login', $basePath);
    }
    return $_SESSION['user'];
}

function require_admin_user(string $basePath): array
{
    $user = require_authenticated_user($basePath);
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Administrator access required.');
    }
    return $user;
}

function flash_message(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function take_flash_message(): ?array
{
    $message = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($message) ? $message : null;
}
