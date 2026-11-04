<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Minimal HS256 JSON Web Token implementation (RFC 7519):
 * signs header.payload with HMAC-SHA256 and checks signature and expiry on decode.
 */
final class Jwt
{
    public static function encode(array $payload, string $secret): string
    {
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::b64(json_encode($payload));
        $signature = self::b64(hash_hmac('sha256', "$header.$body", $secret, true));
        return "$header.$body.$signature";
    }

    public static function decode(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed token');
        }
        [$header, $body, $signature] = $parts;
        $head = json_decode(self::unb64($header), true);
        if (($head['alg'] ?? '') !== 'HS256') {
            throw new RuntimeException('Unsupported algorithm');
        }
        $expected = self::b64(hash_hmac('sha256', "$header.$body", $secret, true));
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid signature');
        }
        $payload = json_decode(self::unb64($body), true);
        if (!is_array($payload) || !isset($payload['exp']) || $payload['exp'] < time()) {
            throw new RuntimeException('Token expired');
        }
        return $payload;
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function unb64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
