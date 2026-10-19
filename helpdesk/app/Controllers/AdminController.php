<?php
declare(strict_types=1);

final class AdminController
{
    public function index(array $config): void
    {
        $admin = require_admin_user($config['base_path']);
        $users = [];
        $error = null;

        try {
            $pdo = DatabaseConnection::connect();
            $users = $pdo->query('SELECT id, display_name, username, email, role, is_active, created_at FROM helpdesk_users ORDER BY created_at DESC')->fetchAll();
        } catch (Throwable $exception) {
            error_log('Helpdesk admin error: ' . $exception->getMessage());
            $error = 'The user directory is unavailable. Check the database connection.';
        }

        $basePath = $config['base_path'];
        $notice = take_flash_message();
        require __DIR__ . '/../Views/Admin/dashboard.php';
    }

    public function users(array $config): void
    {
        $admin = require_admin_user($config['base_path']);
        $users = [];
        $error = null;
        try {
            $pdo = DatabaseConnection::connect();
            $users = $pdo->query('SELECT id, display_name, username, email, role, is_active, created_at FROM helpdesk_users ORDER BY created_at DESC')->fetchAll();
        } catch (Throwable $exception) {
            error_log('Helpdesk user directory error: ' . $exception->getMessage());
            $error = 'The user directory is unavailable. Check the database connection.';
        }

        $basePath = $config['base_path'];
        $activePage = 'users';
        $pageTitle = 'Staff accounts';
        $showCreateForm = (string) ($_GET['create'] ?? '') === '1';
        $notice = take_flash_message();
        require __DIR__ . '/../Views/Admin/users.php';
    }

    public function createUserForm(array $config): void
    {
        require_admin_user($config['base_path']);
        redirect_to('/admin/users?create=1#add-account', $config['base_path']);
    }

    public function database(array $config): void
    {
        $admin = require_admin_user($config['base_path']);
        $database = require __DIR__ . '/../../config/database.php';
        $tables = [];
        $serverVersion = null;
        $databaseName = $database['database'];
        $error = null;

        try {
            $pdo = DatabaseConnection::connect();
            $serverVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            $tables = $pdo->query("SELECT TABLE_NAME AS table_name, ENGINE AS engine, TABLE_ROWS AS estimated_rows, DATA_LENGTH + INDEX_LENGTH AS total_bytes FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME")->fetchAll();
        } catch (Throwable $exception) {
            error_log('Helpdesk database overview error: ' . $exception->getMessage());
            $error = 'The MySQL database could not be reached. Check the database configuration.';
        }

        $basePath = $config['base_path'];
        $activePage = 'database';
        $pageTitle = 'MySQL database';
        require __DIR__ . '/../Views/Admin/database.php';
    }

    public function idGenerator(array $config): void
    {
        $admin = require_admin_user($config['base_path']);
        $basePath = $config['base_path'];
        $activePage = 'id-generator';
        $pageTitle = 'SP ID Generator';
        $error = null;
        $img2 = null;
        $generatorDirectory = dirname(__DIR__, 2) . '/public/tools/sp-id-generator';

        if (empty($_SESSION['_id_generator_csrf'])) {
            $_SESSION['_id_generator_csrf'] = bin2hex(random_bytes(32));
        }
        $generatorCsrf = (string) $_SESSION['_id_generator_csrf'];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verify_csrf();
            $submittedToken = (string) ($_POST['_id_generator_csrf'] ?? '');
            if ($submittedToken === '' || !hash_equals($generatorCsrf, $submittedToken)) {
                http_response_code(419);
                $error = 'Your session expired. Reload the page and try again.';
            } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Choose a JPEG photo and try again.';
            } else {
                $imageInfo = getimagesize($_FILES['image']['tmp_name']);
                if ($imageInfo === false || $imageInfo[2] !== IMAGETYPE_JPEG) {
                    $error = 'The ID picture must be a valid JPEG image.';
                } else {
                    define('IDGEN_GENERATE_REQUEST', true);
                    $previousDirectory = getcwd();
                    chdir($generatorDirectory);
                    try {
                        include $generatorDirectory . '/idgen.php';
                        if (isset($img2)) {
                            $img2 = $generatorDirectory . DIRECTORY_SEPARATOR . $img2;
                        }
                    } finally {
                        if ($previousDirectory !== false) {
                            chdir($previousDirectory);
                        }
                    }
                }
            }
        }

        $hasGeneratedCard = is_string($img2) && is_file($img2);
        $cardName = $hasGeneratedCard ? basename($img2) : '';
        $previewSource = $hasGeneratedCard
            ? $basePath . '/public/tools/sp-id-generator/preview.php?file=' . rawurlencode($cardName)
            : $basePath . '/public/tools/sp-id-generator/id.png';
        $notice = take_flash_message();
        require __DIR__ . '/../Views/Admin/id-generator.php';
    }
    public function createUser(array $config): void
    {
        require_admin_user($config['base_path']);
        verify_csrf();

        $name = trim((string) ($_POST['display_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? 'agent');

        if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            flash_message('error', 'Enter a valid name and email address.');
            redirect_to('/admin/users?create=1#add-account', $config['base_path']);
        }
        if (strlen($password) < 12) {
            flash_message('error', 'Use an initial password with at least 12 characters.');
            redirect_to('/admin/users?create=1#add-account', $config['base_path']);
        }
        if (!in_array($role, ['admin', 'agent'], true)) {
            flash_message('error', 'Choose a valid account role.');
            redirect_to('/admin/users?create=1#add-account', $config['base_path']);
        }
        if ($role === 'agent' && !preg_match('/^[a-z0-9][a-z0-9._-]{2,79}$/', $username)) {
            flash_message('error', 'Staff usernames must be 3–80 characters and use letters, numbers, dots, underscores, or hyphens.');
            redirect_to('/admin/users?create=1#add-account', $config['base_path']);
        }
        if ($role === 'admin') {
            $username = '';
        }

        $created = false;
        try {
            $pdo = DatabaseConnection::connect();
            $statement = $pdo->prepare('INSERT INTO helpdesk_users (display_name, username, email, password_hash, role, is_active) VALUES (:name, :username, :email, :password_hash, :role, 1)');
            $statement->execute([
                'name' => $name,
                'username' => $username !== '' ? $username : null,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
            ]);
            $created = true;
            flash_message('success', 'Account created. The staff member can now sign in.');
        } catch (PDOException $exception) {
            error_log('Helpdesk user creation error: ' . $exception->getMessage());
            flash_message('error', (string) $exception->getCode() === '23000' ? 'That email address or username is already in use.' : 'The account could not be created. Check the database connection.');
        } catch (Throwable $exception) {
            error_log('Helpdesk user creation error: ' . $exception->getMessage());
            flash_message('error', 'The account could not be created. Check the database connection.');
        }

        redirect_to($created ? '/admin/users' : '/admin/users?create=1#add-account', $config['base_path']);
    }

    public function staffDashboard(array $config): void
    {
        $user = require_authenticated_user($config['base_path']);
        if (($user['role'] ?? '') === 'admin') {
            redirect_to('/admin', $config['base_path']);
        }
        $basePath = $config['base_path'];
        require __DIR__ . '/../Views/Staff/dashboard.php';
    }
}