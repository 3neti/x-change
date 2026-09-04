<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;

it('commissions maker and checker onboarding invitations from the package manifest idempotently', function (): void {
    provisionTestSystemPrincipalForCommissioning();

    $this->artisan('x-change:commission:manifest', [
        '--manifest' => 'x-change://commissioning/manifests/x-payout.default.yaml',
    ])
        ->expectsOutputToContain('Commissioning invitation Pay Codes are ready.')
        ->assertSuccessful();

    $this->artisan('x-change:commission:manifest', [
        '--manifest' => 'x-change://commissioning/manifests/x-payout.default.yaml',
    ])->assertSuccessful();

    $vouchers = Voucher::query()->get();
    $roles = $vouchers
        ->map(fn (Voucher $voucher): mixed => data_get(
            $voucher->metadata,
            'instructions.metadata.custom.x_payout_commissioning.role',
        ))
        ->filter()
        ->sort()
        ->values();

    expect($vouchers)->toHaveCount(2)
        ->and($roles->all())->toBe(['checker', 'maker'])
        ->and($vouchers->every(fn (Voucher $voucher): bool => $voucher->redeemed_at === null))->toBeTrue()
        ->and($vouchers->every(fn (Voucher $voucher): bool => data_get($voucher->metadata, 'instructions.onboarding') === true))->toBeTrue()
        ->and($vouchers->every(fn (Voucher $voucher): bool => data_get($voucher->metadata, 'instructions.metadata.flow_type') === 'disbursable'))->toBeTrue()
        ->and($vouchers->every(fn (Voucher $voucher): bool => data_get($voucher->metadata, 'instructions.execution.driver') === OnboardingVoucherInstructionPolicy::ExecutionDriver))->toBeTrue();

    $vouchers->each(function (Voucher $voucher): void {
        expect(route('x-change.claim.show', ['code' => $voucher->code]))
            ->toContain('/x/claim/'.(string) $voucher->code);
    });
});

