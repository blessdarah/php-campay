# Changelog

## 2.0.0 - Unreleased

### Breaking changes

- Requires PHP `^8.4` and Guzzle `^8.2`.
- The constructor is now `new Campay(string $username, string $password, string $baseUrl = Campay::DEMO_BASE_URL, ?GuzzleHttp\ClientInterface $client = null)`.
  Credentials are required, and the package no longer reads `$_ENV` (`CAMPAY_USERNAME`, `CAMPAY_PASSWORD`, `CAMPAY_BASE_URI`, `CAMPAY_BASE_URL`).
- Missing credentials now throw `CampayException` from the constructor instead of on the first request.
- The base URL must be an `https://` URL without credentials, query or fragment.
- `vlucas/phpdotenv` and `nesbot/carbon` are no longer dependencies. Load your configuration in your application.
- `getTransactionStatus()` only accepts references made of letters, numbers, `-` and `_`.
- `transactionHistory()` dates must be `YYYY-MM-DD` strings (or `DateTimeInterface` objects).
- `generatePaymentUrl()` requires `redirect_url` and `failure_redirect_url`, when given, to be `https://` URLs.
- Request payload values must be scalars (or `null`).
- `ApiException` messages no longer include Guzzle's message or the response body. The original exception is still available through `getPrevious()`.
- When Campay returns a non-JSON body, an `ApiException` is now thrown.

### Added

- `Campay::DEMO_BASE_URL` and `Campay::LIVE_BASE_URL` constants.
- `transactionHistory()` accepts `DateTimeInterface` objects.
- `composer test`, `composer analyse` and `composer audit` scripts, and a PHPStan config at max level.

### Security

- Upgraded to Guzzle 8 and Guzzle PSR-7 3, fixing all advisories reported by `composer audit` against 1.x (host confusion, CRLF injection, cookie and proxy header leaks).
- Upgraded Pest to 5 (PHPUnit 13), fixing the PHPUnit unsafe-deserialization advisory.
- Fixed path injection in `getTransactionStatus()`: references such as `../withdraw` could redirect the authenticated request to another endpoint.
- Requests always go to the configured base URL with redirects disabled, TLS verification on, and connect and total timeouts, even when a custom client is injected.
- Removed the example `index.php` (loaded `.env`) and `webhook.php` (echoed unvalidated request input).
