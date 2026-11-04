<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use PDO;

/** Fixed-window rate limit per client IP and route, stored in the database. */
final class RateLimitMiddleware
{
    public function __construct(private PDO $db, private int $max, private int $window)
    {
    }

    public function handle(): callable
    {
        return function (array $context): array {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $route = $context['route'];
            $now = time();
            $this->db->prepare('DELETE FROM rate_limits WHERE request_time < ?')->execute([$now - $this->window]);
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM rate_limits WHERE ip_address = ? AND route = ?');
            $stmt->execute([$ip, $route]);
            $count = (int) $stmt->fetchColumn();
            header('X-RateLimit-Limit: ' . $this->max);
            header('X-RateLimit-Remaining: ' . max(0, $this->max - $count - 1));
            if ($count >= $this->max) {
                header('Retry-After: ' . $this->window);
                throw new HttpException(429, 'Too many requests, try again later');
            }
            $this->db->prepare('INSERT INTO rate_limits (ip_address, route, request_time) VALUES (?, ?, ?)')
                ->execute([$ip, $route, $now]);
            return $context;
        };
    }
}
