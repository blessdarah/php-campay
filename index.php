<?php

use BlessDarah\PhpCampay\Campay;
use Dotenv\Dotenv;

require_once 'vendor/autoload.php';
$env = Dotenv::createImmutable(__DIR__);
$env->load();


$campay = new Campay();


$data = array(
    "amount" => 3,
    "currency" => "XAF",
    "from" => "237672374414",
    "description" => "test payment"
);


$ref = "b1d44bc7-648d-451b-a3ef-c58d7438ec82";

$campay->getAppBalance();

$campay->transactionHistoy();

//$campay->collect($data);