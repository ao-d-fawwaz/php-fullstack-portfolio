<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Ledger entries store a snapshot of the product at booking time,
 * so later price changes never alter past records.
 */
final class Ledger
{
    public function __construct(private PDO $db)
    {
    }

    public function recordBooking(int $bookingId, array $booking, array $product): void
    {
        $total = (int) $product['price'] * (int) $booking['pax'];
        $this->db->prepare(
            'INSERT INTO ledger_entries (booking_id, customer_name, product_code, product_title, unit_price, pax,
                                         total_amount, duration_days, vendor, airline, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $bookingId, $booking['customer_name'], $product['code'], $product['title'], $product['price'], $booking['pax'],
            $total, $product['duration_days'], $product['vendor'], $product['airline'], date('Y-m-d H:i:s'),
        ]);
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM ledger_entries ORDER BY id DESC')->fetchAll();
    }

    public function forBooking(int $bookingId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM ledger_entries WHERE booking_id = ?');
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll();
    }

    public function summaryByProduct(): array
    {
        return $this->db->query(
            "SELECT l.product_code, l.product_title, COUNT(*) AS bookings, SUM(l.pax) AS travellers, SUM(l.total_amount) AS revenue
             FROM ledger_entries l JOIN bookings b ON b.id = l.booking_id
             WHERE b.status <> 'cancelled'
             GROUP BY l.product_code, l.product_title ORDER BY revenue DESC"
        )->fetchAll();
    }
}
