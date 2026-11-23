<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\Funding\TransitionFundingIntent;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Data\Funding\FundingIntentTransitionData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Banking\AssignedFundingTreasuryConnection;
use LBHurtado\XChange\Services\Funding\FreshFundingBoundary;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use LogicException;

final readonly class CreditAssignedPaymentFallback
{
    public function __construct(
        private WalletAccessContract $wallets,
        private AssignedFundingTreasuryConnection $connections,
        private CreateFundingIntent $create,
        private TransitionFundingIntent $transition,
        private SettleVerifiedFundingIntent $settle,
        private FreshFundingBoundary $boundary,
    ) {}

    public function handle(PaymentAttempt $attempt, ProviderFundingObservation $observation, string $reason = 'amount_mismatch'): FundingIntent
    {
        return DB::transaction(function () use ($attempt, $observation, $reason): FundingIntent {
            if (! in_array($reason, ['amount_mismatch', 'recipient_unavailable'], true)) {
                throw new LogicException('The fallback reason is invalid.');
            }
            $attempt = PaymentAttempt::query()->with('voucher.owner')->lockForUpdate()->findOrFail($attempt->getKey());
            $observation = ProviderFundingObservation::query()->lockForUpdate()->findOrFail($observation->getKey());
            $snapshot = $attempt->destination_snapshot_ciphertext;
            $owner = $attempt->voucher->owner;
            $address = $attempt->funding_address_ciphertext;
            if ($attempt->provider_code !== 'netbank' || $attempt->currency !== 'PHP'
                || ! $owner instanceof Model || ! is_array($snapshot)
                || data_get($snapshot, 'mode') !== 'dedicated'
                || ! is_string($address) || $address === ''
                || ! DB::table('xchange_funding_vca_reservations')->where('payment_attempt_id', $attempt->getKey())
                    ->where('address_hash', hash('sha256', $address))->exists()
                || $observation->provider_code !== $attempt->provider_code || $observation->currency !== $attempt->currency
                || $observation->provider_status !== 'settled' || $observation->settled_at === null
                || trim((string) $observation->provider_transaction_id) === ''
                || $observation->gross_amount_minor <= 0 || $observation->net_amount_minor <= 0
                || $observation->net_amount_minor > $observation->gross_amount_minor
                || data_get($observation->metadata, 'destination_verified') !== true
                || $observation->funding_address !== 'sha256:'.hash('sha256', $address)
                || $attempt->instructions_created_at === null || $attempt->expires_at === null
                || $observation->occurred_at === null
                || $observation->occurred_at->lessThan($attempt->instructions_created_at)
                || $observation->occurred_at->greaterThan($attempt->expires_at)) {
                throw new LogicException('The assigned-account payment is not eligible for automatic fallback.');
            }
            $this->boundary->assertPaymentAttemptEligible($attempt);
            $this->connections->resolve($owner, $attempt->provider_code, $attempt->currency, $snapshot);
            $wallet = $this->wallets->resolveForUser($owner);
            if (! is_string($wallet->uuid ?? null) || $wallet->uuid === '') {
                throw new LogicException('The assigned-account owner wallet is unavailable.');
            }
            $intent = $this->create->handle(new CreateFundingIntentData(
                accountReference: 'wallet:'.$wallet->uuid,
                provider: $attempt->provider_code,
                expectedAmountMinor: $observation->gross_amount_minor,
                currency: $attempt->currency,
                idempotencyKey: 'assigned-payment-fallback:'.$attempt->getKey().':'.hash('sha256', $observation->provider_transaction_id),
                actorType: $owner->getMorphClass(), actorId: (string) $owner->getKey(),
                metadata: ['source' => 'assigned_payment_fallback', 'payment_attempt_reference' => $attempt->reference, 'fallback_reason' => $reason],
                destination: FundingDestinationSnapshot::toData($snapshot),
            ));
            $this->boundary->assertEligible($intent);
            if ($intent->status === FundingIntentStatus::Settled) {
                return $intent;
            }
            if ($intent->status !== FundingIntentStatus::PendingInstructions) {
                throw new LogicException('The fallback credit requires review.');
            }
            foreach ([FundingIntentStatus::AwaitingFunds, FundingIntentStatus::Verifying, FundingIntentStatus::Verified] as $status) {
                $intent = $this->transition->handle($intent, new FundingIntentTransitionData(
                    status: $status, eventType: 'assigned_payment_fallback_'.$status->value,
                    actorType: 'system', actorId: 'payment-verifier', expectedVersion: $intent->version,
                    evidenceReference: 'provider-observation:'.$observation->getKey(),
                    providerObservationId: $observation->getKey(), providerTransactionId: $observation->provider_transaction_id,
                    metadata: ['payment_attempt_reference' => $attempt->reference],
                ));
            }
            $this->settle->handle($intent);

            return $intent->fresh();
        }, 5);
    }
}
