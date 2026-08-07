<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;

it('idempotently grants named Commercial Control authority from the system principal', function (): void {
    enableNetbankTreasuryForTests();
    $operator = actingAsTestUser();
    config()->set('auth.providers.users.model', $operator::class);

    $parameters = [
        'operator' => $operator->email,
        '--column' => 'email',
        '--capability' => [
            CommercialOperatorCapability::ViewCommercialControls->value,
            CommercialOperatorCapability::ManageOfferings->value,
        ],
        '--authorization-reference' => 'delegation:commercial-maker:2026-08-07',
        '--json' => true,
    ];

    expect(Artisan::call('x-change:commercial:authorize-operator', $parameters))->toBe(0);
    $first = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect(Artisan::call('x-change:commercial:authorize-operator', $parameters))->toBe(0);
    $replay = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($first['operator'])->toBe(['column' => 'email', 'value' => $operator->email])
        ->and($first['authorizations'])->toHaveCount(2)
        ->and($replay['authorizations'][0]['created'])->toBeFalse()
        ->and(CommercialOperatorAuthorization::query()->count())->toBe(2)
        ->and(CommercialOperatorAuthorization::query()
            ->where('operator_id', $operator->getKey())
            ->pluck('capability')
            ->sort()
            ->values()
            ->all())->toBe([
                CommercialOperatorCapability::ViewCommercialControls->value,
                CommercialOperatorCapability::ManageOfferings->value,
            ]);
});

it('rejects unknown Commercial Control capabilities', function (): void {
    enableNetbankTreasuryForTests();
    $operator = actingAsTestUser();
    config()->set('auth.providers.users.model', $operator::class);

    expect(Artisan::call('x-change:commercial:authorize-operator', [
        'operator' => $operator->email,
        '--column' => 'email',
        '--capability' => ['commercial.superuser'],
        '--authorization-reference' => 'invalid-capability-test',
    ]))->toBe(1)
        ->and(CommercialOperatorAuthorization::query()->count())->toBe(0);
});
