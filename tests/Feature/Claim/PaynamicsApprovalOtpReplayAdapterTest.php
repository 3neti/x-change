<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use LBHurtado\XChange\Support\Claim\ClaimApprovalPendingOtpStore;
use LBHurtado\XChange\Support\Claim\UseDeferredPaynamicsOtpResolver;

it('replays Paynamics payout with submitted approval OTP through deferred resolver', function () {
    app(ClaimApprovalPendingOtpStore::class)
        ->putSubmittedOtp('TEST-Z3EL-09173011987-S1', '441498');

    $capturedOtp = null;

    $provider = new class($capturedOtp) implements PayoutProvider
    {
        public function __construct(
            private mixed &$capturedOtp,
        ) {}

        public function disburse(PayoutRequestData $request): PayoutResultData
        {
            $otp = app(ConstellationOtpResolver::class)->resolve([
                'request_id' => $request->reference,
                'bank_account_no' => $request->account_number,
                'bank_id' => $request->bank_code,
                'amount' => number_format((float) $request->amount, 2, '.', ''),
                'reason' => 'Voucher payout '.$request->reference,
            ]);

            $this->capturedOtp = $otp;

            return new PayoutResultData(
                transaction_id: $request->reference,
                uuid: 'test-uuid',
                status: PayoutStatus::COMPLETED,
                provider: 'paynamics',
                metadata: [
                    'otp' => $otp,
                ],
            );
        }

        public function checkStatus(string $transactionId): PayoutResultData
        {
            return new PayoutResultData(
                transaction_id: $transactionId,
                uuid: 'test-uuid',
                status: PayoutStatus::COMPLETED,
                provider: 'paynamics',
                metadata: [],
            );
        }

        public function getRailFee(SettlementRail $rail): int
        {
            return 0;
        }
    };

    app()->instance(PayoutProvider::class, $provider);

    app(UseDeferredPaynamicsOtpResolver::class)->run(function () {
        $request = new PayoutRequestData(
            reference: 'TEST-Z3EL-09173011987-S1',
            amount: 75.00,
            account_number: '09173011987',
            bank_code: 'GXI',
            settlement_rail: 'INSTAPAY',
            currency: 'PHP',
            mobile: '639171234567',
        );

        app(PayoutProvider::class)->disburse($request);
    });

    expect($capturedOtp)->toBe('441498');
});
