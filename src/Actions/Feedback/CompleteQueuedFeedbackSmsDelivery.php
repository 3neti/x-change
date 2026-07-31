<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Feedback;

use Carbon\CarbonImmutable;
use LBHurtado\EngageSpark\Classes\SendHttpApiParams;
use LBHurtado\EngageSpark\Classes\ServiceMode;
use LBHurtado\EngageSpark\EngageSpark;
use LBHurtado\XChange\Contracts\FeedbackDeliveryJournalWriterContract;
use LBHurtado\XFeedback\Contracts\FeedbackDeliveryAttemptRecorderContract;
use LBHurtado\XFeedback\Data\FeedbackDeliveryAttemptData;
use LBHurtado\XFeedback\Data\FeedbackDeliveryData;
use LBHurtado\XFeedback\Data\FeedbackDeliveryRecordData;
use LBHurtado\XFeedback\Data\FeedbackProviderReceiptData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use LBHurtado\XFeedback\Models\FeedbackDeliveryRecord;
use RuntimeException;
use Throwable;

final readonly class CompleteQueuedFeedbackSmsDelivery
{
    public function __construct(
        private EngageSpark $engageSpark,
        private FeedbackDeliveryAttemptRecorderContract $recorder,
        private FeedbackDeliveryJournalWriterContract $journal,
    ) {}

    public function handle(
        string $deliveryId,
        string $message,
        string $sender,
    ): FeedbackDeliveryRecordData {
        $record = $this->record($deliveryId);

        if ($this->isProviderTerminal($record->status)) {
            return $this->data($record);
        }

        $recipient = $this->recipient($record);
        $response = $this->engageSpark->send(
            (new SendHttpApiParams(
                service: $this->engageSpark,
                mobile: (string) $recipient->phone,
                message: $message,
                senderId: $sender,
            ))->toArray(),
            ServiceMode::SMS,
        );
        $response = is_array($response) ? $response : [];

        return $this->recordOutcome(
            record: $record,
            recipient: $recipient,
            status: FeedbackDeliveryData::StatusSent,
            providerMessageId: $this->firstScalar($response, [
                'message_id',
                'messageId',
                'sms_id',
                'smsId',
                'id',
                'campaign_id',
                'campaignId',
            ]),
            providerStatus: $this->firstScalar($response, [
                'status',
                'provider_status',
                'providerStatus',
            ]) ?? 'ACCEPTED',
            providerPayload: ['accepted' => true],
        );
    }

    public function fail(string $deliveryId, Throwable $exception): ?FeedbackDeliveryRecordData
    {
        $record = FeedbackDeliveryRecord::query()
            ->where('delivery_id', $deliveryId)
            ->first();

        if (! $record instanceof FeedbackDeliveryRecord) {
            return null;
        }

        if ($this->isProviderTerminal((string) $record->status)) {
            return $this->data($record);
        }

        return $this->recordOutcome(
            record: $record,
            recipient: $this->recipient($record),
            status: FeedbackDeliveryData::StatusFailedFinal,
            providerMessageId: null,
            providerStatus: 'FAILED',
            providerPayload: ['accepted' => false],
            meta: [
                'failure_class' => $exception::class,
                'retryable' => false,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $providerPayload
     * @param  array<string, mixed>  $meta
     */
    private function recordOutcome(
        FeedbackDeliveryRecord $record,
        FeedbackRecipientData $recipient,
        string $status,
        ?string $providerMessageId,
        ?string $providerStatus,
        array $providerPayload,
        array $meta = [],
    ): FeedbackDeliveryRecordData {
        $receipt = new FeedbackProviderReceiptData(
            intent_key: (string) $record->intent_key,
            channel: 'sms',
            recipient: $recipient,
            status: $status,
            provider_message_id: $providerMessageId,
            provider_status: $providerStatus,
            provider_payload: $providerPayload,
            correlation_id: $record->correlation_id,
            causation_id: $record->causation_id,
            occurred_at: CarbonImmutable::now()->toISOString(),
            meta: [
                'idempotency_key' => (string) $record->idempotency_key,
                'provider' => 'engagespark',
                'queue' => (string) config(
                    'x-change.redemption.feedback.queue',
                    'x-change-feedback',
                ),
                ...$meta,
            ],
        );
        $updated = $this->recorder->record(new FeedbackDeliveryAttemptData(
            intent_key: (string) $record->intent_key,
            receipts: [$receipt],
            correlation_id: $record->correlation_id,
            causation_id: $record->causation_id,
        ))[0] ?? null;

        if (! $updated instanceof FeedbackDeliveryRecordData) {
            throw new RuntimeException('x-feedback did not persist the queued SMS outcome.');
        }

        $this->journal->writeRecorded($updated);

        return $updated;
    }

    private function record(string $deliveryId): FeedbackDeliveryRecord
    {
        $record = FeedbackDeliveryRecord::query()
            ->where('delivery_id', $deliveryId)
            ->first();

        if (! $record instanceof FeedbackDeliveryRecord) {
            throw new RuntimeException('The queued SMS delivery record was not found.');
        }

        return $record;
    }

    private function recipient(FeedbackDeliveryRecord $record): FeedbackRecipientData
    {
        $recipient = new FeedbackRecipientData(...(array) $record->recipient);

        if (! is_string($recipient->phone) || trim($recipient->phone) === '') {
            throw new RuntimeException('The queued SMS recipient is unavailable.');
        }

        return $recipient;
    }

    private function data(FeedbackDeliveryRecord $record): FeedbackDeliveryRecordData
    {
        $records = $this->recorder->forCorrelation((string) $record->correlation_id);

        foreach ($records as $candidate) {
            if (
                $candidate instanceof FeedbackDeliveryRecordData
                && $candidate->delivery_id === $record->delivery_id
            ) {
                return $candidate;
            }
        }

        throw new RuntimeException('The queued SMS delivery evidence is unavailable.');
    }

    private function isProviderTerminal(string $status): bool
    {
        return in_array($status, [
            FeedbackDeliveryData::StatusSent,
            FeedbackDeliveryData::StatusDelivered,
            FeedbackDeliveryData::StatusFailedFinal,
            FeedbackDeliveryData::StatusExpired,
            FeedbackDeliveryData::StatusCancelled,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  list<string>  $keys
     */
    private function firstScalar(array $response, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $response[$key] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
