<?php

declare(strict_types=1);

namespace BlessDarah\PhpCampay;

use BlessDarah\PhpCampay\Exceptions\ApiException;
use BlessDarah\PhpCampay\Exceptions\AuthenticationException;
use BlessDarah\PhpCampay\Exceptions\CampayException;
use BlessDarah\PhpCampay\Exceptions\InvalidWebhookException;
use BlessDarah\PhpCampay\Exceptions\ValidationException;
use DateTimeImmutable;
use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;

class Campay
{
    public const string DEMO_BASE_URL = 'https://demo.campay.net/api/';
    public const string LIVE_BASE_URL = 'https://www.campay.net/api/';

    /**
     * Campay references are UUIDs, but allow any simple identifier so that
     * nothing able to alter the request path (e.g. "../", "?", "#") gets through.
     */
    public const string REFERENCE_PATTERN = '/^[A-Za-z0-9_-]{1,128}$/';

    private const float TIMEOUT = 30.0;
    private const float CONNECT_TIMEOUT = 10.0;

    private string $token = '';

    private readonly string $baseUrl;

    private readonly ClientInterface $client;

    /**
     * Create a new campay instance
     *
     * @param string $username - Campay application username
     * @param string $password - Campay application password
     * @param string $baseUrl - Campay API base URL (must be https). Defaults to the demo environment
     * @param ClientInterface|null $client - Custom Guzzle client (useful for testing)
     * @throws CampayException
     */
    public function __construct(
        private readonly string $username,
        #[\SensitiveParameter]
        private readonly string $password,
        string $baseUrl = self::DEMO_BASE_URL,
        ?ClientInterface $client = null,
    ) {
        if (trim($this->username) === '') {
            throw new CampayException('Campay username is required');
        }

        if ($this->password === '') {
            throw new CampayException('Campay password is required');
        }

        $this->baseUrl = self::normaliseBaseUrl($baseUrl);
        $this->client = $client ?? new Client();
    }

    /**
     * Returns the Campay token, requesting one if none is cached yet
     *
     * @throws AuthenticationException
     * @throws ApiException
     */
    public function getToken(): string
    {
        $this->ensureAuthenticated();
        return $this->token;
    }

    /**
     * Request a fresh token from campay
     *
     * @throws AuthenticationException
     * @throws ApiException
     */
    public function authenticate(): string
    {
        return $this->requestToken();
    }

    /**
     * Launches collection from user via USSD
     *
     * @param array<string, mixed> $data - the collection data
     * @throws ValidationException
     * @throws ApiException
     * @return string - The JSON response body
     */
    public function collect(array $data): string
    {
        $data = $this->validateRequiredFields($data, ['amount', 'currency', 'from', 'description'], 'collection');

        return $this->request('POST', 'collect/', $data, 'Collection request');
    }

    /**
     * Gets the status of a specific transaction
     *
     * @param string $reference - The transaction reference
     * @throws ValidationException
     * @throws ApiException
     * @return string - The JSON response body, whose status is "SUCCESSFUL" | "PENDING" | "FAILED"
     */
    public function getTransactionStatus(string $reference): string
    {
        $reference = trim($reference);

        if ($reference === '') {
            throw new ValidationException('Transaction reference cannot be empty');
        }

        if (preg_match(self::REFERENCE_PATTERN, $reference) !== 1) {
            throw new ValidationException('Transaction reference contains invalid characters');
        }

        return $this->request('GET', 'transaction/' . rawurlencode($reference) . '/', null, 'Transaction status request');
    }

