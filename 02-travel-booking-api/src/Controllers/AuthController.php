<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\HttpException;
use App\Core\Jwt;
use App\Models\User;

final class AuthController
{
    public function __construct(private User $users, private array $config)
    {
    }

    public function login(): void
    {
        $data = Http::body();
        $user = $this->users->findByUsername(trim((string) ($data['username'] ?? '')));
        // Same message for unknown user and wrong password, so usernames cannot be probed
        if (!$user || !password_verify((string) ($data['password'] ?? ''), $user['password_hash'])) {
            throw new HttpException(401, 'Invalid username or password');
        }
        $token = Jwt::encode([
            'sub' => (int) $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + $this->config['jwt_ttl'],
        ], $this->config['jwt_secret']);
        Http::json([
            'status' => 'success',
            'token' => $token,
            'expires_in' => $this->config['jwt_ttl'],
            'user' => ['id' => (int) $user['id'], 'username' => $user['username'], 'role' => $user['role']],
        ]);
    }

    /** Only admins can create accounts (public self-registration is disabled). */
    public function register(): void
    {
        $data = Http::body();
        $errors = [];
        $username = trim((string) ($data['username'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = (string) ($data['role'] ?? 'staff');
        if (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $username)) {
            $errors['username'] = '3-30 letters, numbers, dot or underscore';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'At least 8 characters';
        }
        if (!in_array($role, ['staff', 'admin'], true)) {
            $errors['role'] = 'Must be staff or admin';
        }
        if ($errors) {
            throw new HttpException(422, 'Validation failed', $errors);
        }
        if ($this->users->findByUsername($username)) {
            throw new HttpException(409, 'Username already taken');
        }
        $id = $this->users->create($username, $email, $password, $role);
        Http::json(['status' => 'success', 'user' => ['id' => $id, 'username' => $username, 'role' => $role]], 201);
    }
}
