<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Exceptions\FundingIntentConflict;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingIntentEvent;

function fundingIntentData(array $overrides = []): CreateFundingIntentData
{
    return new CreateFundingIntentData(...array_merge([
        'accountReference' => 'wallet:account-1001',
        'provider' => 'netbank',
        'expectedAmountMinor' => 25_000,
        'currency' => 'php',
        'idempotencyKey' => 'funding-request-1001',
        'actorType' => 'App\\Models\\User',
        'actorId' => '42',
        'expiresAt' => new DateTimeImmutable('2026-07-23T10:00:00+08:00'),
        'metadata' => ['source' => 'test'],
    ], $overrides));
}

beforeEach(function () {
    config()->set('x-change.funding.providers.netbank.enabled', true);
});

it('creates an idempotent Funding Intent and an append-only creation event', function () {
    $create = app(CreateFundingIntent::class);

    $first = $create->handle(fundingIntentData());
    $retry = $create->handle(fundingIntentData());

    expect($retry->getKey())->toBe($first->getKey())
        ->and(FundingIntent::query()->count())->toBe(1)
        ->and(FundingIntentEvent::query()->count())->toBe(1)
        ->and($first->reference)->toHaveLength(26)
        ->and($first->status)->toBe(FundingIntentStatus::PendingInstructions)
        ->and($first->expected_amount_minor)->toBe(25_000)
        ->and($first->currency)->toBe('PHP')
        ->and($first->version)->toBe(1)
        ->and($first->events->first()->event_type)->toBe('created');
});

it('rejects an idempotency key reused for a different funding request', function () {
    $create = app(CreateFundingIntent::class);

    $create->handle(fundingIntentData());

    expect(fn () => $create->handle(fundingIntentData([
        'expectedAmountMinor' => 30_000,
    ])))->toThrow(FundingIntentConflict::class);
});

it('rejects disabled providers before creating an intent', function () {
    config()->set('x-change.funding.providers.netbank.enabled', false);

    expect(fn () => app(CreateFundingIntent::class)->handle(fundingIntentData()))
        ->toThrow(InvalidArgumentException::class, 'is not enabled')
        ->and(FundingIntent::query()->count())->toBe(0);
});

it('does not allow Funding Intents or their events to be deleted or rewritten', function () {
    $intent = app(CreateFundingIntent::class)->handle(fundingIntentData());
    $event = $intent->events->first();

    expect(fn () => $intent->update(['expected_amount_minor' => 1]))
        ->toThrow(LogicException::class, 'guarded funding actions')
        ->and(fn () => $intent->delete())
        ->toThrow(LogicException::class, 'cannot be deleted')
        ->and(fn () => $event->update(['event_type' => 'tampered']))
        ->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $event->delete())
        ->toThrow(LogicException::class, 'append-only');
});

it('blocks the synthetic lifecycle funding command outside explicitly safe environments', function () {
    config()->set('x-change.lifecycle.synthetic_funding_environments', ['local']);

    expect(Artisan::call('xchange:lifecycle:prepare', ['--json' => true]))
        ->toBe(1)
        ->and(Artisan::output())
        ->toContain('Synthetic lifecycle funding is disabled');
});
