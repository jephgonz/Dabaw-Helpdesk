<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap/env.php';
$config = require __DIR__ . '/../config/app.php';
require __DIR__ . '/../app/Support/helpers.php';
require __DIR__ . '/../app/Database/Connection.php';
require __DIR__ . '/../app/Controllers/AuthController.php';
require __DIR__ . '/../app/Controllers/AdminController.php';
require __DIR__ . '/../app/Controllers/DatabaseManagerController.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('dabaw_helpdesk');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => $config['base_path'] . '/',
    ]);
    session_start();
}

$toolCookieOptions = [
    'expires' => time() + 60 * 60 * 24 * 7,
    'path' => '/helpdesk/public/tools/sp-id-generator/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
];
if (($_SESSION['user']['role'] ?? '') === 'admin') {
    setcookie(session_name(), session_id(), $toolCookieOptions);
} else {
    $toolCookieOptions['expires'] = time() - 3600;
    setcookie(session_name(), '', $toolCookieOptions);
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = $config['base_path'];
if ($basePath !== '' && strpos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath));
}
$requestPath = '/' . trim($requestPath, '/');
if ($requestPath === '//') {
    $requestPath = '/';
}

$routes = require __DIR__ . '/../routes/web.php';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$matched = false;

foreach ($routes as [$routeMethod, $routePath, $handler]) {
    if ($method !== $routeMethod || $requestPath !== $routePath) {
        continue;
    }

    [$controllerName, $action] = explode('@', $handler, 2);
    $controller = match ($controllerName) {
        'AuthController' => new AuthController(),
        'AdminController' => new AdminController(),
        'DatabaseManagerController' => new DatabaseManagerController(),
        default => null,
    };
    if ($controller !== null && method_exists($controller, $action)) {
        $controller->$action($config);
        $matched = true;
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Page not found';
}
