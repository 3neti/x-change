<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\DisbursementRejectionTrustService;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use RuntimeException;

final readonly class PlanCampaignPayoutRecoveryFallbacks
{
    public function __construct(
        private QueueCampaignFeedbackDelivery $delivery,
        private DisbursementRejectionTrustService $rejectionTrust,
    ) {}

    /** @return array{planned: int, queued: int, skipped: int} */
    public function handle(
        CampaignWorksheetAuthorization $authorization,
        Model $actor,
        int $limit = 100,
    ): array {
        if (! config('x-change.campaigns.payout_recovery.enabled')) {
            throw new RuntimeException('Campaign payout recovery is not enabled.');
        }

        if (! $actor instanceof Authenticatable) {
            throw new RuntimeException('Campaign payout recovery requires an authenticated worksheet owner.');
        }

        $authorization->loadMissing(['worksheet', 'fulfillments.row']);
        if ($authorization->status !== 'authorized'
            || $authorization->worksheet === null
            || $authorization->worksheet->owner_type !== $actor->getMorphClass()
            || (string) $authorization->worksheet->owner_id !== (string) $actor->getKey()) {
            throw new RuntimeException('Campaign payout recovery requires the authorized worksheet owner.');
        }

        $summary = ['planned' => 0, 'queued' => 0, 'skipped' => 0];
        $fulfillments = $authorization->fulfillments()
            ->with('row')
            ->whereIn('status', ['recovery_required', 'recovery_ready'])
            ->whereNotNull('pay_code')
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)))
            ->get();

        foreach ($fulfillments as $fulfillment) {
            $planned = DB::transaction(function () use ($fulfillment): bool {
                $locked = CampaignWorksheetFulfillment::query()
                    ->with('row')
                    ->lockForUpdate()
                    ->findOrFail($fulfillment->getKey());

                if (! in_array($locked->status, ['recovery_required', 'recovery_ready'], true)
                    || $locked->pay_code === null) {
                    return false;
                }

                $voucher = Voucher::query()->where('code', $locked->pay_code)->firstOrFail();
                $rejection = DisbursementReconciliation::query()
                    ->where('voucher_id', $voucher->getKey())
                    ->where('status', 'failed')
                    ->where('internal_status', 'recovery_opened')
                    ->where('needs_review', false)
                    ->whereNotNull('completed_at')
                    ->latest('id')
                    ->firstOrFail();
                $claim = VoucherClaim::query()
                    ->where('voucher_id', $voucher->getKey())
                    ->latest('id')
                    ->firstOrFail();

                if (! $this->rejectionTrust->isTrusted($rejection)
                    || $claim->status !== 'payout_rejected'
                    || data_get($voucher->metadata, 'treasury.pay_code_reservation.status') !== 'recovery_pending'
                    || data_get($voucher->metadata, 'instructions.metadata.custom.campaign.claim_activation') !== 'provider_rejection'
                    || data_get($voucher->metadata, 'instructions.validation.otp.required') !== true) {
                    throw new RuntimeException('Trusted instruction-driven rejected-payout recovery evidence is required.');
                }

                if (MobileNumber::normalize(data_get($locked->row?->beneficiary_ciphertext, 'mobile')) === null) {
                    throw new RuntimeException('Campaign payout recovery requires a valid beneficiary mobile.');
                }

                $metadata = (array) $locked->metadata;
                $wasPlanned = data_get($metadata, 'fallback.schema') !== 'x-change.campaign-claim-recovery.v1';
                $metadata['fallback'] = [
                    'schema' => 'x-change.campaign-claim-recovery.v1',
                    'mode' => 'canonical_claim',
                    'rejected_reconciliation_id' => (int) $rejection->getKey(),
                    'planned_at' => data_get($metadata, 'fallback.planned_at') ?? now()->toIso8601String(),
                ];
                $locked->forceFill(['metadata' => $metadata])->save();

                return $wasPlanned;
            }, attempts: 5);

            if ($planned) {
                $summary['planned']++;
            }

            $idempotencyKey = 'campaign-payout-recovery:'.$fulfillment->reference.':canonical-claim-sms:v1';
            if (CampaignDeliveryAttempt::query()
                ->where('idempotency_key_hash', hash('sha256', $idempotencyKey))
                ->exists()) {
                $this->markRecoveryReady($fulfillment);
                $summary['skipped']++;

                continue;
            }

            $mobile = MobileNumber::normalize(data_get($fulfillment->row?->beneficiary_ciphertext, 'mobile'));
            if ($mobile === null) {
                throw new RuntimeException('Campaign payout recovery requires a valid beneficiary mobile.');
            }

            try {
                $this->delivery->handle(
                    authorization: $authorization,
                    actor: $actor,
                    channel: 'sms',
                    recipient: '+'.$mobile,
                    idempotencyKey: $idempotencyKey,
                    purpose: 'beneficiary_payout_recovery',
                    fulfillment: $fulfillment,
                    metadata: [
                        'pay_code' => (string) $fulfillment->pay_code,
                        'recipient_type' => 'campaign_beneficiary',
                    ],
                );
            } catch (UniqueConstraintViolationException $exception) {
                if (! CampaignDeliveryAttempt::query()
                    ->where('idempotency_key_hash', hash('sha256', $idempotencyKey))
                    ->exists()) {
                    throw $exception;
                }

                $summary['skipped']++;
                $this->markRecoveryReady($fulfillment);

                continue;
            }

            $this->markRecoveryReady($fulfillment);
            $summary['queued']++;
        }

        return $summary;
    }

    private function markRecoveryReady(CampaignWorksheetFulfillment $fulfillment): void
    {
        DB::transaction(function () use ($fulfillment): void {
            $locked = CampaignWorksheetFulfillment::query()
                ->lockForUpdate()
                ->findOrFail($fulfillment->getKey());

            if ($locked->status === 'recovery_required') {
                $locked->forceFill(['status' => 'recovery_ready'])->save();
            }
        }, attempts: 5);
    }
}
