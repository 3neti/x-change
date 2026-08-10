<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Feedback;

use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\FeedbackDeliveryJournalWriterContract;
use LBHurtado\XChange\Data\Feedback\JournaledFeedbackDeliveryResultData;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XFeedback\Contracts\FeedbackChannelContentRendererContract;
use LBHurtado\XFeedback\Contracts\FeedbackDeliveryAttemptRecorderContract;
use LBHurtado\XFeedback\Contracts\FeedbackDeliveryAttemptRuntimeContract;
use LBHurtado\XFeedback\Contracts\FeedbackDispatchPreparerContract;
use LBHurtado\XFeedback\Data\FeedbackChannelSelectionPolicyData;
use LBHurtado\XFeedback\Data\FeedbackDeliveryAttemptData;
use LBHurtado\XFeedback\Data\FeedbackDeliveryData;
use LBHurtado\XFeedback\Data\FeedbackDeliveryRecordData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackProviderReceiptData;
use RuntimeException;

final readonly class DeliverAndJournalFeedback
{
    public function __construct(
        private FeedbackDispatchPreparerContract $preparer,
        private FeedbackDeliveryAttemptRuntimeContract $runtime,
        private FeedbackDeliveryAttemptRecorderContract $recorder,
        private FeedbackDeliveryJournalWriterContract $journal,
        private FeedbackChannelContentRendererContract $content,
    ) {}

    public function handle(
        FeedbackIntentData $intent,
        string $channel,
        string $runReference,
        bool $send,
    ): JournaledFeedbackDeliveryResultData {
        $preparation = $this->preparer->prepare(
            $intent,
            new FeedbackChannelSelectionPolicyData(
                allowed_channels: [$channel],
                preferred_channels: [$channel],
                required_channels: [$channel],
                profile: 'direct_feedback',
                meta: [
                    'delivery_boundary' => $send ? 'direct_live' : 'preview',
                    'owns_lifecycle_truth' => false,
                ],
            ),
        );
        $item = $preparation->plan->items[0] ?? null;

        if ($item === null) {
            throw new RuntimeException(sprintf(
                'No [%s] x-feedback delivery route could be prepared.',
                $channel,
            ));
        }

        $deliveryKey = $this->deliveryKey(
            intent: $intent,
            channel: $channel,
            recipientId: (string) ($item->recipient->id ?? ''),
            runReference: $runReference,
        );

        if (! $send) {
            return new JournaledFeedbackDeliveryResultData(
                status: 'preview',
                channel: $channel,
                maskedRoute: $this->maskRoute($item->recipient->email ?: $item->recipient->phone),
                runReference: $runReference,
                meta: [
                    'planned_items' => count($preparation->plan->items),
                    'provider_side_effect' => false,
                    'journal_side_effect' => false,
                ],
            );
        }

        $existing = $this->recordForDeliveryKey(
            correlationId: (string) $preparation->correlation_id,
            deliveryKey: $deliveryKey,
        );

        if ($existing instanceof FeedbackDeliveryRecordData && $this->isTerminal($existing->status)) {
            $created = $this->journal->writeCreated(
                intent: $intent,
                recipient: $item->recipient,
                channel: $channel,
                deliveryKey: $deliveryKey,
                attempt: max(1, $existing->attempt_count),
            );
            $recorded = $this->journal->writeRecorded($existing);
            $this->dispatchQueuedSms($existing, $intent);

            return $this->result(
                record: $existing,
                runReference: $runReference,
                maskedRoute: $this->maskRoute($item->recipient->email ?: $item->recipient->phone),
                journalEventTypes: [$created->event_type, $recorded->event_type],
                replayed: true,
            );
        }

        $attemptNumber = ($existing?->attempt_count ?? 0) + 1;
        $created = $this->journal->writeCreated(
            intent: $intent,
            recipient: $item->recipient,
            channel: $channel,
            deliveryKey: $deliveryKey,
            attempt: $attemptNumber,
        );
        $attempt = $this->withIdempotencyKey(
            $this->runtime->execute($preparation),
            $deliveryKey,
        );
        $records = $this->recorder->record($attempt);
        $record = $records[0] ?? null;

        if (! $record instanceof FeedbackDeliveryRecordData) {
            throw new RuntimeException('x-feedback did not persist delivery evidence.');
        }

        $recorded = $this->journal->writeRecorded($record);
        $this->dispatchQueuedSms($record, $intent);

        return $this->result(
            record: $record,
            runReference: $runReference,
            maskedRoute: $this->maskRoute($item->recipient->email ?: $item->recipient->phone),
            journalEventTypes: [$created->event_type, $recorded->event_type],
        );
    }

    private function withIdempotencyKey(
        FeedbackDeliveryAttemptData $attempt,
        string $deliveryKey,
    ): FeedbackDeliveryAttemptData {
        $receipts = array_map(
            static fn (FeedbackProviderReceiptData $receipt): FeedbackProviderReceiptData => new FeedbackProviderReceiptData(
                intent_key: $receipt->intent_key,
                channel: $receipt->channel,
                recipient: $receipt->recipient,
                status: $receipt->status,
                provider_message_id: $receipt->provider_message_id,
                provider_status: $receipt->provider_status,
                provider_payload: $receipt->provider_payload,
                correlation_id: $receipt->correlation_id,
                causation_id: $receipt->causation_id,
                occurred_at: $receipt->occurred_at,
                meta: [
                    ...$receipt->meta,
                    'idempotency_key' => $deliveryKey,
                ],
            ),
            $attempt->receipts,
        );

        return new FeedbackDeliveryAttemptData(
            intent_key: $attempt->intent_key,
            status: $attempt->status,
            deliveries: $attempt->deliveries,
            receipts: $receipts,
            correlation_id: $attempt->correlation_id,
            causation_id: $attempt->causation_id,
            meta: $attempt->meta,
        );
    }

    private function recordForDeliveryKey(
        string $correlationId,
        string $deliveryKey,
    ): ?FeedbackDeliveryRecordData {
        foreach ($this->recorder->forCorrelation($correlationId) as $record) {
            if (
                $record instanceof FeedbackDeliveryRecordData
                && hash_equals((string) $record->idempotency_key, $deliveryKey)
            ) {
                return $record;
            }
        }

        return null;
    }

    private function deliveryKey(
        FeedbackIntentData $intent,
        string $channel,
        string $recipientId,
        string $runReference,
    ): string {
        return hash('sha256', implode('|', [
            'x-change.feedback.delivery',
            $intent->key,
            $channel,
            $recipientId,
            $runReference,
        ]));
    }

    private function isTerminal(string $status): bool
    {
        return ! in_array($status, [
            FeedbackDeliveryData::StatusPending,
            FeedbackDeliveryData::StatusSending,
            FeedbackDeliveryData::StatusFailedRetryable,
            FeedbackDeliveryData::StatusRetryScheduled,
        ], true);
    }

    /**
     * @param  list<string>  $journalEventTypes
     */
    private function result(
        FeedbackDeliveryRecordData $record,
        string $runReference,
        string $maskedRoute,
        array $journalEventTypes,
        bool $replayed = false,
    ): JournaledFeedbackDeliveryResultData {
        return new JournaledFeedbackDeliveryResultData(
            status: $record->status,
            channel: $record->channel,
            maskedRoute: $maskedRoute,
            runReference: $runReference,
            sent: in_array($record->status, [
                FeedbackDeliveryData::StatusSent,
                FeedbackDeliveryData::StatusDelivered,
            ], true),
            replayed: $replayed,
            deliveryId: $record->delivery_id,
            providerMessageId: $record->provider_message_id,
            providerStatus: $record->provider_status,
            journalEventTypes: $journalEventTypes,
            meta: [
                'attempt_count' => $record->attempt_count,
                'provider_side_effect' => ! $replayed,
                'journal_side_effect' => true,
            ],
        );
    }

    private function dispatchQueuedSms(
        FeedbackDeliveryRecordData $record,
        FeedbackIntentData $intent,
    ): void {
        if (
            $record->channel !== 'sms'
            || $record->status !== FeedbackDeliveryData::StatusQueued
            || ! is_string($record->delivery_id)
            || trim($record->delivery_id) === ''
        ) {
            return;
        }

        DeliverQueuedFeedbackSmsJob::dispatch(
            deliveryId: $record->delivery_id,
            message: $this->content->text($intent, 'sms'),
            sender: (string) config('x-feedback.transports.sms.sender', 'XCHANGE'),
        )->afterCommit();
    }

    private function maskRoute(?string $route): string
    {
        if (! is_string($route) || trim($route) === '') {
            return '[unavailable]';
        }

        if (str_contains($route, '@')) {
            [$local, $domain] = array_pad(explode('@', $route, 2), 2, '');

            return Str::substr($local, 0, 1)
                .str_repeat('*', max(1, Str::length($local) - 1))
                .'@'.$domain;
        }

        return Str::mask($route, '*', 0, max(0, Str::length($route) - 4));
    }
}
