# PHP Campay

A PHP wrapper for the [Campay](https://campay.net) API that makes it easy to integrate Mobile Money payments into your PHP application.

## Requirements

- PHP `8.4` or newer
- `ext-json`
- [Guzzle](https://docs.guzzlephp.org) `8.x` (installed automatically)

## How to install this package

To install this package, run:

```bash
composer require blessdarah/php-campay
```

## Usage

The package automatically requests a token, caches it on the instance, and refreshes it once if Campay reports it expired.

### Configuration

Pass your application credentials (from your Campay dashboard) and the API base URL to the constructor:

```php
use BlessDarah\PhpCampay\Campay;

$campay = new Campay(
    username: getenv('CAMPAY_USERNAME'),
    password: getenv('CAMPAY_PASSWORD'),
    baseUrl: Campay::DEMO_BASE_URL, // or Campay::LIVE_BASE_URL in production
);
```

Load the credentials however your application manages secrets (environment variables, a secrets manager, your framework's config). The package never reads `.env` files or `$_ENV` itself.

> **Security notes**
>
> - Never commit your credentials or expose them to the browser.
> - The base URL must use `https://`. Plain `http://` URLs are rejected so credentials and tokens are never sent in clear text.
> - Redirects are not followed, TLS verification is always on, and requests time out after 30 seconds.

You can pass your own Guzzle client as the fourth argument (useful for testing or adding middleware). The package still enforces its own base URL and secure request options.

### Responses and errors

Every API method returns the raw JSON response body as a string:

```php
$response = json_decode($campay->getAppBalance(), true, flags: JSON_THROW_ON_ERROR);
```

Errors are thrown as exceptions that all extend `BlessDarah\PhpCampay\Exceptions\CampayException`:

| Exception                 | When                                                        |
| ------------------------- | ----------------------------------------------------------- |
| `ValidationException`     | Invalid input, checked before any request is sent           |
| `AuthenticationException` | Campay rejected the credentials or returned no token        |
| `ApiException`            | HTTP error, network failure, or an invalid response from Campay |
| `CampayException`         | Invalid configuration (missing credentials, non-https base URL) |

```php
use BlessDarah\PhpCampay\Exceptions\CampayException;

try {
    $response = $campay->collect($data);
} catch (CampayException $e) {
    // log $e and show a generic error to your user
}
```

### Collect payment

```php
$response = $campay->collect([
    'amount' => 3,
    'currency' => 'XAF',
    'from' => '237******',
    'description' => 'test payment',
]);
```

### Withdraw funds

```php
$response = $campay->withdraw([
    'amount' => 3,
    'currency' => 'XAF',
    'to' => '237******',
    'description' => 'test payment',
]);
```

The response contains the `reference` of your transaction, which you can use to check its status.

### Check transaction status

```php
$collection = json_decode($campay->collect($data), true, flags: JSON_THROW_ON_ERROR);

$status = json_decode(
    $campay->getTransactionStatus($collection['reference']), // e.g: b1d44bc7-648d-451b-a3ef-c5807738ec82
    true,
    flags: JSON_THROW_ON_ERROR,
);

switch ($status['status']) {
    case 'SUCCESSFUL':
        // success logic
        break;
    case 'PENDING':
        // pending logic
        break;
    case 'FAILED':
        // error logic
        break;
}
```

References may only contain letters, numbers, `-` and `_`. Anything else throws a `ValidationException`.

### Get your application balance

```php
$response = $campay->getAppBalance();
```

### Get transaction history

By default, this returns your app's transactions for the past **7 days**:

```php
$response = $campay->transactionHistory();
```

You can pass specific dates as `YYYY-MM-DD` strings or `DateTimeInterface` objects:

```php
$response = $campay->transactionHistory('2022-06-13', new DateTimeImmutable('2022-08-13'));
```

### Generate payment link

```php
$response = $campay->generatePaymentUrl([
    'amount' => 4,
    'currency' => 'XAF',
    'description' => 'Sample description',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'example@mail.com',
    'external_reference' => '',
    'redirect_url' => 'https://example.com',
    'failure_redirect_url' => 'https://example.com',
    'payment_options' => 'MOMO', // or CARD for credit card payments
]);
```

`redirect_url` and `failure_redirect_url` must be `https://` URLs.

### Handling webhooks

Campay notifies the callback URL set on your app in the Campay dashboard when a transaction becomes `SUCCESSFUL` or `FAILED`. Depending on your app settings, it sends a `GET` request with query parameters or a `POST` request with a JSON body.

Each notification carries a `signature`, which is an HS256 JWT signed with your app's **Webhook Key** (found in the Campay dashboard). `CampayWebhook` checks the signature and gives you a typed `WebhookEvent`:

```php
use BlessDarah\PhpCampay\Campay;
use BlessDarah\PhpCampay\CampayWebhook;
use BlessDarah\PhpCampay\Exceptions\CampayException;
use BlessDarah\PhpCampay\Exceptions\InvalidWebhookException;

$webhook = new CampayWebhook(getenv('CAMPAY_WEBHOOK_KEY'));

try {
    // GET callbacks
    $event = $webhook->parse($_GET);
    // POST callbacks
    // $event = $webhook->parseJson(file_get_contents('php://input'));

    $order = findOrderByReference($event->externalReference); // your own lookup

    // Confirm with Campay that the transaction really has this status and amount
    $campay->confirmTransaction($event, expectedAmount: $order->amount);
} catch (InvalidWebhookException $e) {
    http_response_code(400);
    exit;
} catch (CampayException $e) {
    http_response_code(503); // e.g. Campay could not be reached, let it retry
    exit;
}

if ($event->isSuccessful() && !$order->isPaid()) {
    $order->markAsPaid($event->reference); // process each reference only once
} elseif ($event->isFailed()) {
    // $event->reason explains why
}

http_response_code(200);
```

> **Why `confirmTransaction()`?** A valid signature proves the request came from Campay, but Campay
> does not document that the token covers the other fields (status, amount, ...). Always confirm
> the transaction with the API, and compare it with the amount your order expects, before
> fulfilling it. Never echo webhook data back in the response.

`WebhookEvent` exposes `status`, `reference`, `amount`, `currency`, `operator`, `code`, `operatorReference`, `endpoint`, `externalReference`, `externalUser`, `phoneNumber`, `description`, `reason` and `appAmount`. It also keeps every field Campay sent (except the signature) in `$event->data`.

## Development

```bash
composer test     # run the test suite
composer analyse  # run PHPStan
composer audit    # check dependencies for known vulnerabilities
```

## Upgrading from 1.x

See [CHANGELOG.md](CHANGELOG.md).
