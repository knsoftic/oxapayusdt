<?php

namespace App\Services\OxaPay;

use RuntimeException;

class OxaPayException extends RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message, $code);
    }
}
