<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    /**
     * @param  array<string, list<string>>  $fieldErrors
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $fieldErrors = [],
    ) {
        parent::__construct($message);
    }
}
