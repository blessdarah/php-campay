<?php

namespace BlessDarah\PhpCampay\Exceptions;

class ApiException extends CampayException
{
    public function __construct(string $message = 'API request failed', int $code = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

