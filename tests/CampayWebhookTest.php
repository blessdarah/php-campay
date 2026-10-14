<?php

use BlessDarah\PhpCampay\Campay;
use BlessDarah\PhpCampay\CampayWebhook;
use BlessDarah\PhpCampay\Exceptions\CampayException;
use BlessDarah\PhpCampay\Exceptions\InvalidWebhookException;
use BlessDarah\PhpCampay\WebhookEvent;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

const WEBHOOK_KEY = 'test-webhook-key';
const WEBHOOK_REFERENCE = 'b1d44bc7-648d-451b-a3ef-c5807738ec82';

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

/**
 * @param array<string, mixed> $claims
 * @param array<string, mixed> $header
 */
function signJwt(array $claims = [], string $key = WEBHOOK_KEY, array $header = ['alg' => 'HS256', 'typ' => 'JWT']): string
{
    $unsigned = base64UrlEncode(json_encode($header)) . '.' . base64UrlEncode(json_encode((object) $claims));

    return $unsigned . '.' . base64UrlEncode(hash_hmac('sha256', $unsigned, $key, true));
}

/**
 * @return array<string, mixed>
 */
function webhookPayload(array $overrides = []): array
{
    return array_merge([
        'status' => 'SUCCESSFUL',
        'reference' => WEBHOOK_REFERENCE,
        'amount' => '100',
        'currency' => 'XAF',
        'operator' => 'MTN',
        'code' => 'CP123456',
        'operator_reference' => '1234567890',
        'signature' => signJwt(),
        'endpoint' => 'collect',
        'external_reference' => 'order-42',
        'external_user' => '',
        'phone_number' => '237123456789',
        'description' => 'Order 42',
        'reason' => '',
        'app_amount' => '98',
    ], $overrides);
}

function campayWithTransaction(array $transaction): Campay
{
    $handlerStack = HandlerStack::create(new MockHandler([
        new Response(200, [], json_encode(['token' => 'abc123'])),
        new Response(200, [], json_encode($transaction)),
    ]));

    return new Campay('user', 'secret', 'https://example.test/api/', new Client(['handler' => $handlerStack]));
}

function successfulEvent(): WebhookEvent
{
    return (new CampayWebhook(WEBHOOK_KEY))->parse(webhookPayload());
}

test('it requires a webhook key', function () {
    new CampayWebhook('');
})->throws(CampayException::class);

test('it parses a signed GET webhook', function () {
    $event = (new CampayWebhook(WEBHOOK_KEY))->parse(webhookPayload());

    expect($event->isSuccessful())->toBeTrue()
        ->and($event->isFailed())->toBeFalse()
        ->and($event->isCollection())->toBeTrue()
        ->and($event->reference)->toBe(WEBHOOK_REFERENCE)
        ->and($event->amount)->toBe('100')
        ->and($event->appAmount)->toBe('98')
        ->and($event->operatorReference)->toBe('1234567890')
        ->and($event->externalReference)->toBe('order-42')
        ->and($event->externalUser)->toBeNull()
        ->and($event->reason)->toBeNull()
        ->and($event->data)->not->toHaveKey('signature');
});

test('it parses a signed POST webhook with JSON types', function () {
    $body = json_encode(webhookPayload(['status' => 'FAILED', 'amount' => 100, 'reason' => 'Insufficient balance', 'endpoint' => 'withdraw']));

    $event = (new CampayWebhook(WEBHOOK_KEY))->parseJson($body);

    expect($event->isFailed())->toBeTrue()
        ->and($event->isWithdrawal())->toBeTrue()
        ->and($event->amount)->toBe('100')
        ->and($event->reason)->toBe('Insufficient balance');
});

test('it accepts tokens with valid time claims', function () {
    $signature = signJwt(['iat' => time(), 'nbf' => time() - 10, 'exp' => time() + 300, 'reference' => WEBHOOK_REFERENCE]);

    expect((new CampayWebhook(WEBHOOK_KEY))->parse(webhookPayload(['signature' => $signature]))->reference)
        ->toBe(WEBHOOK_REFERENCE);
});