    /**
     * Confirm a webhook notification against the Campay API before acting on it.
     *
     * The webhook signature does not prove the other fields were not altered or replayed,
     * so this fetches the transaction and checks that its reference, status and amount match.
     *
     * @param WebhookEvent $event - A verified webhook from CampayWebhook::parse()
     * @param int|float|string|null $expectedAmount - The amount your order expects, if any
     * @throws InvalidWebhookException - When Campay's record does not match the webhook
     * @throws ValidationException
     * @throws ApiException
     * @return array<mixed> - The decoded transaction from Campay
     */
    public function confirmTransaction(WebhookEvent $event, int|float|string|null $expectedAmount = null): array
    {
        $transaction = $this->decodeJson($this->getTransactionStatus($event->reference), 'Transaction status request');

        $reference = $transaction['reference'] ?? $event->reference;
        $status = $transaction['status'] ?? null;
        $amount = $transaction['amount'] ?? null;

        if (!is_scalar($reference) || (string) $reference !== $event->reference) {
            throw new InvalidWebhookException('Webhook reference does not match the Campay transaction');
        }

        if ($status !== $event->status) {
            throw new InvalidWebhookException('Webhook status does not match the Campay transaction');
        }

        if (!self::amountsMatch($amount, $event->amount)) {
            throw new InvalidWebhookException('Webhook amount does not match the Campay transaction');
        }

        if ($expectedAmount !== null && !self::amountsMatch($amount, $expectedAmount)) {
            throw new InvalidWebhookException('Campay transaction amount does not match the expected amount');
        }

        return $transaction;
    }

    private static function amountsMatch(mixed $actual, int|float|string $expected): bool
    {
        return is_numeric($actual)
            && is_numeric($expected)
            && abs((float) $actual - (float) $expected) < 0.00001;
    }

    /**
     * Trigger a withdrawal request
     *
     * @param array<string, mixed> $data - the client information
     * @throws ValidationException
     * @throws ApiException
     * @return string - The JSON response body
     */
    public function withdraw(array $data): string
    {
        $data = $this->validateRequiredFields($data, ['amount', 'currency', 'to', 'description'], 'withdrawal');

        return $this->request('POST', 'withdraw/', $data, 'Withdrawal request');
    }

    /**
     * Get application balance
     *
     * @throws ApiException
     */
    public function getAppBalance(): string
    {
        return $this->request('GET', 'balance/', null, 'Balance request');
    }

    /**
     * Get transaction history within a date range. Defaults to the past 7 days.
     *
     * @param DateTimeInterface|string|null $start - The start date (YYYY-MM-DD when a string)
     * @param DateTimeInterface|string|null $end - The end date (YYYY-MM-DD when a string)
     * @throws ValidationException
     * @throws ApiException
     */
    public function transactionHistory(DateTimeInterface|string|null $start = null, DateTimeInterface|string|null $end = null): string
    {
        $startDate = $this->toDate($start, '-7 days');
        $endDate = $this->toDate($end, 'now');

        if ($startDate > $endDate) {
            throw new ValidationException('Start date cannot be after end date');
        }

        $params = [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ];

        return $this->request('POST', 'history/', $params, 'Transaction history request');
    }

    /**
     * Generate a payment URL to be used
     *
     * @param array<string, mixed> $params
     * @throws ValidationException
     * @throws ApiException
     */
    public function generatePaymentUrl(array $params): string
    {
        $params = $this->validateRequiredFields(
            $params,
            ['amount', 'currency', 'description', 'first_name', 'last_name', 'email'],
            'payment URL generation',
        );

        if (filter_var($params['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Invalid email address provided');
        }

        foreach (['redirect_url', 'failure_redirect_url'] as $field) {
            if (!empty($params[$field]) && !self::isHttpsUrl((string) $params[$field])) {
                throw new ValidationException("Field '{$field}' must be a valid https URL");
            }
        }

        return $this->request('POST', 'get_payment_link/', $params, 'Payment URL generation');
    }

    /**
     * Ensure the base URL is an https URL without credentials, query or fragment,
     * so that credentials and tokens are never sent in clear text or to a confusable host.
     *
     * @throws CampayException
     */
    private static function normaliseBaseUrl(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);
        $parts = parse_url($baseUrl);

        if (
            $parts === false
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new CampayException('Campay base URL must be a valid https URL without credentials, query or fragment');
        }

        return rtrim($baseUrl, '/') . '/';
    }

