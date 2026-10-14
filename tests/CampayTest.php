<?php

use BlessDarah\PhpCampay\Campay;
use BlessDarah\PhpCampay\Exceptions\ApiException;
use BlessDarah\PhpCampay\Exceptions\AuthenticationException;
use BlessDarah\PhpCampay\Exceptions\CampayException;
use BlessDarah\PhpCampay\Exceptions\ValidationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * @param array<int, mixed> $responses
 * @param array<int, array<string, mixed>> $history
 */
function createCampay(array $responses, array &$history = [], string $baseUrl = 'https://example.test/api'): Campay
{
    $handlerStack = HandlerStack::create(new MockHandler($responses));
    $handlerStack->push(Middleware::history($history));

    return new Campay('demo-user', 'demo-password', $baseUrl, new Client(['handler' => $handlerStack]));
}

function tokenResponse(string $token = 'abc123'): Response
{
    return new Response(200, [], json_encode(['token' => $token]));
}

$validCollection = [
    'amount' => 100,
    'currency' => 'XAF',
    'from' => '237123456789',
    'description' => 'Test payment',
];

test('it fetches token lazily when getToken is called', function () {
    $history = [];
    $model = createCampay([tokenResponse()], $history);

    expect($model->getToken())->toBe('abc123')
        ->and((string) $history[0]['request']->getUri())->toBe('https://example.test/api/token/');
});

test('it throws CampayException when credentials are missing', function (string $username, string $password) {
    new Campay($username, $password);
})->with([
    'empty username' => ['', 'secret'],
    'blank username' => ['   ', 'secret'],
    'empty password' => ['user', ''],
])->throws(CampayException::class);

test('it rejects insecure or malformed base URLs', function (string $baseUrl) {
    new Campay('user', 'secret', $baseUrl);
})->with([
    'plain http' => ['http://demo.campay.net/api/'],
    'no scheme' => ['demo.campay.net/api/'],
    'userinfo' => ['https://user:pass@demo.campay.net/api/'],
    'query string' => ['https://demo.campay.net/api/?x=1'],
    'fragment' => ['https://demo.campay.net/api/#x'],
])->throws(CampayException::class);

test('it ignores the base_uri of a custom client and always uses its own base URL', function () {
    $history = [];
    $handlerStack = HandlerStack::create(new MockHandler([tokenResponse()]));
    $handlerStack->push(Middleware::history($history));
    $client = new Client(['handler' => $handlerStack, 'base_uri' => 'https://attacker.test/']);

    (new Campay('user', 'secret', Campay::DEMO_BASE_URL, $client))->getToken();

    expect($history[0]['request']->getUri()->getHost())->toBe('demo.campay.net');
});

test('it sends requests with secure transport options', function () {
    $history = [];
    createCampay([tokenResponse()], $history)->getToken();

    expect($history[0]['options'])
        ->toMatchArray(['allow_redirects' => false, 'verify' => true, 'http_errors' => true]);
});

test('it throws ValidationException when collection data is empty', function () {
    createCampay([])->collect([]);
})->throws(ValidationException::class);

test('it throws ValidationException when required collection fields are missing', function () {
    createCampay([])->collect([
        'amount' => 100,
        'currency' => 'XAF',
    ]);
})->throws(ValidationException::class);

test('it throws ValidationException when amount is not positive', function (mixed $amount) use ($validCollection) {
    createCampay([])->collect(['amount' => $amount] + $validCollection);
})->with([-100, 0, '0', 'abc'])->throws(ValidationException::class);

test('it throws ValidationException when a field is not a scalar value', function () use ($validCollection) {
    createCampay([])->collect($validCollection + ['external_reference' => ['nested']]);
})->throws(ValidationException::class);

test('it throws ValidationException when withdrawal data is empty', function () {
    createCampay([])->withdraw([]);
})->throws(ValidationException::class);

test('it throws ValidationException when required withdrawal fields are missing', function () {
    createCampay([])->withdraw([
        'amount' => 100,
        'currency' => 'XAF',
    ]);
})->throws(ValidationException::class);

test('it throws ValidationException when transaction reference is empty', function () {
    createCampay([])->getTransactionStatus('  ');
})->throws(ValidationException::class);

test('it rejects transaction references that could alter the request path', function (string $reference) {
    $history = [];

    expect(fn () => createCampay([tokenResponse()], $history)->getTransactionStatus($reference))
        ->toThrow(ValidationException::class)
        ->and($history)->toBeEmpty();
})->with([
    'path traversal' => ['../withdraw'],
    'slash' => ['abc/def'],
    'query' => ['abc?x=1'],
    'fragment' => ['abc#x'],
    'encoded' => ['abc%2F..'],
]);

