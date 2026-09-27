<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Legal;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Actions\Funding\RefreshFundingLiquidity;
use LBHurtado\XChange\Models\AgreementAcceptance;
use LBHurtado\XChange\Models\DeferredOnboardingFunding;
use LBHurtado\XChange\Models\VoucherClaim;
use Throwable;

final readonly class ReleaseDeferredOnboardingFunding
{
    public function __construct(
        private DispatchVoucherClaimOutcome $claimOutcomes,
        private RefreshFundingLiquidity $liquidity,
    ) {}

    public function handle(Authenticatable&Model $subject, AgreementAcceptance $acceptance): int
    {
        $released = 0;
        $deferredFundings = DeferredOnboardingFunding::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', (string) $subject->getKey())
            ->where('status', 'pending_agreement')
            ->lockForUpdate()
            ->get();

        foreach ($deferredFundings as $deferred) {
            $voucher = Voucher::query()->findOrFail($deferred->voucher_id);
            $claim = $this->claimOutcomes->handle(
                voucher: $voucher,
                requestedOutcome: 'account_funding',
                payload: [],
                claimant: $subject,
                allowDeferredAgreementRelease: true,
            );

            if (! $claim instanceof VoucherClaim || $claim->status !== 'succeeded') {
                throw new \RuntimeException('The deferred onboarding funding release did not complete.');
            }

            $deferred->forceFill([
                'status' => 'released',
                'agreement_acceptance_id' => $acceptance->getKey(),
                'voucher_claim_id' => $claim->getKey(),
                'treasury_operation_reference' => $claim->treasury_operation_reference,
                'released_at' => now(),
            ])->save();
            $released++;
        }

        if ($released > 0) {
            DB::afterCommit(function () use ($subject): void {
                try {
                    $this->liquidity->handle($subject);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        }

        return $released;
    }
}
