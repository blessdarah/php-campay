<?php

namespace BlessDarah\PhpCampay\Exceptions;

class ValidationException extends CampayException
{
    public function __construct(string $message = "Validation failed", int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}