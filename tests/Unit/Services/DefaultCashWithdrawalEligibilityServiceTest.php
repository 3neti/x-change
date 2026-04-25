<?php

declare(strict_types=1);

use LBHurtado\Cash\Contracts\WithdrawableInstrumentContract;
use LBHurtado\Cash\Services\DefaultCashWithdrawalEligibilityService;

function withdrawalEligibilityService(): DefaultCashWithdrawalEligibilityService
{
    return new DefaultCashWithdrawalEligibilityService;
}

function fakeEligibilityWithdrawableInstrument(array $overrides = []): WithdrawableInstrumentContract
{
    return new class($overrides) implements WithdrawableInstrumentContract
    {
        public function __construct(private array $overrides = []) {}

        public function isWithdrawable(): bool
        {
            return $this->overrides['isWithdrawable'] ?? true;
        }

        public function isDivisible(): bool
        {
            return $this->overrides['isDivisible'] ?? true;
        }

        public function getSliceMode(): ?string
        {
            return $this->overrides['sliceMode'] ?? 'open';
        }

        public function getSliceAmount(): ?float
        {
            return $this->overrides['sliceAmount'] ?? null;
        }

        public function getRemainingBalance(): float
        {
            return $this->overrides['remainingBalance'] ?? 100.0;
        }

        public function getMinWithdrawal(): ?float
        {
            return $this->overrides['minWithdrawal'] ?? 10.0;
        }

        public function getMaxSlices(): ?int
        {
            return array_key_exists('maxSlices', $this->overrides)
                ? $this->overrides['maxSlices']
                : 3;
        }

        public function getConsumedSlices(): int
        {
            return $this->overrides['consumedSlices'] ?? 0;
        }

        public function isExpired(): bool
        {
            return $this->overrides['isExpired'] ?? false;
        }

        public function getInstrumentState(): string
        {
            return $this->overrides['state'] ?? 'active';
        }

        public function getInstrumentId(): string|int|null
        {
            return $this->overrides['id'] ?? 1;
        }

        public function getOriginalClaimantId(): string|int|null
        {
            return $this->overrides['originalClaimantId'] ?? null;
        }
    };
}

it('passes when instrument is active withdrawable and not expired', function () {
    withdrawalEligibilityService()->assertEligible(
        fakeEligibilityWithdrawableInstrument([
            'isWithdrawable' => true,
            'isExpired' => false,
            'state' => 'active',
            'isDivisible' => true,
            'maxSlices' => 3,
            'consumedSlices' => 0,
        ]),
    );

    expect(true)->toBeTrue();
});

it('fails when instrument is not withdrawable', function () {
    withdrawalEligibilityService()->assertEligible(
        fakeEligibilityWithdrawableInstrument([
            'isWithdrawable' => false,
        ]),
    );
})->throws(RuntimeException::class, 'This voucher is not withdrawable.');

it('fails when instrument is expired', function () {
    withdrawalEligibilityService()->assertEligible(
        fakeEligibilityWithdrawableInstrument([
            'isWithdrawable' => true,
            'isExpired' => true,
            'state' => 'active',
        ]),
    );
})->throws(RuntimeException::class, 'This voucher has expired.');

it('fails when instrument state is not active', function () {
    withdrawalEligibilityService()->assertEligible(
        fakeEligibilityWithdrawableInstrument([
            'isWithdrawable' => true,
            'isExpired' => false,
            'state' => 'redeemed',
        ]),
    );
})->throws(RuntimeException::class, 'This voucher is not withdrawable.');

it('fails when divisible instrument has no remaining slices', function () {
    withdrawalEligibilityService()->assertEligible(
        fakeEligibilityWithdrawableInstrument([
            'isWithdrawable' => true,
            'isExpired' => false,
            'state' => 'active',
            'isDivisible' => true,
            'maxSlices' => 2,
            'consumedSlices' => 2,
        ]),
    );
})->throws(RuntimeException::class, 'This voucher has no remaining slices.');

it('passes when max slices is null', function () {
    withdrawalEligibilityService()->assertEligible(
        fakeEligibilityWithdrawableInstrument([
            'isWithdrawable' => true,
            'isExpired' => false,
            'state' => 'active',
            'isDivisible' => true,
            'maxSlices' => null,
            'consumedSlices' => 99,
        ]),
    );

    expect(true)->toBeTrue();
});

it('passes slice exhaustion rule when instrument is not divisible', function () {
    withdrawalEligibilityService()->assertEligible(
        fakeEligibilityWithdrawableInstrument([
            'isWithdrawable' => true,
            'isExpired' => false,
            'state' => 'active',
            'isDivisible' => false,
            'maxSlices' => 1,
            'consumedSlices' => 1,
        ]),
    );

    expect(true)->toBeTrue();
});
