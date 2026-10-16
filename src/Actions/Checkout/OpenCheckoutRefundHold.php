<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Checkout;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\CheckoutRefundDisposition;
use LBHurtado\XChange\Enums\CheckoutRefundStatus;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\CheckoutRefundCase;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class OpenCheckoutRefundHold
{
    public function __construct(private CheckoutLifecycle $lifecycle) {}

    public function handle(
        Checkout $checkout,
        int $clientFundsAmountMinor,
        string $reason,
        string $actor = 'onboarding',
        ?CarbonInterface $holdUntil = null,
    ): CheckoutRefundCase {
        if ($clientFundsAmountMinor < 1) {
            throw new ConflictHttpException('A refund hold requires a positive client-funds amount.');
        }

        return DB::transaction(function () use ($checkout, $clientFundsAmountMinor, $reason, $actor, $holdUntil): CheckoutRefundCase {
            $locked = Checkout::query()
                ->with(['contact', 'fundingOrder.fundingIntent.settlement'])
                ->lockForUpdate()
                ->findOrFail($checkout->getKey());
            $existing = $locked->refundCase()->lockForUpdate()->first();

            if ($existing instanceof CheckoutRefundCase) {
                if ($existing->disposition === CheckoutRefundDisposition::PayCode->value) {
                    return $existing;
                }

                throw new ConflictHttpException('An external refund case already exists for this checkout.');
            }

            $settlement = $locked->fundingOrder?->fundingIntent?->settlement;

            if ($settlement === null || $settlement->currency !== ($locked->fundingOrder?->currency ?? $settlement->currency)) {
                throw new ConflictHttpException('A settled payment is required before opening a refund hold.');
            }

            $until = $holdUntil ?? now()->addSeconds((int) config('x-change.checkout_refunds.pay_code_hold_seconds', 86400));
            $recipientMobile = $locked->contact?->mobile ?: $locked->visitor_mobile_ciphertext;
            $status = is_string($recipientMobile) && trim($recipientMobile) !== ''
                ? CheckoutRefundStatus::Held->value
                : CheckoutRefundStatus::ManualRecovery->value;
            $case = CheckoutRefundCase::query()->create([
                'checkout_id' => $locked->getKey(),
                'funding_settlement_id' => $settlement->getKey(),
                'status' => $status,
                'disposition' => CheckoutRefundDisposition::PayCode->value,
                'amount_minor' => $clientFundsAmountMinor,
                'currency' => $settlement->currency,
                'reason' => $reason,
                'opened_by' => $actor,
                'hold_until' => $until,
                'recipient_contact_id' => $locked->contact?->getKey(),
                'idempotency_key_hash' => hash('sha256', 'checkout-pay-code-refund:'.$locked->getKey()),
                'metadata' => [
                    'amount_component' => 'client_funds',
                    'automatic_issuer_enabled' => false,
                    'recipient_mobile_available' => $status === CheckoutRefundStatus::Held->value,
                ],
            ]);
            $this->lifecycle->event($locked, 'pay_code_refund_hold_opened', self::class, $actor, [
                'refund_case_id' => $case->getKey(),
                'status' => $status,
                'hold_until' => $until->toIso8601String(),
                'amount_minor' => $clientFundsAmountMinor,
            ]);

            return $case;
        }, attempts: 5);
    }
}
