<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\HttpException;
use App\Models\Booking;
use App\Models\Ledger;
use App\Models\Product;
use PDO;

final class BookingController
{
    public function __construct(private PDO $db, private Booking $bookings, private Product $products, private Ledger $ledger)
    {
    }

    public function index(): void
    {
        $status = $_GET['status'] ?? null;
        if ($status !== null && !in_array($status, Booking::STATUSES, true)) {
            throw new HttpException(422, 'Unknown status filter');
        }
        $perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        Http::json([
            'status' => 'success',
            'data' => $this->bookings->list($status, $perPage, ($page - 1) * $perPage),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $this->bookings->count($status)],
        ]);
    }

    public function show(array $params): void
    {
        $booking = $this->bookings->find((int) $params['id']);
        if (!$booking) {
            throw new HttpException(404, 'Booking not found');
        }
        $booking['ledger'] = $this->ledger->forBooking((int) $booking['id']);
        Http::json(['status' => 'success', 'data' => $booking]);
    }

    public function store(array $params, array $context): void
    {
        $data = $this->validate(Http::body());
        $product = $this->products->findByCode($data['product_code']);
        if (!$product || $product['status'] !== 'active') {
            throw new HttpException(422, 'Validation failed', ['product_code' => 'Unknown or inactive product']);
        }

        // Booking and ledger entry are written together or not at all
        $this->db->beginTransaction();
        try {
            $id = $this->bookings->create($data, $product, $context['user']['id']);
            $this->ledger->recordBooking($id, $data, $product);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $booking = $this->bookings->find($id);
        $booking['ledger'] = $this->ledger->forBooking($id);
        Http::json(['status' => 'success', 'message' => 'Booking created', 'data' => $booking], 201);
    }

    public function updateStatus(array $params): void
    {
        $booking = $this->bookings->find((int) $params['id']);
        if (!$booking) {
            throw new HttpException(404, 'Booking not found');
        }
        $status = (string) (Http::body()['status'] ?? '');
        if (!in_array($status, Booking::STATUSES, true)) {
            throw new HttpException(422, 'Status must be one of: ' . implode(', ', Booking::STATUSES));
        }
        if ($booking['status'] === 'cancelled') {
            throw new HttpException(409, 'Cancelled bookings cannot be changed');
        }
        $this->bookings->updateStatus((int) $booking['id'], $status);
        Http::json(['status' => 'success', 'data' => $this->bookings->find((int) $booking['id'])]);
    }

    private function validate(array $in): array
    {
        $required = ['customer_name', 'phone', 'email', 'departure_date', 'pax', 'passport_number', 'passport_expiry', 'product_code'];
        $errors = [];
        foreach ($required as $field) {
            if (!isset($in[$field]) || trim((string) $in[$field]) === '') {
                $errors[$field] = 'Required';
            }
        }
        if ($errors) {
            throw new HttpException(422, 'Validation failed', $errors);
        }
        $data = array_map(fn($v) => is_string($v) ? trim($v) : $v, array_intersect_key($in, array_flip($required)));

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email';
        }
        if (!preg_match('/^\+?[0-9 ()-]{7,20}$/', (string) $data['phone'])) {
            $errors['phone'] = 'Invalid phone number';
        }
        if (filter_var($data['pax'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]) === false) {
            $errors['pax'] = 'Must be a whole number between 1 and 50';
        }
        if (!preg_match('/^[A-Z0-9]{6,12}$/i', (string) $data['passport_number'])) {
            $errors['passport_number'] = '6-12 letters or digits';
        }
        $departure = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $data['departure_date']);
        $expiry = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $data['passport_expiry']);
        if (!$departure || $departure->format('Y-m-d') !== $data['departure_date']) {
            $errors['departure_date'] = 'Use YYYY-MM-DD';
        } elseif ($departure < new \DateTimeImmutable('today')) {
            $errors['departure_date'] = 'Must not be in the past';
        }
        if (!$expiry || $expiry->format('Y-m-d') !== $data['passport_expiry']) {
            $errors['passport_expiry'] = 'Use YYYY-MM-DD';
        } elseif ($departure && $expiry < $departure->modify('+6 months')) {
            // Most countries require a passport valid for 6 months after arrival
            $errors['passport_expiry'] = 'Passport must be valid at least 6 months after departure';
        }
        if ($errors) {
            throw new HttpException(422, 'Validation failed', $errors);
        }
        $data['pax'] = (int) $data['pax'];
        $data['passport_number'] = strtoupper((string) $data['passport_number']);
        return $data;
    }
}
