<?php

namespace BlessDarah\PhpCampay;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\StreamInterface;
use BlessDarah\PhpCampay\Exceptions\CampayException;
use BlessDarah\PhpCampay\Exceptions\AuthenticationException;
use BlessDarah\PhpCampay\Exceptions\ValidationException;
use BlessDarah\PhpCampay\Exceptions\ApiException;

class Campay
{
    private string $token;

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    private Client $client;

    /**
     * Create a new campay instance
     *
     * @param string $base_url - Set the base uri or pull from the env by default
     * @return void
     */
    public function __construct(?string $base_url = null)
    {
        $this->validateEnvironmentVariables();
        
        $this->client = new Client([
            'base_uri' => $base_url ?? $_ENV['CAMPAY_BASE_URI'] ?? 'https://demo.campay.net/api/',
            'timeout' => 30,
        ]);

        $this->token = $this->request_token();
    }

    /**
     * Set headers required by Campay
     *
     * @param array<string, string> $headers - The set of headers
     * @returns void
     */
    private function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * Get the set headers
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        return $this->headers;
    }

    /*
     * Returns the Campay Token
     * @return string
     * */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Set the token to be use for every request
     *
     * @param string $token
     * @returns void
     */
    protected function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Request a token from campay
     *
     * @throws AuthenticationException
     * @throws ApiException
     * @return string
     */
    private function request_token(): string
    {
        try {
            $config = [
                'username' => $_ENV['CAMPAY_USERNAME'],
                'password' => $_ENV['CAMPAY_PASSWORD'],
            ];

            $options = [
                'form_params' => $config,
            ];

            $response = $this->client->post('token/', $options);
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['token'])) {
                throw new AuthenticationException('Failed to retrieve authentication token');
            }

            $this->setHeaders([
                'Authorization' => 'Token ' . $data['token'],
            ]);

            return $data['token'];
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 401) {
                throw new AuthenticationException('Invalid credentials provided', 401, $e);
            }
            throw new ApiException('Authentication request failed: ' . $e->getMessage(), $e->getCode(), $e);
        } catch (ServerException | GuzzleException $e) {
            throw new ApiException('Authentication request failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Launches collection from user via USSD
     *
     * @param array<string, mixed> $data - the collection data
     * @throws ValidationException
     * @throws ApiException
     * @return string - The request body
     */
    public function collect(array $data): string
    {
        $this->validateCollectionData($data);
        
        try {
            $uri = 'collect/';
            $response = $this->client->post($uri, [
                'form_params' => $data, 
                'headers' => $this->getHeaders()
            ]);

            return $response->getBody()->getContents();
        } catch (ClientException | ServerException | GuzzleException $e) {
            throw new ApiException('Collection request failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Gets the status of a specific transaction
     *
     * @param string $reference - The transaction reference
     * @throws ValidationException
     * @throws ApiException
     * @return string "SUCCESSFUL" | "PENDING" | "CANCELED" - the transaction status
     */
    public function getTransactionStatus(string $reference): string
    {
        if (empty(trim($reference))) {
            throw new ValidationException('Transaction reference cannot be empty');
        }
        
        try {
            $uri = 'transaction/' . $reference . '/';
            $response = $this->client->get($uri, [
                'headers' => $this->getHeaders(),
            ]);

            return $response->getBody()->getContents();
        } catch (ClientException | ServerException | GuzzleException $e) {
            throw new ApiException('Transaction status request failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Trigger a withdrawal request
     *
     * @param array<string, mixed> $data - the client information
     * @throws ValidationException
     * @throws ApiException
     * @return string - the withdrawal data
     */
    public function withdraw(array $data): string
    {
        $this->validateWithdrawData($data);
        
        try {
            $uri = 'withdraw/';
            $response = $this->client->post($uri, [
                'form_params' => $data, 
                'headers' => $this->getHeaders()
            ]);

            return $response->getBody()->getContents();
        } catch (ClientException | ServerException | GuzzleException $e) {
            throw new ApiException('Withdrawal request failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get application balance
     * @throws ApiException
     */
    public function getAppBalance(): string
    {
        try {
            $uri = 'balance/';
            $response = $this->client->get($uri, ['headers' => $this->getHeaders()]);

            return $response->getBody()->getContents();
        } catch (ClientException | ServerException | GuzzleException $e) {
            throw new ApiException('Balance request failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get transaction history within a date range
     * @param string|null $start - The start date of the transaction
     * @param string|null $end - The end date of the transaction
     * @throws ApiException
     */
    public function transactionHistory(?string $start = null, ?string $end = null): string
    {
        try {
            $uri = 'history/';
            $date = new Carbon();
            
            if (!isset($start)) {
                $start = $date->subWeek()->format('Y-m-d');
            }

            if (!isset($end)) {
                $end = Carbon::now()->format('Y-m-d');
            }

            $params = [
                'start_date' => date_format(new \DateTime($start), 'Y-m-d'),
                'end_date' => date_format(new \DateTime($end), 'Y-m-d'),
            ];

            $response = $this->client->post($uri, [
                'form_params' => $params, 
                'headers' => $this->getHeaders()
            ]);

            return $response->getBody()->getContents();
        } catch (ClientException | ServerException | GuzzleException $e) {
            throw new ApiException('Transaction history request failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Generate a payment URL to be used
     * @param array<string, mixed> $params
     * @throws ValidationException
     * @throws ApiException
     */
    public function generatePaymentUrl(array $params): string
    {
        $this->validatePaymentUrlData($params);
        
        try {
            $uri = 'get_payment_link/';
            $response = $this->client->post($uri, [
                'form_params' => $params,
                'headers' => $this->getHeaders(),
            ]);

            return $response->getBody()->getContents();
        } catch (ClientException | ServerException | GuzzleException $e) {
            throw new ApiException('Payment URL generation failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Validate environment variables are set
     * @throws CampayException
     */
    private function validateEnvironmentVariables(): void
    {
        if (empty($_ENV['CAMPAY_USERNAME'])) {
            throw new CampayException('CAMPAY_USERNAME environment variable is required');
        }
        
        if (empty($_ENV['CAMPAY_PASSWORD'])) {
            throw new CampayException('CAMPAY_PASSWORD environment variable is required');
        }
    }

    /**
     * Validate collection data
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    private function validateCollectionData(array $data): void
    {
        $required = ['amount', 'currency', 'from', 'description'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for collection");
            }
        }
        
        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new ValidationException('Amount must be a positive number');
        }
    }

    /**
     * Validate withdrawal data
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    private function validateWithdrawData(array $data): void
    {
        $required = ['amount', 'currency', 'to', 'description'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for withdrawal");
            }
        }
        
        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new ValidationException('Amount must be a positive number');
        }
    }

    /**
     * Validate payment URL data
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    private function validatePaymentUrlData(array $data): void
    {
        $required = ['amount', 'currency', 'description', 'first_name', 'last_name', 'email'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for payment URL generation");
            }
        }
        
        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new ValidationException('Amount must be a positive number');
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email address provided');
        }
    }
}
