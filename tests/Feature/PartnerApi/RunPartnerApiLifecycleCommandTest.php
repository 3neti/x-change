<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('runs the default Partner API contract scenario entirely over HTTP', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://partner.example.test/oauth/token' => Http::response(['access_token' => 'test-token']),
        'https://partner.example.test/api/partner/v1/capabilities' => Http::response([
            'success' => true,
            'data' => ['schema' => 'x-change.partner-capabilities.v1'],
            'meta' => [],
        ]),
    ]);

    $exitCode = Artisan::call('x-change:partner-api:run', [
        '--base-url' => 'https://partner.example.test',
        '--client-id' => 'client-id',
        '--client-secret' => 'client-secret',
        '--json' => true,
        '--no-interaction' => true,
    ]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($report['scenario'])->toBe('contract')
        ->and($report['safety']['transport'])->toBe('http')
        ->and($report['safety']['direct_action_calls'])->toBeFalse()
        ->and($report['safety']['financial_mutation_confirmed'])->toBeFalse();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://partner.example.test/oauth/token'
        && $request['grant_type'] === 'client_credentials'
        && $request['scope'] === 'capabilities:read');
});

it('requires explicit confirmation before the HTTP issuance scenario', function () {
    Http::preventStrayRequests();

    $this->artisan('x-change:partner-api:run', [
        '--base-url' => 'https://partner.example.test',
        '--client-id' => 'client-id',
        '--client-secret' => 'client-secret',
        '--scenario' => 'issue-and-cancel',
        '--json' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    Http::assertNothingSent();
});

it('issues reads and cancels through HTTP only after financial confirmation', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            str_ends_with($request->url(), '/oauth/token') => Http::response(['access_token' => 'test-token']),
            str_ends_with($request->url(), '/capabilities') => Http::response(['data' => ['schema' => 'capabilities']]),
            str_ends_with($request->url(), '/pay-code-estimates') => Http::response(['data' => ['account_debit' => 16.50]]),
            str_ends_with($request->url(), '/pay-codes') => Http::response(['data' => ['code' => 'API-1234']], 201),
            str_ends_with($request->url(), '/pay-codes/API-1234/cancellation') => Http::response(['data' => ['code' => 'API-1234', 'status' => 'cancelled']]),
            str_ends_with($request->url(), '/pay-codes/API-1234') => Http::response(['data' => ['code' => 'API-1234', 'status' => ['key' => 'active']]]),
            default => Http::response(['message' => 'Unexpected request'], 500),
        };
    });

    $exitCode = Artisan::call('x-change:partner-api:run', [
        '--base-url' => 'https://partner.example.test',
        '--client-id' => 'client-id',
        '--client-secret' => 'client-secret',
        '--scenario' => 'issue-and-cancel',
        '--confirm-financial-mutation' => true,
        '--json' => true,
        '--no-interaction' => true,
    ]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(data_get($report, 'lifecycle.issuance.code'))->toBe('API-1234')
        ->and(data_get($report, 'lifecycle.cancellation.status'))->toBe('cancelled')
        ->and(data_get($report, 'safety.commercial_charges_refunded'))->toBeFalse();

    Http::assertSentCount(6);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/pay-codes')
        && filled($request->header('Idempotency-Key')[0] ?? null)
        && filled($request->header('X-Correlation-ID')[0] ?? null));
});