test('it rejects webhooks with a bad signature', function (array $payload) {
    (new CampayWebhook(WEBHOOK_KEY))->parse($payload);
})->with([
    'missing signature' => [webhookPayload(['signature' => null])],
    'empty signature' => [webhookPayload(['signature' => ''])],
    'wrong key' => [webhookPayload(['signature' => signJwt([], 'another-key')])],
    'not a JWT' => [webhookPayload(['signature' => 'asdfasdfasdf'])],
    'too many segments' => [webhookPayload(['signature' => signJwt() . '.extra'])],
    'invalid base64' => [webhookPayload(['signature' => 'a$b.c.d'])],
    'tampered claims' => [webhookPayload(['signature' => (function () {
        [$header, , $signature] = explode('.', signJwt(['reference' => 'other']));
        return $header . '.' . base64UrlEncode(json_encode(['reference' => WEBHOOK_REFERENCE])) . '.' . $signature;
    })()])],
    'alg none' => [webhookPayload(['signature' => base64UrlEncode('{"alg":"none"}') . '.' . base64UrlEncode('{}') . '.'])],
    'alg HS512' => [webhookPayload(['signature' => signJwt([], WEBHOOK_KEY, ['alg' => 'HS512'])])],
    'crit header' => [webhookPayload(['signature' => signJwt([], WEBHOOK_KEY, ['alg' => 'HS256', 'crit' => ['x']])])],
    'expired' => [webhookPayload(['signature' => signJwt(['exp' => time() - 3600])])],
    'not yet valid' => [webhookPayload(['signature' => signJwt(['nbf' => time() + 3600])])],
    'issued in the future' => [webhookPayload(['signature' => signJwt(['iat' => time() + 3600])])],
    'non-numeric exp' => [webhookPayload(['signature' => signJwt(['exp' => 'never'])])],
    'reference claim mismatch' => [webhookPayload(['signature' => signJwt(['reference' => 'another-reference'])])],
])->throws(InvalidWebhookException::class);

test('it rejects webhooks with invalid fields', function (array $payload) {
    (new CampayWebhook(WEBHOOK_KEY))->parse($payload);
})->with([
    'missing status' => [webhookPayload(['status' => null])],
    'unknown status' => [webhookPayload(['status' => 'PENDING'])],
    'missing reference' => [webhookPayload(['reference' => ''])],
    'path traversal reference' => [webhookPayload(['reference' => '../withdraw'])],
    'non-numeric amount' => [webhookPayload(['amount' => 'lots'])],
    'missing currency' => [webhookPayload(['currency' => null])],
    'array field' => [webhookPayload(['description' => ['nested']])],
    'list payload' => [['SUCCESSFUL', WEBHOOK_REFERENCE]],
])->throws(InvalidWebhookException::class);

test('it rejects invalid JSON bodies', function (string $body) {
    (new CampayWebhook(WEBHOOK_KEY))->parseJson($body);
})->with(['not json', '"a string"', '[]'])->throws(InvalidWebhookException::class);

test('it confirms a webhook against the Campay API', function () {
    $transaction = campayWithTransaction([
        'reference' => WEBHOOK_REFERENCE,
        'status' => 'SUCCESSFUL',
        'amount' => 100,
        'currency' => 'XAF',
    ])->confirmTransaction(successfulEvent(), 100);

    expect($transaction['status'])->toBe('SUCCESSFUL');
});

test('it rejects webhooks that do not match the Campay transaction', function (array $transaction, ?int $expectedAmount) {
    campayWithTransaction($transaction)->confirmTransaction(successfulEvent(), $expectedAmount);
})->with([
    'status differs' => [['reference' => WEBHOOK_REFERENCE, 'status' => 'PENDING', 'amount' => 100], null],
    'amount differs' => [['reference' => WEBHOOK_REFERENCE, 'status' => 'SUCCESSFUL', 'amount' => 1], null],
    'reference differs' => [['reference' => 'another-reference', 'status' => 'SUCCESSFUL', 'amount' => 100], null],
    'missing amount' => [['reference' => WEBHOOK_REFERENCE, 'status' => 'SUCCESSFUL'], null],
    'expected amount differs' => [['reference' => WEBHOOK_REFERENCE, 'status' => 'SUCCESSFUL', 'amount' => 100], 5000],
])->throws(InvalidWebhookException::class);
