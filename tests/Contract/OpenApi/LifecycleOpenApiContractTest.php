<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Contract\OpenApi;

use LBHurtado\XChange\Tests\Contract\OpenApi\Support\OpenApiDocument;
use LBHurtado\XChange\Tests\Contract\OpenApi\Support\OpenApiPathFinder;
use LBHurtado\XChange\Tests\Contract\OpenApi\Support\OpenApiResponseMatcher;

uses(OpenApiTestCase::class);

it('loads the lifecycle openapi document', function () {
    $doc = OpenApiDocument::load($this->openApiSpecPath());

    expect($doc->openApiVersion())->not->toBeNull()
        ->and($doc->paths())->not->toBeEmpty();
});

it('contains the expected lifecycle operations', function () {
    $doc = OpenApiDocument::load($this->openApiSpecPath());
    $finder = new OpenApiPathFinder($doc);

    $missing = $finder->missingOperations([
        ['method' => 'post', 'path' => '/issuers'],
        ['method' => 'post', 'path' => '/issuers/{issuer}/wallets'],
        ['method' => 'get', 'path' => '/wallets/{wallet}'],
        ['method' => 'get', 'path' => '/wallets/{wallet}/balance'],
        ['method' => 'get', 'path' => '/wallets/{wallet}/ledger'],
        ['method' => 'post', 'path' => '/wallets/{wallet}/top-ups'],
        ['method' => 'get', 'path' => '/pricelist'],
        ['method' => 'get', 'path' => '/pricelist/items'],
        ['method' => 'post', 'path' => '/vouchers/estimate'],
        ['method' => 'post', 'path' => '/vouchers'],
        ['method' => 'get', 'path' => '/vouchers'],
        ['method' => 'get', 'path' => '/vouchers/{voucher}'],
        ['method' => 'get', 'path' => '/vouchers/code/{code}'],
        ['method' => 'get', 'path' => '/vouchers/{voucher}/status'],
        ['method' => 'post', 'path' => '/vouchers/{voucher}/cancel'],
        ['method' => 'post', 'path' => '/vouchers/code/{code}/claim/start'],
        ['method' => 'post', 'path' => '/vouchers/code/{code}/claim/submit'],
        ['method' => 'post', 'path' => '/vouchers/code/{code}/claim/complete'],
        ['method' => 'get', 'path' => '/vouchers/code/{code}/claim/status'],
        ['method' => 'get', 'path' => '/reconciliations'],
        ['method' => 'get', 'path' => '/reconciliations/{reconciliation}'],
        ['method' => 'post', 'path' => '/reconciliations/{reconciliation}/resolve'],
        ['method' => 'get', 'path' => '/events'],
        ['method' => 'get', 'path' => '/events/{event}'],
        ['method' => 'get', 'path' => '/events/idempotency/{key}'],
    ]);

    expect($missing)->toBe([]);
});

it('documents request schemas for key write endpoints', function () {
    $doc = OpenApiDocument::load($this->openApiSpecPath());

    expect($doc->requestSchema('post', '/vouchers'))->not->toBeNull()
        ->and($doc->requestSchema('post', '/vouchers/code/{code}/claim/submit'))->not->toBeNull()
        ->and($doc->requestSchema('post', '/vouchers/code/{code}/claim/complete'))->not->toBeNull()
        ->and($doc->requestSchema('post', '/vouchers/{voucher}/cancel'))->not->toBeNull()
        ->and($doc->requestSchema('post', '/reconciliations/{reconciliation}/resolve'))->not->toBeNull();
});

it('matches the documented response shape for voucher list response', function () {
    $doc = OpenApiDocument::load($this->openApiSpecPath());
    $matcher = new OpenApiResponseMatcher($doc);

    $matcher->assertMatchesResponseSchema('get', '/vouchers', '200', [
        'success' => true,
        'data' => [
            'items' => [
                [
                    'id' => 1,
                    'voucher_id' => 99,
                    'code' => 'TEST-1234',
                    'amount' => 100,
                    'currency' => 'PHP',
                    'status' => 'issued',
                    'issuer_id' => 1,
                ],
            ],
        ],
        'meta' => [],
    ]);

    expect(true)->toBeTrue();
});

it('matches the documented response shape for reconciliation resolve response', function () {
    $doc = OpenApiDocument::load($this->openApiSpecPath());
    $matcher = new OpenApiResponseMatcher($doc);

    $matcher->assertMatchesResponseSchema('post', '/reconciliations/{reconciliation}/resolve', '200', [
        'success' => true,
        'data' => [
            'reconciliation_id' => 'rec-001',
            'status' => 'resolved',
            'resolution' => 'manual_clear',
            'resolved' => true,
            'notes' => 'Reviewed and cleared.',
            'messages' => ['Reconciliation resolved successfully.'],
        ],
        'meta' => [],
    ]);

    expect(true)->toBeTrue();
});

it('matches the documented response shape for event idempotency response', function () {
    $doc = OpenApiDocument::load($this->openApiSpecPath());
    $matcher = new OpenApiResponseMatcher($doc);

    $matcher->assertMatchesResponseSchema('get', '/events/idempotency/{key}', '200', [
        'success' => true,
        'data' => [
            'idempotency' => [
                'key' => 'idem-001',
                'replayed' => true,
                'first_seen_at' => '2026-04-22T10:00:00+00:00',
                'last_seen_at' => '2026-04-22T10:05:00+00:00',
                'request_fingerprint' => 'fp-001',
                'response_status' => 200,
            ],
        ],
        'meta' => [],
    ]);

    expect(true)->toBeTrue();
});
