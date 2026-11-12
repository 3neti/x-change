<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PartnerApi;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\Funding\LockIssuanceFundingAttribution;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final readonly class ReleasePartnerIssuanceReservation
{
    public function __construct(private LockIssuanceFundingAttribution $attributionLock) {}

    public function handle(int $reservationId, Model $actor, string $reason, string $evidenceReference): void
    {
        if (mb_strlen(trim($reason)) < 8 || mb_strlen(trim($reason)) > 2000
            || mb_strlen(trim($evidenceReference)) < 3 || mb_strlen(trim($evidenceReference)) > 190) {
            throw ValidationException::withMessages(['reservation' => ['A reason and provider evidence reference are required.']]);
        }
        $reservation = DB::table('x_change_partner_issuance_reservations')->where('id', $reservationId)->first();
        $order = $reservation?->funding_order_id === null ? null : PayCodeIssuanceFundingOrder::query()->find($reservation->funding_order_id);
        $intent = $order?->fundingIntent;
        if ($intent === null) {
            throw ValidationException::withMessages(['reservation' => ['A funding intent is required for review.']]);
        }

        DB::transaction(function () use ($reservationId, $actor, $reason, $evidenceReference, $intent, $order): void {
            $this->attributionLock->handle($intent->provider_code, $intent->currency);
            $lockedIntent = FundingIntent::query()->lockForUpdate()->findOrFail($intent->getKey());
            $lockedOrder = PayCodeIssuanceFundingOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            $clientId = DB::table('x_change_partner_issuance_reservations')->where('id', $reservationId)->value('partner_api_client_id');
            PartnerApiClient::query()->lockForUpdate()->findOrFail($clientId);
            $locked = DB::table('x_change_partner_issuance_reservations')->where('id', $reservationId)->lockForUpdate()->first();
            $check = DB::table('x_change_funding_reconciliation_checks')->where('funding_intent_id', $lockedIntent->getKey())->first();
            $window = max(0, (int) config('x-change.funding.issuance_late_reconciliation_days', 7));
            if ($locked === null || $locked->status !== 'pending' || (int) $locked->funding_order_id !== (int) $lockedOrder->getKey()
                || (int) $lockedOrder->funding_intent_id !== (int) $lockedIntent->getKey()
                || ! in_array($lockedOrder->status, [PayCodeIssuanceFundingOrderStatus::Expired, PayCodeIssuanceFundingOrderStatus::Cancelled], true)
                || ! in_array($lockedIntent->status, [FundingIntentStatus::Expired, FundingIntentStatus::Cancelled], true)
                || $lockedOrder->expires_at === null || $lockedOrder->expires_at->addDays($window)->isFuture()
                || $lockedOrder->voucher_id !== null || $lockedOrder->funded_at !== null || $lockedOrder->late_payment_detected_at !== null
                || $lockedIntent->matched_observation_id !== null || $lockedIntent->provider_transaction_id !== null
                || $lockedIntent->evidence_received_at !== null || $lockedIntent->settlement()->exists() || $lockedIntent->suspenseCases()->exists()
                || $check === null || $check->last_outcome !== 'not_observed'
                || CarbonImmutable::parse($check->last_checked_at)->lessThan($lockedOrder->expires_at)
                || CarbonImmutable::parse($check->last_checked_at)->lessThan(now()->subDay())) {
                throw ValidationException::withMessages(['reservation' => ['The order requires payment reconciliation before this reservation can be released.']]);
            }

            DB::table('x_change_partner_issuance_reservations')->where('id', $reservationId)->update([
                'status' => 'released', 'released_by_type' => $actor->getMorphClass(), 'released_by_id' => (string) $actor->getKey(),
                'release_reason' => trim($reason), 'release_evidence_reference' => trim($evidenceReference), 'released_at' => now(), 'updated_at' => now(),
            ]);
        }, attempts: 5);
    }
}
