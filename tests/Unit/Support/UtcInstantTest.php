<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use LBHurtado\XChange\Rules\TimezoneAwareInstant;
use LBHurtado\XChange\Rules\UtcDateOrTimezoneAwareInstant;
use LBHurtado\XChange\Support\Time\UtcInstant;

it('canonicalizes equivalent offset-bearing instants to one UTC representation', function (): void {
    expect(UtcInstant::canonical('2026-08-17T21:00:00+08:00'))
        ->toBe('2026-08-17T13:00:00.000000Z')
        ->toBe(UtcInstant::canonical('2026-08-17T13:00:00Z'));
});

it('preserves microseconds in the canonical UTC representation', function (): void {
    expect(UtcInstant::canonical('2026-08-17T21:00:00.123456+08:00'))
        ->toBe('2026-08-17T13:00:00.123456Z');
});

it('rejects externally supplied instants without an explicit timezone offset', function (): void {
    expect(fn () => UtcInstant::parseOffsetRequired('2026-08-17 21:00:00'))
        ->toThrow(InvalidArgumentException::class, 'must include Z or a numeric timezone offset');
});

it('treats explicit calendar dates as UTC day boundaries', function (): void {
    expect(UtcInstant::canonical(UtcInstant::parseDateOrOffsetRequired('2026-08-17')))
        ->toBe('2026-08-17T00:00:00.000000Z');
});

it('validates authoritative instants and calendar periods at their public boundaries', function (): void {
    expect(Validator::make(
        ['instant' => '2026-08-17 21:00:00'],
        ['instant' => [new TimezoneAwareInstant]],
    )->fails())->toBeTrue()
        ->and(Validator::make(
            ['instant' => '2026-08-17T21:00:00+08:00'],
            ['instant' => [new TimezoneAwareInstant]],
        )->passes())->toBeTrue()
        ->and(Validator::make(
            ['period' => '2026-08-17'],
            ['period' => [new UtcDateOrTimezoneAwareInstant]],
        )->passes())->toBeTrue();
});
