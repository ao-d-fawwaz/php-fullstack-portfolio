<?php
// Create a login user from the command line:
//   php database/create_user.php <username> <password>
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || $argc !== 3) {
    fwrite(STDERR, "Usage: php database/create_user.php <username> <password>\n");
    exit(1);
}
$config = require __DIR__ . '/../config/config.php';
$pdo = new PDO($config['db_dsn'], $config['db_user'], $config['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
$stmt->execute([$argv[1], password_hash($argv[2], PASSWORD_DEFAULT)]);
echo "User '{$argv[1]}' created.\n";
