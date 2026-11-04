<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http;
use App\Core\HttpException;
use App\Core\Jwt;

final class AuthMiddleware
{
    public function __construct(private string $secret)
    {
    }

    /** Requires a valid Bearer token; optionally a specific role. Adds the user to the context. */
    public function require(?string $role = null): callable
    {
        return function (array $context) use ($role): array {
            $token = Http::bearerToken();
            if ($token === null) {
                throw new HttpException(401, 'Missing bearer token');
            }
            try {
                $payload = Jwt::decode($token, $this->secret);
            } catch (\RuntimeException $e) {
                throw new HttpException(401, 'Invalid or expired token');
            }
            if ($role !== null && ($payload['role'] ?? '') !== $role) {
                throw new HttpException(403, 'Insufficient permissions');
            }
            $context['user'] = ['id' => (int) $payload['sub'], 'username' => $payload['username'], 'role' => $payload['role']];
            return $context;
        };
    }
}
