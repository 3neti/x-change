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
use LBHurtado\XChange\Models\CampaignPayoutRecoveryGrant;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use RuntimeException;

final readonly class PlanCampaignPayoutRecoveryFallbacks
{
    public function __construct(
        private QueueCampaignFeedbackDelivery $delivery,
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
            $grant = DB::transaction(function () use ($fulfillment, &$summary): ?CampaignPayoutRecoveryGrant {
                $locked = CampaignWorksheetFulfillment::query()
                    ->with('row')
                    ->lockForUpdate()
                    ->findOrFail($fulfillment->getKey());

                if (! in_array($locked->status, ['recovery_required', 'recovery_ready'], true)
                    || $locked->pay_code === null) {
                    $summary['skipped']++;

                    return null;
                }

                $voucher = Voucher::query()->where('code', $locked->pay_code)->firstOrFail();
                $rejection = DisbursementReconciliation::query()
                    ->where('voucher_id', $voucher->getKey())
                    ->where('status', 'failed')
                    ->where('internal_status', 'recovery_opened')
                    ->where('needs_review', false)
                    ->whereNotNull('provider_transaction_id')
                    ->whereNotNull('completed_at')
                    ->latest('id')
                    ->firstOrFail();
                $claim = VoucherClaim::query()
                    ->where('voucher_id', $voucher->getKey())
                    ->latest('id')
                    ->first();

                if (($claim instanceof VoucherClaim && $claim->status !== 'payout_rejected')
                    || data_get($voucher->metadata, 'treasury.pay_code_reservation.status') !== 'recovery_pending') {
                    throw new RuntimeException('Trusted rejected-payout recovery evidence is required.');
                }

                $mobile = MobileNumber::normalize(
                    data_get($locked->row?->beneficiary_ciphertext, 'mobile'),
                );
                if ($mobile === null) {
                    throw new RuntimeException('Campaign payout recovery requires a valid beneficiary mobile.');
                }

                $grant = CampaignPayoutRecoveryGrant::query()->firstOrCreate(
                    ['rejected_reconciliation_id' => $rejection->getKey()],
                    [
                        'voucher_id' => $voucher->getKey(),
                        'campaign_worksheet_fulfillment_id' => $locked->getKey(),
                        'mobile_hash' => $this->mobileHash($mobile),
                        'provider' => $this->otpDriver(),
                        'purpose' => $this->otpPurpose(),
                        'status' => 'available',
                        'attempts' => 0,
                        'expires_at' => now()->addMinutes(max(1, (int) config(
                            'x-change.campaigns.payout_recovery.ttl_minutes',
                            1440,
                        ))),
                    ],
                );

                $metadata = (array) $locked->metadata;
                $metadata['fallback'] = [
                    'schema' => 'x-change.campaign-payout-recovery.v1',
                    'mode' => 'same_pay_code_recipient_correction',
                    'grant_reference' => (string) $grant->reference,
                    'rejected_reconciliation_id' => (int) $rejection->getKey(),
                    'planned_at' => data_get($metadata, 'fallback.planned_at') ?? now()->toIso8601String(),
                ];
                $locked->forceFill(['metadata' => $metadata])->save();
                if ($grant->wasRecentlyCreated) {
                    $summary['planned']++;
                }

                return $grant;
            }, attempts: 5);

            if (! $grant instanceof CampaignPayoutRecoveryGrant) {
                continue;
            }

            $idempotencyKey = 'campaign-payout-recovery:'.$grant->reference.':claim-sms:v2';
            if (CampaignDeliveryAttempt::query()
                ->where('idempotency_key_hash', hash('sha256', $idempotencyKey))
                ->exists()) {
                $this->markRecoveryReady($fulfillment);
                $summary['skipped']++;

                continue;
            }

            $mobile = MobileNumber::normalize(
                data_get($fulfillment->row?->beneficiary_ciphertext, 'mobile'),
            );
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

    private function otpDriver(): string
    {
        $driver = trim((string) config('x-change.onboarding.identity_otp.driver', 'unavailable'));
        if (in_array($driver, ['', 'unavailable', 'null'], true)) {
            throw new RuntimeException('Campaign payout recovery OTP delivery is unavailable.');
        }

        return $driver;
    }

    private function otpPurpose(): string
    {
        $purpose = trim((string) config(
            'x-change.campaigns.payout_recovery.otp_purpose',
            'campaign.payout-recovery',
        ));
        if ($purpose === '') {
            throw new RuntimeException('Campaign payout recovery OTP purpose is unavailable.');
        }

        return $purpose;
    }

    private function mobileHash(string $mobile): string
    {
        $key = (string) (config('x-change.onboarding.mobile_verification.hash_key') ?: config('app.key'));

        return hash_hmac('sha256', $mobile, $key);
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
