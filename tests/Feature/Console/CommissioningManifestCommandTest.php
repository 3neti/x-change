<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Console\Commands\BootstrapXChangeFromManifestCommand;
use LBHurtado\XChange\Services\Commissioning\CommissioningManifestRepository;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;

it('requires the x payout manifest to commission against netbank readiness', function (): void {
    $manifest = app(CommissioningManifestRepository::class)
        ->load('x-change://commissioning/manifests/x-payout.default.yaml');

    expect(data_get($manifest, 'deployment.profile'))->toBe('netbank')
        ->and(data_get($manifest, 'deployment.runtime_tier'))->toBe('local')
        ->and(data_get($manifest, 'bootstrap.environment.defaults.XCHANGE_DEPLOYMENT_PROFILE'))->toBe('netbank')
        ->and(data_get($manifest, 'bootstrap.environment.defaults.XCHANGE_FUNDING_NETBANK_ENABLED'))->toBeTrue()
        ->and(collect(data_get($manifest, 'bootstrap.environment.required', []))->pluck('key')->all())
        ->toContain(
            'NETBANK_DISBURSEMENT_ENDPOINT',
            'NETBANK_TOKEN_ENDPOINT',
            'NETBANK_QR_ENDPOINT',
            'NETBANK_STATUS_ENDPOINT',
            'NETBANK_BALANCE_ENDPOINT',
            'NETBANK_CLIENT_ID',
            'NETBANK_CLIENT_SECRET',
            'NETBANK_CLIENT_ALIAS',
            'NETBANK_SOURCE_ACCOUNT_NUMBER',
            'NETBANK_SENDER_CUSTOMER_ID',
        );
});

it('keeps bootstrap strict while allowing interactive credential capture', function (): void {
    $source = file_get_contents((new ReflectionClass(BootstrapXChangeFromManifestCommand::class))->getFileName());

    expect($source)
        ->toContain('LocalEnvironmentFileWriter')
        ->toContain('$this->input->isInteractive()')
        ->toContain('$this->secret($prompt)')
        ->toContain("'config:clear'")
        ->toContain("'x-change:doctor', '--pre-install', '--strict'")
        ->toContain("'x-change:doctor', '--strict'")
        ->toContain("'--profile='.\$profile");
});

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
