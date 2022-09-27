# PHP Campay

This is a php wrapper for the campay API that enables a seemless integration of momo with PHP and Campay.

## How to install this package
To install this package, run:
```bash
composer require blessdarah/php-campay
```

## Usage

The package automatically manages your tokens for all transactions with campay.

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
For withdrawal, we use the **widthdraw** function
```php
use BlessDarah\PhpCampay\Campay;

$campay = new Campay();

$data = array(
    "amount" => 3,
    "currency" => "XAF",
    "to" => "237******",
    "description" => "test payment"
);
$res = $campay->widthdraw($data);
echo $res;

```
The above response contains the info and the `reference` for your transaction that will enable you check its status using the `getTransactionStatus` function

### Check transaction status
For checking transaction status after using the `collect` or the `widthdraw` functions,
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
