<?php

use BlessDarah\PhpCampay\Campay;
use Dotenv\Dotenv;

$env = Dotenv::createMutable(__DIR__ . "/../");
$env->load();

test("expect token to be fetched upon creating new campay object", function () {
    $model = new Campay();
    expect($model->getToken())->not()->toBeEmpty();
    expect($model->getToken())->toBeString();
});

test("should fail if user collection information is wrong", function () {
    try {
        $model = new Campay();
        $model->collect([]);

    } catch(Exception $e) {
        throw new Exception($e->getMessage());
    }
})->throws(Exception::class, "Bad request");
