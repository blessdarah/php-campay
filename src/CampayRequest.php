<?php

namespace BlessDarah\PhpCampay;

class CampayRequest
{
    private $request;

    public function __construct(string $url, array $headers)
    {
        $this->request = curl_init($url);
        curl_setopt($this->request, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->request, CURLOPT_RETURNTRANSFER, true);
    }

    public function get()
    {
        $response = curl_exec($this->request);
        curl_close($this->request);
        return $response;
    }

    public function post(array $params = [])
    {
        curl_setopt($this->request, CURLOPT_POST, true);
        curl_setopt($this->request, CURLOPT_POSTFIELDS, json_encode($params));
        $response = curl_exec($this->request);
        curl_close($this->request);
        return $response;
    }

}