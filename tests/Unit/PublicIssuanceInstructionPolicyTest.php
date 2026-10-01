<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceInstructionPolicy;

it('accepts the public safe instruction subset', function (): void {
    app(PublicIssuanceInstructionPolicy::class)->assertAllowed([
        'count' => 1,
        'voucher_type' => 'redeemable',
        'feedback' => ['email' => 'guest@example.com', 'webhook' => null],
        'rider' => ['url' => 'https://example.com/thank-you'],
    ]);

    expect(true)->toBeTrue();
});

it('rejects privileged modes and unsafe destinations', function (array $payload): void {
    expect(fn () => app(PublicIssuanceInstructionPolicy::class)->assertAllowed($payload))
        ->toThrow(ValidationException::class);
})->with([
    'webhook' => [['feedback' => ['webhook' => 'https://example.com/hook']]],
    'invitation' => [['onboarding' => true]],
    'settlement' => [['voucher_type' => 'settlement']],
    'stored value' => [['stored_value' => ['enabled' => true]]],
    'batch' => [['count' => 2]],
    'plain http' => [['rider' => ['url' => 'http://example.com']]],
    'credentials' => [['rider' => ['url' => 'https://user:secret@example.com']]],
    'loopback' => [['rider' => ['url' => 'https://127.0.0.1/path']]],
    'private address' => [['rider' => ['url' => 'https://10.0.0.8/path']]],
]);
