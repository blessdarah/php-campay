<?php

use BlessDarah\PhpCampay\Campay;

require_once 'vendor/autoload.php';

$_ENV['CAMPAY_USERNAME'] = "9lBc9TNNrF3fgaA16WbSvhNFyWneTPN89EAwdSjCQX7WrQ1ERoNhv6jvTWBCOzwrL9QbePeIHcCY4K4tXmYEAw";
$_ENV['CAMPAY_PASSWORD'] = "hAq0q4zLzIo74NQ2dp5afbaYzIvfJqCap3w1klzMvATM3Pw2bhregIxuw7q4cb_P1sx45B6ifkZxBZM0ucJaEw";


$campay = new Campay();

$data = array(
    "amount" => 3,
    "currency" => "XAF",
    "from" => "237672374414",
    "description" => "test payment"
);
$ref = "b1d44bc7-648d-451b-a3ef-c58d7438ec82";
//$campay->getTransactionStatus($ref);
$campay->getAppBalance();
$campay->transactionHistoy();
//$campay->collect($data);