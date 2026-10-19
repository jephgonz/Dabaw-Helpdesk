<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('dabaw_helpdesk_tool');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/helpdesk',
    ]);
    session_start();
}

function verify_id_generator_csrf(string $submitted): void
{
    $known = (string) ($_SESSION['_id_generator_csrf'] ?? '');
    if ($known === '' || $submitted === '' || !hash_equals($known, $submitted)) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }
}

if (empty($_SESSION['user']['id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    session_write_close();
    header('Location: /helpdesk/login', true, 303);
    exit;
}

if (empty($_SESSION['_id_generator_csrf'])) {
    $_SESSION['_id_generator_csrf'] = bin2hex(random_bytes(32));
}
session_write_close();
