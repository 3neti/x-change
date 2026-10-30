<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Contracts\InstructionCapabilityContributor;
use LBHurtado\XChange\Data\Configuration\InstructionCapabilityReadinessData;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityReadinessRegistry;
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
    'v2 webhook subscriptions' => [['feedback' => ['webhooks' => [['url' => 'https://example.com/hook', 'events' => ['settlement.payment_collected']]]]]],
    'invitation' => [['onboarding' => true]],
    'settlement' => [['voucher_type' => 'settlement']],
    'stored value' => [['stored_value' => ['enabled' => true]]],
    'batch' => [['count' => 2]],
    'plain http' => [['rider' => ['url' => 'http://example.com']]],
    'credentials' => [['rider' => ['url' => 'https://user:secret@example.com']]],
    'loopback' => [['rider' => ['url' => 'https://127.0.0.1/path']]],
    'private address' => [['rider' => ['url' => 'https://10.0.0.8/path']]],
]);

it('admits a single public reusable balance only with commissioned issuance authority', function (): void {
    $payload = [
        'count' => 1,
        'voucher_type' => 'redeemable',
        'stored_value' => ['enabled' => true],
    ];

    expect(fn () => publicStoredValuePolicy(true)->assertAllowed($payload))
        ->toThrow(ValidationException::class);
    expect(fn () => publicStoredValuePolicy(false)->assertAllowed($payload, allowCommissionedStoredValue: true))
        ->toThrow(ValidationException::class);

    publicStoredValuePolicy(true)->assertAllowed($payload, allowCommissionedStoredValue: true);

    expect(true)->toBeTrue();
});

function publicStoredValuePolicy(bool $ready): PublicIssuanceInstructionPolicy
{
    $contributor = new class($ready) implements InstructionCapabilityContributor
    {
        public function __construct(private readonly bool $ready) {}

        public function instructionCapabilities(): iterable
        {
            yield new InstructionCapabilityReadinessData(
                key: 'stored_value',
                label: 'Reusable Balance',
                status: $this->ready ? 'ready' : 'unavailable',
                issuanceAllowed: $this->ready,
                claimRetryable: false,
            );
        }
    };

    return new PublicIssuanceInstructionPolicy(new InstructionCapabilityReadinessRegistry([$contributor]));
}
