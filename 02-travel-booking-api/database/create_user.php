<?php
// Create an account from the command line (public registration is disabled):
//   php database/create_user.php <username> <email> <password> [staff|admin]
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || $argc < 4) {
    fwrite(STDERR, "Usage: php database/create_user.php <username> <email> <password> [staff|admin]\n");
    exit(1);
}
$config = require __DIR__ . '/../config/config.php';
$pdo = new PDO($config['db_dsn'], $config['db_user'] ?: null, $config['db_pass'] ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$role = $argv[4] ?? 'staff';
$pdo->prepare('INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)')
    ->execute([$argv[1], $argv[2], password_hash($argv[3], PASSWORD_DEFAULT), $role, date('Y-m-d H:i:s')]);
echo "User '{$argv[1]}' ($role) created.\n";
