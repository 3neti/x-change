<?php

declare(strict_types=1);

use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimStartController;
use LBHurtado\XChange\Support\Claim\PayoutDestinationRegistry;

function claimStartControllerDestinationPayload(array $field): array
{
    return [
        'steps' => [
            [
                'handler' => 'form',
                'config' => [
                    'fields' => [
                        array_merge([
                            'name' => 'bank_code',
                            'type' => 'bank_account',
                        ], $field),
                    ],
                ],
            ],
        ],
    ];
}

function applyClaimStartDestinationDefaults(array $payload): array
{
    $controller = (new ReflectionClass(ClaimStartController::class))->newInstanceWithoutConstructor();

    $destinations = new ReflectionProperty(ClaimStartController::class, 'destinations');
    $destinations->setValue($controller, app(PayoutDestinationRegistry::class));

    $method = new ReflectionMethod(ClaimStartController::class, 'applyClaimDestinationDefaults');

    return $method->invoke($controller, $payload);
}

it('replaces only the bundled GCash destination fallback with the configured default', function (): void {
    config()->set('x-change.claim.destination.default_bank_code', 'PAPHPHM1XXX');

    $payload = applyClaimStartDestinationDefaults(
        claimStartControllerDestinationPayload(['default' => 'GXCHPHM2XXX']),
    );

    expect(data_get($payload, 'steps.0.config.fields.0.default'))->toBe('PAPHPHM1XXX')
        ->and(data_get($payload, 'steps.0.config.fields.0.destination_default'))->toBeTrue();
});

it('does not overwrite an explicit voucher destination default', function (): void {
    config()->set('x-change.claim.destination.default_bank_code', 'PAPHPHM1XXX');

    $payload = applyClaimStartDestinationDefaults(
        claimStartControllerDestinationPayload(['default' => 'MYDBPHM2XXX']),
    );

    expect(data_get($payload, 'steps.0.config.fields.0.default'))->toBe('MYDBPHM2XXX')
        ->and(data_get($payload, 'steps.0.config.fields.0.destination_default'))->toBeTrue();
});
