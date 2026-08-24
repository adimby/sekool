<?php

namespace App\Domain\Platform\Exceptions;

use RuntimeException;

class DomainException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message, $status);
    }
}
