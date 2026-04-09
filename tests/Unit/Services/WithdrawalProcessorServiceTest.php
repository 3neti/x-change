<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Services\DefaultWithdrawalProcessorService;

it('resolves amount for fixed-slice vouchers', function () {
    $gateway = Mockery::mock(\LBHurtado\EmiCore\Contracts\PayoutProvider::class);
    $bankRegistry = Mockery::mock(\LBHurtado\MoneyIssuer\Support\BankRegistry::class);
    $reconciliations = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $statusResolver = Mockery::mock(DisbursementStatusResolverContract::class);

    $service = new class($gateway, $bankRegistry, $reconciliations, $statusResolver) extends DefaultWithdrawalProcessorService
    {
        public function exposeResolveAmount(\LBHurtado\Voucher\Models\Voucher $voucher, ?float $amount): float
        {
            return $this->resolveAmount($voucher, $amount);
        }
    };

    $voucher = Mockery::mock(\LBHurtado\Voucher\Models\Voucher::class);
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('fixed');
    $voucher->shouldReceive('getSliceAmount')->once()->andReturn(250.00);

    $result = $service->exposeResolveAmount($voucher, null);

    expect($result)->toBe(250.00);
});

it('resolves amount for open-slice vouchers within remaining balance', function () {
    $gateway = Mockery::mock(\LBHurtado\EmiCore\Contracts\PayoutProvider::class);
    $bankRegistry = Mockery::mock(\LBHurtado\MoneyIssuer\Support\BankRegistry::class);
    $reconciliations = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $statusResolver = Mockery::mock(DisbursementStatusResolverContract::class);

    $service = new class($gateway, $bankRegistry, $reconciliations, $statusResolver) extends DefaultWithdrawalProcessorService
    {
        public function exposeResolveAmount(\LBHurtado\Voucher\Models\Voucher $voucher, ?float $amount): float
        {
            return $this->resolveAmount($voucher, $amount);
        }
    };

    $voucher = Mockery::mock(\LBHurtado\Voucher\Models\Voucher::class);
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('getMinWithdrawal')->once()->andReturn(100.00);
    $voucher->shouldReceive('getRemainingBalance')->once()->andReturn(500.00);

    $result = $service->exposeResolveAmount($voucher, 200.00);

    expect($result)->toBe(200.00);
});

it('fails when open-slice amount exceeds remaining balance', function () {
    $gateway = Mockery::mock(\LBHurtado\EmiCore\Contracts\PayoutProvider::class);
    $bankRegistry = Mockery::mock(\LBHurtado\MoneyIssuer\Support\BankRegistry::class);
    $reconciliations = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $statusResolver = Mockery::mock(DisbursementStatusResolverContract::class);

    $service = new class($gateway, $bankRegistry, $reconciliations, $statusResolver) extends DefaultWithdrawalProcessorService
    {
        public function exposeResolveAmount(\LBHurtado\Voucher\Models\Voucher $voucher, ?float $amount): float
        {
            return $this->resolveAmount($voucher, $amount);
        }
    };

    $voucher = Mockery::mock(\LBHurtado\Voucher\Models\Voucher::class);
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('getMinWithdrawal')->once()->andReturn(100.00);
    $voucher->shouldReceive('getRemainingBalance')->once()->andReturn(150.00);

    expect(fn () => $service->exposeResolveAmount($voucher, 200.00))
        ->toThrow(InvalidArgumentException::class, 'exceeds remaining balance');
});
