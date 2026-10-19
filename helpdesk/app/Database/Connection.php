<?php
declare(strict_types=1);

final class DatabaseConnection
{
    public static function connect(): PDO
    {
        $config = require __DIR__ . '/../../config/database.php';
        if ($config['driver'] !== 'mysql') {
            throw new RuntimeException('Only MySQL connections are configured.');
        }
        if ($config['host'] === '' || $config['database'] === '' || $config['username'] === '' || $config['password'] === '') {
            throw new RuntimeException('Set DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD in the local .env file.');
        }
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('Enable the pdo_mysql extension in Laragon PHP.');
        }
        if (!is_file($config['ssl_ca'])) {
            throw new RuntimeException('The MySQL TLS CA certificate was not found at the configured path.');
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_CA')] = $config['ssl_ca'];
        } else {
            throw new RuntimeException('The PDO MySQL TLS options are unavailable in this PHP installation.');
        }
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = true;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        return new PDO($dsn, $config['username'], $config['password'], $options);
    }
}
