<?php

declare(strict_types=1);

namespace BlessDarah\PhpCampay;

use BlessDarah\PhpCampay\Exceptions\CampayException;
use BlessDarah\PhpCampay\Exceptions\InvalidWebhookException;
use JsonException;

/**
 * Verifies and parses Campay webhook (callback) notifications.
 *
 * Campay signs each notification with an HS256 JWT in the "signature" field,
 * using the application's webhook key from the Campay dashboard.
 */
class CampayWebhook
{
    /**
     * Allowed clock difference in seconds when checking exp, nbf and iat claims
     */
    private const int LEEWAY = 60;

    private const array STATUSES = [WebhookEvent::STATUS_SUCCESSFUL, WebhookEvent::STATUS_FAILED];

    /**
     * @param string $webhookKey - The application's webhook key from the Campay dashboard
     * @throws CampayException
     */
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $webhookKey,
    ) {
        if ($this->webhookKey === '') {
            throw new CampayException('Campay webhook key is required');
        }
    }

    /**
     * Verify and parse a webhook from its fields, e.g. $_GET for GET callbacks
     * or the decoded JSON body for POST callbacks.
     *
     * @param array<mixed> $payload
     * @throws InvalidWebhookException
     */
    public function parse(array $payload): WebhookEvent
    {
        $data = [];

        foreach ($payload as $field => $value) {
            if (!is_string($field)) {
                throw new InvalidWebhookException('Webhook payload must be an object');
            }

            if ($value !== null && !is_scalar($value)) {
                throw new InvalidWebhookException("Webhook field '{$field}' must be a scalar value");
            }

            $data[$field] = $value === null ? null : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        $signature = $data['signature'] ?? null;

        if ($signature === null || $signature === '') {
            throw new InvalidWebhookException('Webhook signature is missing');
        }

        unset($data['signature']);

        $claims = $this->verifySignature($signature);

        return $this->buildEvent($data, $claims);
    }

    /**
     * Verify and parse a webhook from a raw JSON request body (POST callbacks).
     *
     * @throws InvalidWebhookException
     */
    public function parseJson(string $body): WebhookEvent
    {
        try {
            $payload = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidWebhookException('Webhook body is not valid JSON', 400, $e);
        }

        if (!is_array($payload)) {
            throw new InvalidWebhookException('Webhook payload must be an object');
        }

        return $this->parse($payload);
    }

    /**
     * Verify an HS256 JWT signed with the webhook key and return its claims.
     *
     * @return array<string, mixed>
     * @throws InvalidWebhookException
     */
    private function verifySignature(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidWebhookException('Webhook signature is not a valid JWT');
        }

        [$encodedHeader, $encodedClaims, $encodedSignature] = $parts;

        $header = $this->decodeSegment($encodedHeader);

        // Only accept HS256 so tokens with "none" or another algorithm are never trusted
        if (($header['alg'] ?? null) !== 'HS256') {
            throw new InvalidWebhookException('Webhook signature uses an unsupported algorithm');
        }

        if (isset($header['crit'])) {
            throw new InvalidWebhookException('Webhook signature uses unsupported critical headers');
        }

        $expected = hash_hmac('sha256', $encodedHeader . '.' . $encodedClaims, $this->webhookKey, true);

        if (!hash_equals($expected, $this->base64UrlDecode($encodedSignature))) {
            throw new InvalidWebhookException('Webhook signature is invalid');
        }

        $claims = $this->decodeSegment($encodedClaims);
        $now = time();

        if (array_key_exists('exp', $claims) && (!is_numeric($claims['exp']) || $now - self::LEEWAY >= (int) $claims['exp'])) {
            throw new InvalidWebhookException('Webhook signature has expired');
        }

        foreach (['nbf', 'iat'] as $claim) {
            if (array_key_exists($claim, $claims) && (!is_numeric($claims[$claim]) || $now + self::LEEWAY < (int) $claims[$claim])) {
                throw new InvalidWebhookException('Webhook signature is not valid yet');
            }
        }

        return $claims;
    }

    /**
     * @return array<string, mixed>
     * @throws InvalidWebhookException
     */
    private function decodeSegment(string $segment): array
    {
        try {
            $decoded = json_decode($this->base64UrlDecode($segment), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidWebhookException('Webhook signature is not a valid JWT', 400, $e);
        }

        if (!is_array($decoded) || array_is_list($decoded) && $decoded !== []) {
            throw new InvalidWebhookException('Webhook signature is not a valid JWT');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @throws InvalidWebhookException
     */
    private function base64UrlDecode(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_-]*$/', $value) !== 1) {
            throw new InvalidWebhookException('Webhook signature is not a valid JWT');
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidWebhookException('Webhook signature is not a valid JWT');
        }

        return $decoded;
    }

    /**
     * @param array<string, string|null> $data
     * @param array<string, mixed> $claims
     * @throws InvalidWebhookException
     */
    private function buildEvent(array $data, array $claims): WebhookEvent
    {
        $status = $this->required($data, 'status');
        $reference = $this->required($data, 'reference');
        $amount = $this->required($data, 'amount');
        $currency = $this->required($data, 'currency');

        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidWebhookException('Webhook status is invalid');
        }

        if (preg_match(Campay::REFERENCE_PATTERN, $reference) !== 1) {
            throw new InvalidWebhookException('Webhook reference is invalid');
        }

        if (!is_numeric($amount)) {
            throw new InvalidWebhookException('Webhook amount is invalid');
        }

        // Campay does not document the token's claims; when it carries a reference, it must match
        if (isset($claims['reference']) && (!is_scalar($claims['reference']) || (string) $claims['reference'] !== $reference)) {
            throw new InvalidWebhookException('Webhook signature does not match the transaction reference');
        }

        return new WebhookEvent(
            status: $status,
            reference: $reference,
            amount: $amount,
            currency: $currency,
            operator: $this->optional($data, 'operator'),
            code: $this->optional($data, 'code'),
            operatorReference: $this->optional($data, 'operator_reference'),
            endpoint: $this->optional($data, 'endpoint'),
            externalReference: $this->optional($data, 'external_reference'),
            externalUser: $this->optional($data, 'external_user'),
            phoneNumber: $this->optional($data, 'phone_number'),
            description: $this->optional($data, 'description'),
            reason: $this->optional($data, 'reason'),
            appAmount: $this->optional($data, 'app_amount'),
            data: $data,
        );
    }

    /**
     * @param array<string, string|null> $data
     * @throws InvalidWebhookException
     */
    private function required(array $data, string $field): string
    {
        $value = $this->optional($data, $field);

        if ($value === null) {
            throw new InvalidWebhookException("Webhook field '{$field}' is missing");
        }

        return $value;
    }

    /**
     * @param array<string, string|null> $data
     */
    private function optional(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;

        return $value === null || $value === '' ? null : $value;
    }
}
