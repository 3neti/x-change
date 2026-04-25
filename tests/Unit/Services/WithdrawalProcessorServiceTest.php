<?php

declare(strict_types=1);

use LBHurtado\Cash\Services\DefaultCashClaimantAuthorizationService;
use LBHurtado\Cash\Services\DefaultCashWithdrawalAmountResolverService;
use LBHurtado\Cash\Services\DefaultCashWithdrawalEligibilityService;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Services\DefaultWithdrawalProcessorService;
use LBHurtado\XChange\Services\WithdrawalBankAccountResolver;
use LBHurtado\XChange\Services\WithdrawalDisbursementExecutor;
use LBHurtado\XChange\Services\WithdrawalExecutionContextResolver;
use LBHurtado\XChange\Services\WithdrawalPayoutRequestFactory;
use LBHurtado\XChange\Services\WithdrawalPendingDisbursementRecorder;
use LBHurtado\XChange\Services\WithdrawalRailGuard;
use LBHurtado\XChange\Services\WithdrawalResultFactory;
use LBHurtado\XChange\Services\WithdrawalWalletSettlementService;

function withdrawalProcessorForAmountResolution(): DefaultWithdrawalProcessorService
{
    $gateway = Mockery::mock(PayoutProvider::class);
    $reconciliations = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $statusResolver = Mockery::mock(DisbursementStatusResolverContract::class);

    return new class(Mockery::mock(BankRegistry::class), new DefaultCashWithdrawalAmountResolverService, new DefaultCashClaimantAuthorizationService, new DefaultCashWithdrawalEligibilityService, new WithdrawalExecutionContextResolver, new WithdrawalBankAccountResolver, new WithdrawalPayoutRequestFactory, new WithdrawalRailGuard(Mockery::mock(BankRegistry::class)), new WithdrawalDisbursementExecutor(gateway: $gateway, reconciliations: $reconciliations, statusResolver: $statusResolver), new WithdrawalWalletSettlementService, new WithdrawalResultFactory, new WithdrawalPendingDisbursementRecorder(Mockery::mock(BankRegistry::class))) extends DefaultWithdrawalProcessorService
    {
        public function exposeResolveAmount(Voucher $voucher, ?float $amount): float
        {
            return $this->resolveAmount($voucher, $amount);
        }
    };
}

it('resolves amount for fixed-slice vouchers', function () {
    $service = withdrawalProcessorForAmountResolution();
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('fixed');
    $voucher->shouldReceive('getSliceAmount')->once()->andReturn(250.00);

    $result = $service->exposeResolveAmount($voucher, null);

    expect($result)->toBe(250.00);
});

it('resolves amount for open-slice vouchers within remaining balance', function () {
    $service = withdrawalProcessorForAmountResolution();
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('getMinWithdrawal')->once()->andReturn(100.00);
    $voucher->shouldReceive('getRemainingBalance')->once()->andReturn(500.00);

    $result = $service->exposeResolveAmount($voucher, 200.00);

    expect($result)->toBe(200.00);
});

it('fails when open-slice amount exceeds remaining balance', function () {
    $service = withdrawalProcessorForAmountResolution();
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('getMinWithdrawal')->once()->andReturn(100.00);
    $voucher->shouldReceive('getRemainingBalance')->once()->andReturn(150.00);

    expect(fn () => $service->exposeResolveAmount($voucher, 200.00))
        ->toThrow(InvalidArgumentException::class, 'exceeds remaining balance');
});
