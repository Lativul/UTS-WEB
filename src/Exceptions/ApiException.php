<?php
declare(strict_types=1);

namespace RuangBaca\Exceptions;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(string $message, public int $statusCode = 400)
    {
        parent::__construct($message);
    }
}
