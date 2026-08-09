<?php

declare(strict_types=1);

use LBHurtado\XChange\Enums\CockpitPayeeKind;
use LBHurtado\XChange\Services\Cockpit\CockpitPayeePolicy;

it('classifies Pay To values deterministically', function (
    ?string $input,
    CockpitPayeeKind $kind,
    ?string $normalized,
    bool $issuable,
): void {
    $policy = app(CockpitPayeePolicy::class)->classify($input);

    expect($policy->kind)->toBe($kind)
        ->and($policy->normalizedValue)->toBe($normalized)
        ->and($policy->issuable)->toBe($issuable);
})->with([
    'blank is open' => ['', CockpitPayeeKind::Open, null, true],
    'cash is open' => ['CASH', CockpitPayeeKind::Open, null, true],
    'local mobile' => ['09173011987', CockpitPayeeKind::Mobile, '+639173011987', true],
    'international mobile' => ['+63 917 301 1987', CockpitPayeeKind::Mobile, '+639173011987', true],
    'email is deferred' => ['person@example.com', CockpitPayeeKind::Email, 'person@example.com', false],
    'vendor alias' => ['@merchant_1', CockpitPayeeKind::Vendor, 'MERCHANT_1', true],
    'ordinary secret' => ['release-me', CockpitPayeeKind::Secret, 'release-me', true],
    'quoted mobile secret' => ['"09173011987"', CockpitPayeeKind::Secret, '09173011987', true],
    'quoted cash secret' => ['"CASH"', CockpitPayeeKind::Secret, 'CASH', true],
]);

it('fails closed for ambiguous or malformed Pay To values', function (string $input, string $message): void {
    $policy = app(CockpitPayeePolicy::class)->classify($input);

    expect($policy->kind)->toBe(CockpitPayeeKind::Invalid)
        ->and($policy->issuable)->toBeFalse()
        ->and($policy->message)->toContain($message);
})->with([
    'incomplete mobile' => ['0917301198', 'complete Philippine mobile'],
    'unclosed quote' => ['"09173011987', 'Close both double quotes'],
    'malformed email' => ['a@invalid', 'complete email address'],
    'malformed vendor' => ['@bad alias', 'Vendor aliases'],
    'short secret' => ['123', '4 to 255'],
]);
