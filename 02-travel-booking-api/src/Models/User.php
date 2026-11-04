<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    public function __construct(private PDO $db)
    {
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $username, string $email, string $password, string $role): int
    {
        $this->db->prepare('INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role, date('Y-m-d H:i:s')]);
        return (int) $this->db->lastInsertId();
    }
}