test('it fetches the transaction status for a valid reference', function () {
    $history = [];
    $model = createCampay([
        tokenResponse(),
        new Response(200, [], json_encode(['status' => 'SUCCESSFUL'])),
    ], $history);

    $response = $model->getTransactionStatus('b1d44bc7-648d-451b-a3ef-c5807738ec82');

    expect($response)->toContain('SUCCESSFUL')
        ->and((string) $history[1]['request']->getUri())
        ->toBe('https://example.test/api/transaction/b1d44bc7-648d-451b-a3ef-c5807738ec82/');
});

test('it throws ValidationException when payment URL data is incomplete', function () {
    createCampay([])->generatePaymentUrl([
        'amount' => 100,
        'currency' => 'XAF',
    ]);
})->throws(ValidationException::class);

$validPaymentUrl = [
    'amount' => 100,
    'currency' => 'XAF',
    'description' => 'Test payment',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john@example.com',
];

test('it throws ValidationException when email is invalid in payment URL data', function () use ($validPaymentUrl) {
    createCampay([])->generatePaymentUrl(['email' => 'invalid-email'] + $validPaymentUrl);
})->throws(ValidationException::class);

test('it rejects non-https redirect URLs in payment URL data', function (string $field, string $url) use ($validPaymentUrl) {
    createCampay([])->generatePaymentUrl([$field => $url] + $validPaymentUrl);
})->with([
    ['redirect_url', 'javascript:alert(1)'],
    ['redirect_url', 'http://example.com'],
    ['failure_redirect_url', 'not a url'],
])->throws(ValidationException::class);

test('it performs collection after authenticating lazily', function () use ($validCollection) {
    $history = [];
    $model = createCampay([
        tokenResponse(),
        new Response(200, [], json_encode(['reference' => 'ref-1', 'status' => 'PENDING'])),
    ], $history);

    $response = $model->collect($validCollection);

    expect($response)->toContain('"reference":"ref-1"')
        ->and($history[1]['request']->getHeaderLine('Authorization'))->toBe('Token abc123');
});

test('it re-authenticates once when the token has expired', function () use ($validCollection) {
    $history = [];
    $model = createCampay([
        tokenResponse('old'),
        new Response(401),
        tokenResponse('new'),
        new Response(200, [], json_encode(['reference' => 'ref-1'])),
    ], $history);

    $model->collect($validCollection);

    expect($history)->toHaveCount(4)
        ->and($history[3]['request']->getHeaderLine('Authorization'))->toBe('Token new');
});

test('it does not retry authentication more than once', function () use ($validCollection) {
    createCampay([
        tokenResponse('old'),
        new Response(401),
        tokenResponse('new'),
        new Response(401),
    ])->collect($validCollection);
})->throws(ApiException::class, 'Collection request failed with HTTP status 401');

test('it throws AuthenticationException for rejected credentials', function () {
    createCampay([new Response(400, [], json_encode(['detail' => 'bad credentials']))])->getToken();
})->throws(AuthenticationException::class);

test('it throws AuthenticationException when the token is missing from the response', function () {
    createCampay([new Response(200, [], json_encode(['foo' => 'bar']))])->getToken();
})->throws(AuthenticationException::class);

test('it throws ApiException on server errors without leaking the response body', function () {
    try {
        createCampay([tokenResponse(), new Response(500, [], 'internal secret details')])->getAppBalance();
    } catch (ApiException $e) {
        expect($e->getMessage())->toBe('Balance request failed with HTTP status 500')
            ->and($e->getCode())->toBe(500);
        return;
    }

    $this->fail('ApiException was not thrown');
});

test('it throws ApiException on network errors', function () {
    createCampay([
        new ConnectException('Connection refused', new Request('POST', 'https://example.test/api/token/')),
    ])->getToken();
})->throws(ApiException::class, 'unable to reach Campay');

test('it throws ApiException on invalid JSON responses', function () {
    createCampay([tokenResponse(), new Response(200, [], '<html>oops</html>')])->getAppBalance();
})->throws(ApiException::class, 'invalid JSON');

test('it throws ValidationException for invalid transaction history date format', function (string $date) {
    createCampay([])->transactionHistory($date, '2025-01-01');
})->with(['not-a-date', '2025-13-01', '2025-02-30', '01/01/2025'])->throws(ValidationException::class);

test('it throws ValidationException when start date is after end date', function () {
    createCampay([])->transactionHistory('2025-02-01', '2025-01-01');
})->throws(ValidationException::class);

test('it accepts DateTime objects for transaction history', function () {
    $history = [];
    createCampay([tokenResponse(), new Response(200, [], '[]')], $history)
        ->transactionHistory(new DateTimeImmutable('2025-01-01'), new DateTime('2025-01-31'));

    expect((string) $history[1]['request']->getBody())->toBe('start_date=2025-01-01&end_date=2025-01-31');
});
