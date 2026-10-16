<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Checkout;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\CheckoutRefundDisposition;
use LBHurtado\XChange\Enums\CheckoutRefundStatus;
use LBHurtado\XChange\Models\CheckoutRefundCase;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ReleaseCheckoutRefundHold
{
    public function __construct(private CheckoutLifecycle $lifecycle) {}

    public function handle(CheckoutRefundCase $refundCase, string $actor = 'scheduler'): CheckoutRefundCase
    {
        return DB::transaction(function () use ($refundCase, $actor): CheckoutRefundCase {
            $case = CheckoutRefundCase::query()->with('checkout')->lockForUpdate()->findOrFail($refundCase->getKey());

            if ($case->disposition !== CheckoutRefundDisposition::PayCode->value) {
                throw new ConflictHttpException('Only Pay Code refund holds can be released.');
            }

            if ($case->status === CheckoutRefundStatus::Eligible->value) {
                return $case;
            }

            if ($case->status !== CheckoutRefundStatus::Held->value || $case->hold_until === null || $case->hold_until->isFuture()) {
                throw new ConflictHttpException('This refund hold is not ready for release.');
            }

            $case->forceFill([
                'status' => CheckoutRefundStatus::Eligible->value,
                'eligible_at' => now(),
            ])->save();
            $this->lifecycle->event($case->checkout, 'pay_code_refund_hold_released', self::class, $actor, [
                'refund_case_id' => $case->getKey(),
            ]);

            return $case->refresh();
        }, attempts: 5);
    }
}
