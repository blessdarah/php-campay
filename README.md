# PHP Campay

This is a php wrapper for the campay API that enables a seemless integration of momo with PHP and Campay.

## Requirements

This package works as from `php@7.4` and above. Ensure that you have the latest version of php
installed.

## How to install this package

To install this package, run:

```bash
composer require blessdarah/php-campay:dev-main
```

Use the command below when the package is stable.

```bash
composer require blessdarah/php-campay
```

## Usage

The package automatically manages your tokens for all transactions with campay.

### Configuration

This packages uses the `dotenv` package and thus if you're not using something like laravel which automatically loads
env variables, you can set it up like this:

1. Create a `.env` file in the root of your project if you don't already have one
2. Copy your application `username` and `password` from your campay dashboard and add them like this:

```bash
CAMPAY_USERNAME="YOUR CAMPAY APPLICATION USERNAME"
CAMPAY_PASSWORD="YOUR CAMPAY APPLICATION PASSWORD"
```

3. In your `index.php` file or your root application entry point, you have to load up the `dotenv` package

```bash
require_once "vendor/autoload.php";
use BlessDarah\PhpCampay\Campay;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
```

If you're not using composer, you can ignore the above steps and set up your `.env` vars using the php global `$_ENV` to setup your campay
configurations in the location you want as follows:

```bash
$_ENV['CAMPAY_USERNAME']="YOUR CAMPAY APPLICATION USERNAME"
$_ENV['CAMPAY_PASSWORD']="YOUR CAMPAY APPLICATION PASSWORD"
```

Configure campay base url 
```bash
$_ENV['CAMPAY_BASE_URL']="https://demo.campay.net/api/" # for local testing or
$_ENV['CAMPAY_BASE_URL']="https://campay.net/api/" # for production
```

> **Remark**: You should make sure that you don't expose your config variables
> online as it will be a potential security issue for your application

### Collect payment

```php
use BlessDarah\PhpCampay\Campay;

$campay = new Campay();

$data = array(
    "amount" => 3,
    "currency" => "XAF",
    "from" => "237******",
    "description" => "test payment"
);
$res = $campay->collect($data);
// handle your response data from here
echo $res;
```

### Withdraw funds

For withdrawal, we use the **withdraw** function

```php
use BlessDarah\PhpCampay\Campay;

$campay = new Campay();

$data = array(
    "amount" => 3,
    "currency" => "XAF",
    "to" => "237******",
    "description" => "test payment"
);
$res = $campay->withdraw($data);
echo $res;

```

The above response contains the info and the `reference` for your transaction that will enable you check its status using the `getTransactionStatus` function

### Check transaction status

For checking transaction status after using the `collect` or the `withdraw` functions,
you can pass the resulting reference key in order to check your transaction status

```php
use BlessDarah\PhpCampay\Campay;

$campay = new Campay();

/*
* Suppose you have carried out a collection request from user then you can
* collection logic here
* /
$ref = $res->reference; // e.g: b1d44bc7-648d-451b-a3ef-c5807738ec82

$transaction_feedback = $campay->getTransactionStatus($ref) /* your reference code */

if($transaction_feedback->status == 'SUCCESSFUL')
{
    /* success logic here */
}

if($transaction_feedback->status == 'PENDING')
{
    /* Pending logic here */
}

if($transaction_feedback->status == 'FAILED')
{
    /* Erro logic here */
}
```

### Get your application balance

```php
use BlessDarah\PhpCampay\Campay;

$campay = new Campay();
$res = $campay->getAppBalance();

// handle your response data from here
echo $res;
```

### Get transaction history with an interval

By default, your transaction history will return all your app's transactions for the past **7 days** or past week

```php
use BlessDarah\PhpCampay\Campay;

$campay = new Campay();
$res = $campay->transactionHistory();

// handle your response data from here
echo $res;
```

If you want the dates to be specific, you can pass your `start` and `end` dates like so:

```php
$start_date = new Date("2022-06-13");
$end_date = new Date("2022-08-13");

$res = $campay->transactionHistory($start_date, $end_date);
```

### Generate payment link

As per the campay documentation, you can generate a payment link or url that can be utilized
for payments. Here's how you do it:

```php
use BlessDarah\PhpCampay\Campay;

$campay = new Campay();

$params = [
    "amount" => 4,
    "currency" => "XAF",
    "description" => "Sample description",
    "first_name" =>  "John",
    "last_name" =>  "Doe",
    "email" => "example@mail.com",
    "external_reference" =>  "",
    "redirect_url" =>  "https://example.com",
    "failure_redirect_url" => "https://example.com",
    "payment_options" => "MOMO" // or CARD for credit card payments
];
$res = $campay->generatePaymentUrl($params);

// handle your response data from here
echo $res;
```
