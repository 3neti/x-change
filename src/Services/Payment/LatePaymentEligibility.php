<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Commercial\PayCodeCommercialSaleService;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;

final readonly class LatePaymentEligibility
{
    public function __construct(
        private PayCodeCommercialSaleService $sales,
        private VoucherCollectionProgressService $progress,
    ) {}

    public function canApply(PaymentAttempt $attempt, Voucher $voucher, ProviderFundingObservation $observation): bool
    {
        if ($voucher->isExpired() || $voucher->isClosed() || $voucher->isCancelled()
            || (in_array($voucher->voucher_type, [VoucherType::PAYABLE, VoucherType::SETTLEMENT], true)
                && ! $voucher->canAcceptPayment())) {
            return false;
        }

        if (VoucherCollection::query()->where('provider', $attempt->provider_code)
            ->where('provider_transaction_id', $observation->provider_transaction_id)->exists()) {
            return false;
        }

        $principalMinor = $attempt->expected_amount_minor - $this->sales->outstandingCollectionFee($voucher);

        return $principalMinor > 0 && $principalMinor <= $this->progress->compute($voucher)->remaining_to_collect_minor;
    }
}
