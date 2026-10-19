<?php
declare(strict_types=1);

final class AuthController
{
    public function login(array $config): void
    {
        if (!empty($_SESSION['user'])) {
            redirect_to($_SESSION['user']['role'] === 'admin' ? '/admin' : '/dashboard', $config['base_path']);
        }

        $error = null;
        $mode = (string) ($_GET['type'] ?? 'staff');
        if (!in_array($mode, ['staff', 'admin'], true)) {
            $mode = 'staff';
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $action = (string) ($_POST['login_action'] ?? '');
            $mode = $action === 'admin_login' ? 'admin' : 'staff';
            try {
                if ($action === 'staff_login') {
                    $username = strtolower(trim((string) ($_POST['username'] ?? '')));
                    $password = (string) ($_POST['password'] ?? '');
                    if ($username === '' || $password === '') {
                        $error = 'Enter your username and password.';
                    } else {
                        $pdo = DatabaseConnection::connect();
                        $statement = $pdo->prepare("SELECT id, display_name, email, password_hash, role, is_active FROM helpdesk_users WHERE username = :username AND role = 'agent' LIMIT 1");
                        $statement->execute(['username' => $username]);
                        $user = $statement->fetch();
                        if ($user && (int) $user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
                            $this->completeLogin($user, $config);
                        }
                        $error = 'The username or password is incorrect, or this account is inactive.';
                    }
                } elseif ($action === 'admin_login') {
                    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                    $password = (string) ($_POST['password'] ?? '');
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
                        $error = 'Enter a valid administrator email and password.';
                    } else {
                        $pdo = DatabaseConnection::connect();
                        $statement = $pdo->prepare("SELECT id, display_name, email, password_hash, role, is_active FROM helpdesk_users WHERE email = :email AND role = 'admin' LIMIT 1");
                        $statement->execute(['email' => $email]);
                        $user = $statement->fetch();
                        if ($user && (int) $user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
                            $this->completeLogin($user, $config);
                        }
                        $error = 'The administrator email or password is incorrect, or this account is inactive.';
                    }
                } else {
                    $error = 'Choose a sign-in method and try again.';
                }
            } catch (Throwable $exception) {
                error_log('Helpdesk login error: ' . $exception->getMessage());
                $error = 'The sign-in service is unavailable. Check the local database configuration.';
            }
        }

        $appName = $config['name'];
        $basePath = $config['base_path'];
        require __DIR__ . '/../Views/Auth/login.php';
    }

    private function completeLogin(array $user, array $config): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) ($user['name'] ?? $user['display_name'] ?? ''),
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        redirect_to($user['role'] === 'admin' ? '/admin' : '/dashboard', $config['base_path']);
    }

    public function logout(array $config): void
    {
        verify_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => '/helpdesk/public/tools/sp-id-generator/',
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        redirect_to('/login', $config['base_path']);
    }
}
