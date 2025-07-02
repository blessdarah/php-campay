<?php

namespace BlessDarah\PhpCampay\Exceptions;

use Exception;

class CampayException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}