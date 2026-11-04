<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Product
{
    public function __construct(private PDO $db)
    {
    }

    public function allActive(): array
    {
        return $this->db->query("SELECT * FROM products WHERE status = 'active' ORDER BY code")->fetchAll();
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE code = ?');
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }
}
