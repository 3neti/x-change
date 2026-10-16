<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use LBHurtado\XChange\Actions\Checkout\OpenCheckoutRefundHold;
use LBHurtado\XChange\Actions\Checkout\ReleaseCheckoutRefundHold;
use LBHurtado\XChange\Models\Checkout;
use Throwable;

final readonly class CheckoutRefundFoundationScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private OpenCheckoutRefundHold $openHold,
        private ReleaseCheckoutRefundHold $releaseHold,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $checkoutId = data_get($context->scenario, 'checkout_id')
            ?: data_get($context->scenario, '_runtime.checkout_id')
            ?: env('XCHANGE_LIFECYCLE_REFUND_CHECKOUT_ID');

        if (! is_scalar($checkoutId) || trim((string) $checkoutId) === '') {
            return new ScenarioRunResult(Command::FAILURE, [
                'success' => false,
                'scenario' => $context->scenarioKey,
                'mode' => 'checkout_refund_foundation',
                'message' => 'Set XCHANGE_LIFECYCLE_REFUND_CHECKOUT_ID to a settled checkout before running this scenario.',
            ]);
        }

        try {
            $checkout = Checkout::query()->findOrFail((string) $checkoutId);
            $amountMinor = max(1, (int) (data_get($context->scenario, '_runtime.amount') ?: data_get($context->scenario, 'amount_minor', 1_000)));
            $case = $this->openHold->handle($checkout, $amountMinor, 'Lifecycle refund foundation verification.');
            $released = null;

            if ((bool) data_get($context->scenario, 'release', false)) {
                $case->forceFill(['hold_until' => Carbon::now()->subSecond()])->saveQuietly();
                $released = $this->releaseHold->handle($case->refresh());
            }

            return new ScenarioRunResult(Command::SUCCESS, [
                'success' => true,
                'scenario' => $context->scenarioKey,
                'mode' => 'checkout_refund_foundation',
                'checkout_id' => $checkout->getKey(),
                'refund_case_id' => $case->getKey(),
                'status' => $released?->status ?? $case->status,
                'amount_minor' => $amountMinor,
                'automatic_issuer_enabled' => (bool) config('x-change.checkout_refunds.pay_code_issuer_enabled', false),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return new ScenarioRunResult(Command::FAILURE, [
                'success' => false,
                'scenario' => $context->scenarioKey,
                'mode' => 'checkout_refund_foundation',
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