    private static function isHttpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    /**
     * Request a token from campay
     *
     * @throws AuthenticationException
     * @throws ApiException
     */
    private function requestToken(): string
    {
        $this->token = '';

        try {
            $response = $this->client->request('POST', $this->baseUrl . 'token/', $this->requestOptions([
                'username' => $this->username,
                'password' => $this->password,
            ]));
        } catch (BadResponseException $e) {
            $status = $e->getResponse()->getStatusCode();

            if ($status === 400 || $status === 401) {
                throw new AuthenticationException('Invalid credentials provided', 401, $e);
            }

            throw new ApiException("Authentication request failed with HTTP status {$status}", $status, $e);
        } catch (GuzzleException $e) {
            throw new ApiException('Authentication request failed: unable to reach Campay', 0, $e);
        }

        $data = $this->decodeJson((string) $response->getBody(), 'Authentication request');
        $token = $data['token'] ?? null;

        if (!is_string($token) || $token === '') {
            throw new AuthenticationException('Failed to retrieve authentication token');
        }

        return $this->token = $token;
    }

    /**
     * Ensure the instance has a valid authentication token.
     *
     * @throws AuthenticationException
     * @throws ApiException
     */
    private function ensureAuthenticated(): void
    {
        if ($this->token === '') {
            $this->requestToken();
        }
    }

    /**
     * Send an authenticated request and map low-level errors into package exceptions.
     *
     * @param array<array-key, scalar|null>|null $formParams
     * @throws AuthenticationException
     * @throws ApiException
     */
    private function request(string $method, string $path, ?array $formParams, string $context, bool $retryUnauthorized = true): string
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->client->request(
                $method,
                $this->baseUrl . $path,
                $this->requestOptions($formParams, 'Token ' . $this->token),
            );
        } catch (BadResponseException $e) {
            $status = $e->getResponse()->getStatusCode();

            if ($retryUnauthorized && $status === 401) {
                $this->requestToken();
                return $this->request($method, $path, $formParams, $context, false);
            }

            throw new ApiException("{$context} failed with HTTP status {$status}", $status, $e);
        } catch (GuzzleException $e) {
            throw new ApiException("{$context} failed: unable to reach Campay", 0, $e);
        }

        $body = (string) $response->getBody();
        $this->decodeJson($body, $context);

        return $body;
    }

    /**
     * Build request options with secure transport defaults that a caller supplied client cannot weaken.
     *
     * @param array<array-key, scalar|null>|null $formParams
     * @return array{headers: array<string, string>, allow_redirects: false, verify: true, http_errors: true, timeout: float, connect_timeout: float, form_params?: array<array-key, scalar|null>}
     */
    private function requestOptions(?array $formParams, ?string $authorization = null): array
    {
        $headers = ['Accept' => 'application/json'];

        if ($authorization !== null) {
            $headers['Authorization'] = $authorization;
        }

        $options = [
            'headers' => $headers,
            'allow_redirects' => false,
            'verify' => true,
            'http_errors' => true,
            'timeout' => self::TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
        ];

        if ($formParams !== null) {
            $options['form_params'] = $formParams;
        }

        return $options;
    }

    /**
     * @return array<mixed>
     * @throws ApiException
     */
    private function decodeJson(string $body, string $context): array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ApiException("{$context} returned an invalid JSON response", 0, $e);
        }

        if (!is_array($data)) {
            throw new ApiException("{$context} returned an unexpected response");
        }

        return $data;
    }

    /**
     * @throws ValidationException
     */
    private function toDate(DateTimeInterface|string|null $value, string $default): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if ($value === null) {
            return new DateTimeImmutable($default);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new ValidationException('Invalid date format provided. Use YYYY-MM-DD');
        }

        return $date;
    }

    /**
     * Validate that required fields are present, the amount is positive
     * and every value can be safely sent as a form field.
     *
     * @param array<string, mixed> $data
     * @param list<string> $required
     * @return array<string, scalar|null>
     * @throws ValidationException
     */
    private function validateRequiredFields(array $data, array $required, string $operation): array
    {
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new ValidationException("Field '{$field}' is required for {$operation}");
            }
        }

        $validated = [];

        foreach ($data as $field => $value) {
            if ($value !== null && !is_scalar($value)) {
                throw new ValidationException("Field '{$field}' must be a scalar value");
            }

            if (is_float($value) && !is_finite($value)) {
                throw new ValidationException("Field '{$field}' must be a finite number");
            }

            $validated[$field] = $value;
        }

        if (!is_numeric($validated['amount']) || $validated['amount'] <= 0) {
            throw new ValidationException('Amount must be a positive number');
        }

        return $validated;
    }
}
