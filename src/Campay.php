<?php

namespace BlessDarah\PhpCampay;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\StreamInterface;

class Campay
{
    private string $token;

    /*
     * @var array<string, string> $headers
     * */
    private array $headers;

    private Client $client;

    /**
     * Create a new campay instance
     *
     * @param string $base_url - Set the base uri or pull from the env by default
     * @return void
     */
    public function __construct(string $base_url = $_ENV['CAMPAY_BASE_URI'])
    {
        $this->client = new Client([
            'base_uri' => $base_url,
            'timeout' => 30, // 30 sec timeout
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

    /*
     * Get the set headers
     * @return array<string, string>
     * */
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
     * @throws GuzzleException
     * @return string
     */
    private function request_token(): string
    {
        $config = [
            'username' => $_ENV['CAMPAY_USERNAME'],
            'password' => $_ENV['CAMPAY_PASSWORD'],
        ];

        $options = [
            'form_params' => $config,
        ];

        $response = $this->client->post('token/', $options);
        $data = json_decode($response->getBody());
        $this->setHeaders([
            'Authorization' => 'Token '.$data->token,
        ]);

        return $data->token;
    }

    /**
     * Launches collection from user via USSD
     *
     * @param array<string, mixed> $data - the collection data
     * @throws GuzzleException
     * @return string - The request body
     */
    public function collect(array $data): string
    {
        $uri = 'collect/';
        $response = $this->client->post($uri, ['form_params' => $data, 'headers' => $this->getHeaders()]);

        return $response->getBody()->getContents();
    }

    /**
     * Gets the status of a specific transaction
     *
     * @param string $reference - The transaction reference
     * @throws GuzzleException
     *
     * @return string "SUCCESSFUL" | "PENDING" | "CANCELED" - the transaction status
     */
    public function getTransactionStatus(string $reference)
    {
        $uri = 'transaction/'.$reference.'/';
        $response = $this->client->get($uri, [
            'headers' => $this->getHeaders(),
        ]);

        return $response->getBody()->getContents();
    }

    /**
     * Trigger a withdrawal request
     *
     * @param array<string, mixed> $data - the client information
     * @throws GuzzleException
     * @return string - the withdrawal data
     */
    public function withdraw(array $data)
    {
        $uri = 'withdraw/';
        $response = $this->client->post($uri, ['form_params' => $data, 'headers' => $this->getHeaders()]);

        return $response->getBody()->getContents();
    }

    /**
     * Get application balance
     * @throws GuzzleException
     */
    public function getAppBalance(): string
    {
        $uri = 'balance/';
        $response = $this->client->get($uri, ['headers' => $this->getHeaders()]);

        return $response->getBody()->getContents();
    }

    /**
     * @throws GuzzleException
     * @param $start - The start date of the transaction
     * @param $end - The end date of the transaction
     */
    public function transactionHistory(string $start = null, string $end = null): string
    {
        $uri = 'history/';
        $date = new Carbon();
        if (! isset($start)) {
            $start = $date->subWeek()->format('Y-m-d');
        }

        if (! isset($end)) {
            $end = Carbon::now()->format('Y-m-d');
        }

        $params = [
            'start_date' => date_format(new \DateTime($start), 'Y-m-d'),
            'end_date' => date_format(new \DateTime($end), 'Y-m-d'),
        ];

        $response = $this->client->post($uri, ['form_params' => $params, 'headers' => $this->getHeaders()]);

        return $response->getBody()->getContents();
    }

    /**
     * Generate a payment URL to be used
     * @param array<string, mixed> $params
     * @throws GuzzleException
     */
    public function generatePaymentUrl(array $params): StreamInterface
    {
        $uri = 'get_payment_link/';
        $response = $this->client->post($uri, [
            'form_params' => $params,
            'headers' => $this->getHeaders(),
        ]);

        return $response->getBody();
    }
}
