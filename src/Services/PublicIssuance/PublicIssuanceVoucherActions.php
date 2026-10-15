<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\CheckoutRefundCase;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;

final readonly class PublicIssuanceVoucherActions
{
    public function __construct(
        private VoucherLifecycleServiceContract $lifecycle,
        private CheckoutLifecycle $checkouts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Voucher $voucher, string $action, ?int $extensionHours = null, string $reason = 'Public issuance recovery'): array
    {
        if ($action === 'request_refund') {
            return $this->requestRefund($voucher, $reason);
        }

        if (in_array($action, ['cancel', 'expire'], true)) {
            return $action === 'cancel'
                ? $this->lifecycle->cancel((string) $voucher->getKey(), ['reason' => $reason])
                : $this->lifecycle->expire((string) $voucher->getKey(), ['reason' => $reason]);
        }

        if ($extensionHours === null || ! in_array($extensionHours, [24, 168], true)) {
            throw ValidationException::withMessages(['extension_hours' => 'Choose a 24-hour or 7-day extension.']);
        }

        return DB::transaction(function () use ($voucher, $extensionHours, $reason): array {
            $locked = Voucher::query()->lockForUpdate()->findOrFail($voucher->getKey());
            if ($locked->state !== VoucherState::ACTIVE || $locked->redeemed_at !== null || $locked->closed_at !== null) {
                throw ValidationException::withMessages(['action' => 'Only an active, unclaimed Pay Code can be extended.']);
            }
            if ($locked->expires_at === null || $locked->expires_at->isPast()) {
                throw ValidationException::withMessages(['action' => 'This Pay Code has already expired.']);
            }

            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            $extensions = data_get($metadata, 'lifecycle.expiration_extensions', []);
            $extensions = is_array($extensions) ? $extensions : [];
            if (count($extensions) >= 2) {
                throw ValidationException::withMessages(['action' => 'This Pay Code has reached its extension limit.']);
            }

            $newExpiration = $locked->expires_at->addHours($extensionHours);
            $extensions[] = [
                'hours' => $extensionHours,
                'reason' => $reason,
                'before' => $locked->expires_at->toIso8601String(),
                'after' => $newExpiration->toIso8601String(),
                'occurred_at' => now()->toIso8601String(),
            ];
            data_set($metadata, 'lifecycle.expiration_extensions', $extensions);
            $locked->forceFill(['expires_at' => $newExpiration, 'metadata' => $metadata])->save();

            return [
                'voucher_id' => $locked->getKey(),
                'code' => $locked->code,
                'status' => 'extended',
                'expires_at' => $newExpiration->toIso8601String(),
                'messages' => ['Pay Code expiration extended successfully.'],
            ];
        }, attempts: 5);
    }

    /** @return array<string, mixed> */
    private function requestRefund(Voucher $voucher, string $reason): array
    {
        return DB::transaction(function () use ($voucher, $reason): array {
            $lockedVoucher = Voucher::query()->lockForUpdate()->findOrFail($voucher->getKey());
            if (! in_array($lockedVoucher->state?->value, ['expired', 'cancelled'], true) || $lockedVoucher->redeemed_at !== null) {
                throw ValidationException::withMessages(['action' => 'Only an unclaimed expired or cancelled Pay Code can request a refund.']);
            }
            $order = PayCodeIssuanceFundingOrder::query()
                ->with('fundingIntent.settlement')
                ->where('voucher_id', (string) $lockedVoucher->getKey())
                ->first();
            $checkout = $order === null ? null : Checkout::query()->where('funding_order_id', $order->getKey())->first();
            $settlement = $order?->fundingIntent?->settlement;
            if ($checkout === null || $settlement === null) {
                throw ValidationException::withMessages(['action' => 'This Pay Code is not eligible for an automatic refund request.']);
            }
            if ($checkout->refundCase()->exists()) {
                throw ValidationException::withMessages(['action' => 'A refund request already exists for this Pay Code.']);
            }

            CheckoutRefundCase::query()->create([
                'checkout_id' => $checkout->getKey(),
                'funding_settlement_id' => $settlement->getKey(),
                'status' => 'open',
                'amount_minor' => $settlement->gross_amount_minor,
                'currency' => $settlement->currency,
                'reason' => $reason,
                'opened_by' => 'public_recovery',
            ]);
            $this->checkouts->event($checkout, 'public_refund_request_opened', 'public_recovery', null, [
                'reason' => $reason,
                'voucher_code' => $lockedVoucher->code,
            ]);

            return [
                'voucher_id' => $lockedVoucher->getKey(),
                'code' => $lockedVoucher->code,
                'status' => 'refund_requested',
                'messages' => ['Refund request recorded. An operator will complete the refund.'],
            ];
        }, attempts: 5);
    }
}
