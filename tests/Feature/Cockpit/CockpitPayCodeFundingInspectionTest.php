<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\Funding\PayCodeFundingInspectionStore;
use LBHurtado\XChange\Tests\Fakes\User;

it('fails closed for legacy payout-only Pay Codes', function () {
    $issuer = actingAsTestUser();
    $claimant = User::query()->create([
        'name' => 'Funding Claimant',
        'email' => 'funding-claimant@example.test',
        'password' => 'password',
    ]);
    $voucher = payCodeFundingInspectionVoucher($issuer, [
        'destinations' => ['provider_payout'],
    ]);

    $this->actingAs($claimant)
        ->post(route(
            'x-change.cockpit.funding.pay-code-inspections.store',
        ), ['code' => mb_strtolower($voucher->code)])
        ->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHas(
            'pay_code_funding_preview.status',
            'payout_only',
        )
        ->assertSessionMissing(
            'pay_code_funding_preview.inspection_token',
        );
});

it('returns a sanitized short-lived preview for an eligible Pay Code', function () {
    $issuer = actingAsTestUser();
    $claimant = User::query()->create([
        'name' => 'Funding Claimant',
        'email' => 'funding-preview@example.test',
        'password' => 'password',
    ]);
    $voucher = payCodeFundingInspectionVoucher($issuer);

    $response = $this->actingAs($claimant)
        ->post(route(
            'x-change.cockpit.funding.pay-code-inspections.store',
        ), ['code' => '  '.mb_strtolower($voucher->code).'  '])
        ->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHas(
            'pay_code_funding_preview.eligible',
            true,
        )
        ->assertSessionHas(
            'pay_code_funding_preview.amount',
            '₱125.00',
        )
        ->assertSessionHas(
            'pay_code_funding_preview.provider_calls',
            false,
        )
        ->assertSessionMissing(
            'pay_code_funding_preview.connection_reference',
        )
        ->assertSessionMissing(
            'pay_code_funding_preview.reservation_operation_reference',
        );
    $token = $response->getSession()->get(
        'pay_code_funding_preview.inspection_token',
    );

    expect($token)->toBeString()
        ->and($token)->not->toContain($voucher->code)
        ->and(app(PayCodeFundingInspectionStore::class)->resolve(
            $token,
            $claimant,
        )?->is($voucher))->toBeTrue();
});

it('rejects expired and commercially incompatible Pay Codes without issuing a token', function (
    array $overrides,
    string $status,
) {
    $issuer = actingAsTestUser();
    $claimant = User::query()->create([
        'name' => 'Funding Claimant',
        'email' => uniqid('funding-blocked-', true).'@example.test',
        'password' => 'password',
    ]);
    $voucher = payCodeFundingInspectionVoucher($issuer, $overrides);

    $this->actingAs($claimant)
        ->post(route(
            'x-change.cockpit.funding.pay-code-inspections.store',
        ), ['code' => $voucher->code])
        ->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHas('pay_code_funding_preview.status', $status)
        ->assertSessionMissing(
            'pay_code_funding_preview.inspection_token',
        );
})->with([
    'expired' => [
        ['expires_at' => now()->subMinute()],
        'not_claimable',
    ],
    'provider cost present' => [
        ['provider_cost_minor' => 1_000],
        'commercial_profile_unavailable',
    ],
    'missing reserve' => [
        ['reservation' => null],
        'reserve_unavailable',
    ],
]);

/**
 * @param  array<string, mixed>  $overrides
 */
function payCodeFundingInspectionVoucher(
    User $issuer,
    array $overrides = [],
): Voucher {
    $amountMinor = 12_500;
    $reservation = array_key_exists('reservation', $overrides)
        ? $overrides['reservation']
        : [
            'status' => 'reserved',
            'connection_reference' => 'netbank-primary',
            'operation_reference' => 'pay-code-position-reservation:inspection',
            'amount_minor' => $amountMinor,
            'currency' => 'PHP',
        ];

    return Voucher::query()->forceCreate([
        'code' => 'FUND-'.mb_strtoupper(substr(hash('sha256', uniqid('', true)), 0, 6)),
        'owner_type' => $issuer::class,
        'owner_id' => $issuer->getKey(),
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => $amountMinor / 100,
                    'currency' => 'PHP',
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
                'metadata' => [
                    'custom' => [
                        'settlement' => [
                            'destinations' => $overrides['destinations']
                                ?? ['provider_payout', 'account_funding'],
                            'account_funding' => [
                                'pricing_profile' => 'account-funding-v1',
                                'provider_cost_minor' => $overrides['provider_cost_minor']
                                    ?? 0,
                                'provider_calls' => false,
                            ],
                        ],
                    ],
                ],
            ],
            'treasury' => [
                'account_funding' => [
                    'status' => 'ready',
                    'destinations' => $overrides['destinations']
                        ?? ['provider_payout', 'account_funding'],
                    'pricing_profile' => 'account-funding-v1',
                    'provider_cost_minor' => $overrides['provider_cost_minor']
                        ?? 0,
                    'provider_calls' => false,
                ],
                'pay_code_reservation' => $reservation,
            ],
        ],
        'state' => 'active',
        'expires_at' => $overrides['expires_at'] ?? now()->addHour(),
    ]);
}
