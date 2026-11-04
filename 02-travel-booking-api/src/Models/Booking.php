<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Booking
{
    public const STATUSES = ['pending', 'confirmed', 'paid', 'cancelled'];

    public function __construct(private PDO $db)
    {
    }

    public function create(array $b, array $product, int $createdBy): int
    {
        $this->db->prepare(
            'INSERT INTO bookings (customer_name, phone, email, departure_date, pax, passport_number, passport_expiry,
                                   product_code, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $b['customer_name'], $b['phone'], $b['email'], $b['departure_date'], $b['pax'],
            $b['passport_number'], $b['passport_expiry'], $product['code'], 'pending', $createdBy, date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM bookings WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function list(?string $status, int $limit, int $offset): array
    {
        $sql = 'SELECT * FROM bookings' . ($status ? ' WHERE status = ?' : '') . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($status ? [$status] : []);
        return $stmt->fetchAll();
    }

    public function count(?string $status): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM bookings' . ($status ? ' WHERE status = ?' : ''));
        $stmt->execute($status ? [$status] : []);
        return (int) $stmt->fetchColumn();
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->prepare('UPDATE bookings SET status = ?, updated_at = ? WHERE id = ?')
            ->execute([$status, date('Y-m-d H:i:s'), $id]);
    }
}
