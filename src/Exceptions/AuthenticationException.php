<?php

namespace BlessDarah\PhpCampay\Exceptions;

class AuthenticationException extends CampayException
{
    public function __construct(string $message = "Authentication failed", int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}