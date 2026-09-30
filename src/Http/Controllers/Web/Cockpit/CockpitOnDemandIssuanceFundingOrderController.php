<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryHoldOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldReleaseData;
use LBHurtado\XChange\Actions\Funding\ExpireOnDemandIssuanceFundingOrder;
use LBHurtado\XChange\Actions\Funding\TransitionPayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingIntentJob;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CockpitOnDemandIssuanceFundingOrderController extends Controller
{
    public function show(
        Request $request,
        PayCodeIssuanceFundingOrder $order,
        OnDemandIssuanceFundingOrderPresenter $presenter,
    ): JsonResponse {
        $this->authorizeOwner($request, $order);

        return response()->json($presenter->present($order));
    }

    public function acknowledge(
        Request $request,
        PayCodeIssuanceFundingOrder $order,
        TransitionPayCodeIssuanceFundingOrder $transition,
        OnDemandIssuanceFundingOrderPresenter $presenter,
    ): JsonResponse {
        $this->authorizeOwner($request, $order);
        $intent = $order->fundingIntent;

        if ($intent === null
            || $intent->status !== FundingIntentStatus::AwaitingFunds
            || $order->expires_at->isPast()) {
            throw new ConflictHttpException('This funding order is not eligible for a payment check.');
        }

        if ($order->status === PayCodeIssuanceFundingOrderStatus::AwaitingPayment) {
            $order = $transition->handle(
                order: $order,
                status: PayCodeIssuanceFundingOrderStatus::PayerAcknowledged,
                eventType: 'payer_acknowledged',
                actorType: $request->user()::class,
                actorId: (string) $request->user()->getAuthIdentifier(),
                attributes: ['payer_acknowledged_at' => now()],
            );
        }

        VerifyFundingIntentJob::dispatch(
            fundingIntentId: $intent->getKey(),
            providerCode: $intent->provider_code,
            trigger: FundingVerificationTrigger::Operator,
            actorId: (string) $request->user()->getAuthIdentifier(),
        )->afterCommit();

        return response()->json($presenter->present($order), 202);
    }

    public function verifyAutomatically(
        Request $request,
        PayCodeIssuanceFundingOrder $order,
        ExpireOnDemandIssuanceFundingOrder $expire,
        OnDemandIssuanceFundingOrderPresenter $presenter,
    ): JsonResponse {
        $this->authorizeOwner($request, $order);

        if ($order->expires_at->isPast()) {
            $order = $expire->handle($order);

            return response()->json($presenter->present($order));
        }

        $intent = $order->fundingIntent;

        if ($intent === null
            || ! in_array($order->status, [
                PayCodeIssuanceFundingOrderStatus::AwaitingPayment,
                PayCodeIssuanceFundingOrderStatus::PayerAcknowledged,
                PayCodeIssuanceFundingOrderStatus::Verifying,
                PayCodeIssuanceFundingOrderStatus::Underfunded,
                PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous,
            ], true)
            || ! in_array($intent->status, [
                FundingIntentStatus::AwaitingFunds,
                FundingIntentStatus::EvidenceReceived,
                FundingIntentStatus::Verifying,
                FundingIntentStatus::Verified,
            ], true)) {
            return response()->json($presenter->present($order));
        }

        $intervalSeconds = max(
            5,
            (int) config(
                'x-change.issuance_funding.on_demand.automatic_verification.interval_seconds',
                10,
            ),
        );
        $dispatchKey = 'x-change:on-demand-funding:auto-verify:'.$intent->getKey();

        if (Cache::add($dispatchKey, true, now()->addSeconds($intervalSeconds))) {
            VerifyFundingIntentJob::dispatch(
                fundingIntentId: $intent->getKey(),
                providerCode: $intent->provider_code,
                trigger: FundingVerificationTrigger::Schedule,
                actorId: 'funding-modal-monitor',
            )->afterCommit();
        }

        return response()->json($presenter->present($order), 202);
    }

    public function cancel(
        Request $request,
        PayCodeIssuanceFundingOrder $order,
        TransitionPayCodeIssuanceFundingOrder $transition,
        TreasuryHoldOperationContract $holds,
        OnDemandIssuanceFundingOrderPresenter $presenter,
    ): JsonResponse {
        $this->authorizeOwner($request, $order);

        if (! in_array($order->status, [
            PayCodeIssuanceFundingOrderStatus::AwaitingPayment,
            PayCodeIssuanceFundingOrderStatus::PayerAcknowledged,
            PayCodeIssuanceFundingOrderStatus::Underfunded,
            PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous,
            PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
        ], true)) {
            throw new ConflictHttpException('This funding order can no longer be cancelled.');
        }

        if ($order->treasury_hold_reference !== null) {
            $holds->release(new TreasuryHoldReleaseData(
                operationReference: 'issuance-hold-release:'.$order->reference,
                holdReference: $order->treasury_hold_reference,
                currency: $order->currency,
                idempotencyKey: 'issuance-hold-release-key:'.$order->reference,
                externalReference: 'issuance-funding-order:'.$order->reference,
                metadata: ['reason' => 'operator_cancelled'],
            ));
        }

        $order = $transition->handle(
            order: $order,
            status: PayCodeIssuanceFundingOrderStatus::Cancelled,
            eventType: 'cancelled',
            actorType: $request->user()::class,
            actorId: (string) $request->user()->getAuthIdentifier(),
            attributes: ['cancelled_at' => now()],
        );

        return response()->json($presenter->present($order));
    }

    private function authorizeOwner(Request $request, PayCodeIssuanceFundingOrder $order): void
    {
        $user = $request->user();

        if ($user === null
            || $order->issuer_type !== $user::class
            || $order->issuer_id !== (string) $user->getAuthIdentifier()) {
            throw new NotFoundHttpException;
        }
    }
}
