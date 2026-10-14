<?php

declare(strict_types=1);

namespace BlessDarah\PhpCampay;

/**
 * A Campay webhook notification whose signature has been verified.
 *
 * A valid signature proves the request came from Campay, but Campay does not
 * document that the token covers the other fields. Confirm the transaction with
 * Campay::confirmTransaction() before fulfilling an order.
 */
final readonly class WebhookEvent
{
    public const string STATUS_SUCCESSFUL = 'SUCCESSFUL';
    public const string STATUS_FAILED = 'FAILED';

    /**
     * @param array<string, string|null> $data - Every field Campay sent, except the signature
     */
    public function __construct(
        public string $status,
        public string $reference,
        public string $amount,
        public string $currency,
        public ?string $operator,
        public ?string $code,
        public ?string $operatorReference,
        public ?string $endpoint,
        public ?string $externalReference,
        public ?string $externalUser,
        public ?string $phoneNumber,
        public ?string $description,
        public ?string $reason,
        public ?string $appAmount,
        public array $data,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESSFUL;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Whether the transaction was a deposit (collect) rather than a withdrawal
     */
    public function isCollection(): bool
    {
        return $this->endpoint === 'collect';
    }

    public function isWithdrawal(): bool
    {
        return $this->endpoint === 'withdraw';
    }
}
