<?php

namespace BlessDarah\PhpCampay\Exceptions;

class InvalidWebhookException extends CampayException
{
    public function __construct(string $message = 'Invalid webhook', int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
