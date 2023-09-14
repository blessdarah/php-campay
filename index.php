<?php

use BlessDarah\PhpCampay\Campay;
use Dotenv\Dotenv;

require_once 'vendor/autoload.php';
$env = Dotenv::createImmutable(__DIR__);
$env->load();


$campay = new Campay();

$params = [
    "amount" => 4,
    "currency" => "XAF",
    "description" => "Sample description",
    "first_name" =>  "John",
    "last_name" =>  "Doe",
    "email" => "blessdarahuba@gmail.com",
    "external_reference" =>  "",
    "payment_options" => "MOMO" // or CARD for credit card payments
];


// $campay->generatePaymentUrl($params);

// $campay->getAppBalance();
//
// $campay->transactionHistory();

$data = array(
    "amount" => 3,
    "currency" => "XAF",
    "from" => "237672374414",
    "description" => "test payment"
);
echo $campay->collect($data);
