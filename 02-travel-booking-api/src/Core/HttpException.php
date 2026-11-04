<?php
declare(strict_types=1);

namespace App\Core;

/** Thrown anywhere to end the request with a JSON error. */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
