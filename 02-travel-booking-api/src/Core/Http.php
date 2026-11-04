<?php
declare(strict_types=1);

namespace App\Core;

final class Http
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public static function body(): array
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($data)) {
            throw new HttpException(400, 'Request body must be a JSON object');
        }
        return $data;
    }

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        return preg_match('/^Bearer\s+(\S+)$/', $header, $m) ? $m[1] : null;
    }
}
