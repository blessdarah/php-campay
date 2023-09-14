<?php

namespace BlessDarah\PhpCampay;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Campay
{
    private string $token;
    private array $headers;
    private Client $client;

    /**
     * constructor
     */
    public function __construct($base_url = "https://demo.campay.net/api/")
    {
        $this->client = new Client([
            'base_uri' => $base_url,
            'timeout' => 30 // 30 sec timeout
        ]);

        $this->token = $this->request_token();
    }

    /**
     * @param array $headers
     * @returns void
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     * @returns void
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @return mixed
     * @throws GuzzleException
     */
    private function request_token()
    {
        $config = [
            "username" => $_ENV['CAMPAY_USERNAME'],
            "password" => $_ENV['CAMPAY_PASSWORD']
        ];

        $options = [
            "form_params" => $config
        ];

        $response = $this->client->post('token/', $options);
        $data = json_decode($response->getBody());
        $this->setHeaders([
            "Authorization" => "Token " . $data->token
        ]);
        return $data->token;
    }


    /**
     * @param array $data
     * @throws GuzzleException
     */
    public function collect(array $data)
    {
        $uri = "collect/";
        $response = $this->client->post($uri, array("form_params" => $data, "headers" => $this->getHeaders()));
        return $response->getBody()->getContents();
    }

    /**
     * @param string $reference
     * @throws GuzzleException
     */
    public function getTransactionStatus(string $reference)
    {
        $uri = "transaction/" . $reference . "/";
        $response = $this->client->get($uri, [
            "headers" => $this->getHeaders()
        ]);
        return $response->getBody()->getContents();
    }

    /**
     * @param array $data
     * @throws GuzzleException
     */
    public function withdraw(array $data)
    {
        $uri = "withdraw/";
        $response = $this->client->post($uri, array("form_params" => $data, "headers" => $this->getHeaders()));
        return $response->getBody()->getContents();
    }


    /**
     * @throws GuzzleException
     */
    public function getAppBalance()
    {
        $uri = "balance/";
        $response = $this->client->get($uri, array("headers" => $this->getHeaders()));
        return $response->getBody()->getContents();
    }


    /**
     * @param $start
     * @param $end
     * @throws GuzzleException
     */
    public function transactionHistory($start = null, $end = null)
    {
        $uri = "history/";
        $date = new Carbon();
        if(!isset($start)) {
            $start = $date->subWeek()->format('Y-m-d');
        }

        if(!isset($end)) {
            $end = Carbon::now()->format('Y-m-d');
        }

        $params = [
            "start_date" => date_format(new \DateTime($start), "Y-m-d"),
            "end_date" => date_format(new \DateTime($end), "Y-m-d"),
        ];

        $response = $this->client->post($uri, array("form_params" => $params, "headers" => $this->getHeaders()));
        return $response->getBody()->getContents();
    }


    /**
     * @param array $params
     * @throws GuzzleException
     */
    public function generatePaymentUrl(array $params)
    {
        $uri = "get_payment_link/";
        $response = $this->client->post($uri, array(
            "form_params" => $params,
            "headers" => $this->getHeaders()
        ));
        return $response->getBody();
    }
}
