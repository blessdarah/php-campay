<?php

use BlessDarah\PhpCampay\Campay;
use BlessDarah\PhpCampay\Exceptions\ValidationException;
use BlessDarah\PhpCampay\Exceptions\AuthenticationException;
use BlessDarah\PhpCampay\Exceptions\CampayException;
use Dotenv\Dotenv;

beforeEach(function () {
    $env = Dotenv::createMutable(__DIR__ . "/../");
    $env->load();
});

test("expect token to be fetched upon creating new campay object", function () {
    $model = new Campay();
    expect($model->getToken())->not()->toBeEmpty();
    expect($model->getToken())->toBeString();
});

test("should throw ValidationException when collection data is empty", function () {
    $model = new Campay();
    $model->collect([]);
})->throws(ValidationException::class);

test("should throw ValidationException when required collection fields are missing", function () {
    $model = new Campay();
    $model->collect([
        'amount' => 100,
        'currency' => 'XAF'
        // missing 'from' and 'description'
    ]);
})->throws(ValidationException::class);

test("should throw ValidationException when amount is not positive", function () {
    $model = new Campay();
    $model->collect([
        'amount' => -100,
        'currency' => 'XAF',
        'from' => '237123456789',
        'description' => 'Test payment'
    ]);
})->throws(ValidationException::class);

test("should throw ValidationException when withdrawal data is empty", function () {
    $model = new Campay();
    $model->withdraw([]);
})->throws(ValidationException::class);

test("should throw ValidationException when required withdrawal fields are missing", function () {
    $model = new Campay();
    $model->withdraw([
        'amount' => 100,
        'currency' => 'XAF'
        // missing 'to' and 'description'
    ]);
})->throws(ValidationException::class);

test("should throw ValidationException when transaction reference is empty", function () {
    $model = new Campay();
    $model->getTransactionStatus('');
})->throws(ValidationException::class);

test("should throw ValidationException when payment URL data is incomplete", function () {
    $model = new Campay();
    $model->generatePaymentUrl([
        'amount' => 100,
        'currency' => 'XAF'
        // missing required fields
    ]);
})->throws(ValidationException::class);

test("should throw ValidationException when email is invalid in payment URL data", function () {
    $model = new Campay();
    $model->generatePaymentUrl([
        'amount' => 100,
        'currency' => 'XAF',
        'description' => 'Test payment',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'invalid-email'
    ]);
})->throws(ValidationException::class);

test("should throw CampayException when environment variables are missing", function () {
    unset($_ENV['CAMPAY_USERNAME']);
    unset($_ENV['CAMPAY_PASSWORD']);
    
    new Campay();
})->throws(CampayException::class);
