<?php

namespace App\Exceptions;

use Exception;

class BusinessRuleException extends Exception
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->httpStatus;
    }
}
