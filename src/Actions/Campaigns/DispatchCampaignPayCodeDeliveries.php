<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use RuntimeException;

final readonly class DispatchCampaignPayCodeDeliveries
{
    public function __construct(
        private RecordCampaignDeliveryAttempt $deliveryAttempts,
        private QueueCampaignFeedbackDelivery $delivery,
    ) {}

    /**
     * @return array{queued: int, blocked: int, skipped: int}
     */
    public function handle(
        CampaignWorksheetAuthorization $authorization,
        Model $actor,
        string $channel,
        int $limit = 100,
    ): array {
        $this->assertChannel($channel);
        $result = ['queued' => 0, 'blocked' => 0, 'skipped' => 0];

        $fulfillments = $authorization->fulfillments()
            ->with('row')
            ->where('status', 'issued')
            ->whereNotNull('pay_code')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($fulfillments as $fulfillment) {
            $alreadyAttempted = CampaignDeliveryAttempt::query()
                ->where('campaign_worksheet_fulfillment_id', $fulfillment->getKey())
                ->where('channel', $channel)
                ->exists();

            if ($alreadyAttempted) {
                $result['skipped']++;

                continue;
            }

            $outcome = $this->dispatch($authorization, $fulfillment, $actor, $channel);
            $result[$outcome]++;
        }

        return $result;
    }

    public function retry(CampaignDeliveryAttempt $previous, Model $actor): string
    {
        $previous->loadMissing(['authorization', 'fulfillment.row', 'events']);
        $lastEvent = $previous->events->last();

        if (! in_array($lastEvent?->event_type, ['failed', 'blocked'], true)
            || ! $previous->authorization instanceof CampaignWorksheetAuthorization
            || ! $previous->fulfillment instanceof CampaignWorksheetFulfillment) {
            throw new RuntimeException('Only failed or blocked beneficiary deliveries can be retried.');
        }

        return $this->dispatch(
            $previous->authorization,
            $previous->fulfillment,
            $actor,
            $previous->channel,
            (string) $previous->reference,
        );
    }

    public function resendCompleted(CampaignDeliveryAttempt $previous, Model $actor): string
    {
        return DB::transaction(function () use ($previous, $actor): string {
            $lockedPrevious = CampaignDeliveryAttempt::query()
                ->with(['authorization', 'fulfillment.row', 'events'])
                ->lockForUpdate()
                ->findOrFail($previous->getKey());

            if (CampaignDeliveryAttempt::query()
                ->where('retry_of_reference', $lockedPrevious->reference)
                ->exists()) {
                return 'already_queued';
            }

            $lastEvent = $lockedPrevious->events->last();
            $latestAttemptId = CampaignDeliveryAttempt::query()
                ->where('campaign_worksheet_fulfillment_id', $lockedPrevious->campaign_worksheet_fulfillment_id)
                ->where('channel', $lockedPrevious->channel)
                ->max('id');

            if ($lastEvent?->event_type !== 'completed'
                || data_get($lockedPrevious->metadata, 'purpose') !== 'beneficiary_pay_code'
                || ! $lockedPrevious->authorization instanceof CampaignWorksheetAuthorization
                || ! $lockedPrevious->fulfillment instanceof CampaignWorksheetFulfillment
                || $latestAttemptId !== $lockedPrevious->getKey()) {
                throw new RuntimeException('Only the latest completed beneficiary delivery can be resent.');
            }

            return $this->dispatch(
                $lockedPrevious->authorization,
                $lockedPrevious->fulfillment,
                $actor,
                $lockedPrevious->channel,
                (string) $lockedPrevious->reference,
                'recipient_reported_missing',
            );
        });
    }

    private function dispatch(
        CampaignWorksheetAuthorization $authorization,
        CampaignWorksheetFulfillment $fulfillment,
        Model $actor,
        string $channel,
        ?string $retryOfReference = null,
        ?string $resendReason = null,
    ): string {
        $beneficiary = (array) ($fulfillment->row?->beneficiary_ciphertext ?? []);
        $route = $channel === 'sms'
            ? $this->stringValue($beneficiary['mobile'] ?? null)
            : $this->stringValue($beneficiary['email'] ?? null);
        if ($route === null) {
            $attempt = $this->deliveryAttempts->start(
                authorization: $authorization,
                channel: $channel,
                actor: $actor,
                idempotencyKey: $this->idempotencyKey(
                    $authorization,
                    $fulfillment,
                    $channel,
                    $retryOfReference,
                ),
                fulfillment: $fulfillment,
                retryOfReference: $retryOfReference,
                metadata: [
                    'purpose' => 'beneficiary_pay_code',
                    'pay_code' => $fulfillment->pay_code,
                    'recipient_type' => 'campaign_beneficiary',
                    ...($resendReason === null ? [] : ['resend_reason' => $resendReason]),
                ],
            );
            $this->deliveryAttempts->append($attempt, 'blocked', safeErrorCode: 'recipient_route_missing');

            return 'blocked';
        }

        $this->delivery->handle(
            authorization: $authorization,
            actor: $actor,
            channel: $channel,
            recipient: $route,
            idempotencyKey: $this->idempotencyKey(
                $authorization,
                $fulfillment,
                $channel,
                $retryOfReference,
            ),
            purpose: 'beneficiary_pay_code',
            fulfillment: $fulfillment,
            retryOfReference: $retryOfReference,
            metadata: [
                'pay_code' => $fulfillment->pay_code,
                'recipient_type' => 'campaign_beneficiary',
                ...($resendReason === null ? [] : ['resend_reason' => $resendReason]),
            ],
        );

        return 'queued';
    }

    private function idempotencyKey(
        CampaignWorksheetAuthorization $authorization,
        CampaignWorksheetFulfillment $fulfillment,
        string $channel,
        ?string $retryOfReference,
    ): string {
        return implode(':', [
            'campaign-delivery',
            (string) $authorization->reference,
            (string) $fulfillment->reference,
            $channel,
            (string) ($retryOfReference ?? 'initial'),
        ]);
    }

    private function assertChannel(string $channel): void
    {
        if (! in_array($channel, ['sms', 'email'], true)) {
            throw new RuntimeException('Campaign delivery channel is not supported.');
        }
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
