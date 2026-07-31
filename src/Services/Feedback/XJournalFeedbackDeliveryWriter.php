<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Feedback;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\FeedbackDeliveryJournalWriterContract;
use LBHurtado\XFeedback\Contracts\FeedbackJournalEventMapperContract;
use LBHurtado\XFeedback\Data\FeedbackDeliveryData;
use LBHurtado\XFeedback\Data\FeedbackDeliveryRecordData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class XJournalFeedbackDeliveryWriter implements FeedbackDeliveryJournalWriterContract
{
    public function __construct(
        private FeedbackJournalEventMapperContract $mapper,
        private ExecutionJournalRecorder $recorder,
    ) {}

    public function writeCreated(
        FeedbackIntentData $intent,
        FeedbackRecipientData $recipient,
        string $channel,
        string $deliveryKey,
        int $attempt,
    ): ExecutionJournalEntry {
        $idempotencyKey = hash('sha256', implode('|', [
            'x-change.feedback.created',
            $deliveryKey,
            (string) $attempt,
        ]));
        $existing = $this->existing($idempotencyKey);

        if ($existing instanceof ExecutionJournalEntry) {
            return $existing;
        }

        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'feedback.created',
            occurredAt: CarbonImmutable::now(),
            actor: new ExecutionActorData(
                id: 'x-change',
                type: 'system',
            ),
            subject: new ExecutionSubjectData(
                id: $deliveryKey,
                type: 'feedback_delivery',
                display: $channel,
            ),
            references: new ExecutionReferenceData(
                correlationId: $intent->context?->correlation_id,
                causationId: $intent->context?->causation_id,
                externalReference: $deliveryKey,
                metadata: [
                    'intent_key' => $intent->key,
                    'channel' => $channel,
                    'attempt' => $attempt,
                ],
            ),
            idempotencyKey: $idempotencyKey,
            payload: [
                'intent_key' => $intent->key,
                'channel' => $channel,
                'delivery_status' => 'pending',
                'attempt' => $attempt,
                'recipient' => $this->safeRecipient($recipient),
            ],
            metadata: $this->metadata(),
        ));
    }

    public function writeRecorded(FeedbackDeliveryRecordData $record): ExecutionJournalEntry
    {
        $event = $this->mapper->fromRecord($record);
        $idempotencyKey = hash('sha256', implode('|', [
            'x-change.feedback.recorded',
            (string) $record->delivery_id,
            (string) $record->attempt_count,
            $record->status,
        ]));
        $existing = $this->existing($idempotencyKey);

        if ($existing instanceof ExecutionJournalEntry) {
            return $existing;
        }

        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $record->status === FeedbackDeliveryData::StatusQueued
                ? 'feedback.queued'
                : $event->event_name,
            occurredAt: $this->occurredAt($record),
            actor: new ExecutionActorData(
                id: 'x-feedback',
                type: 'system',
            ),
            subject: new ExecutionSubjectData(
                id: $record->delivery_id,
                type: 'feedback_delivery',
                display: $record->channel,
            ),
            references: new ExecutionReferenceData(
                correlationId: $record->correlation_id,
                causationId: $record->causation_id,
                externalReference: $record->provider_message_id,
                metadata: [
                    'intent_key' => $record->intent_key,
                    'channel' => $record->channel,
                    'provider_status' => $record->provider_status,
                    'attempt' => $record->attempt_count,
                ],
            ),
            idempotencyKey: $idempotencyKey,
            payload: [
                'intent_key' => $record->intent_key,
                'channel' => $record->channel,
                'delivery_status' => $record->status,
                'attempt' => $record->attempt_count,
                'provider_message_id' => $record->provider_message_id,
                'provider_status' => $record->provider_status,
                'recipient' => $this->safeRecipient($record->recipient),
            ],
            metadata: $this->metadata(),
        ));
    }

    private function existing(string $idempotencyKey): ?ExecutionJournalEntry
    {
        if (! config('x-journal.idempotency.enabled', true)) {
            return null;
        }

        return ExecutionJournalEntry::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function occurredAt(FeedbackDeliveryRecordData $record): CarbonImmutable
    {
        $occurredAt = $record->last_attempted_at;

        return is_string($occurredAt) && trim($occurredAt) !== ''
            ? CarbonImmutable::parse($occurredAt)
            : CarbonImmutable::now();
    }

    /**
     * @return array<string, string|null>
     */
    private function safeRecipient(FeedbackRecipientData $recipient): array
    {
        $route = $recipient->email ?: $recipient->phone;

        return [
            'type' => $recipient->type,
            'id' => $recipient->id,
            'route_masked' => is_string($route) ? $this->maskRoute($route) : null,
            'route_hash' => is_string($route) ? hash('sha256', $route) : null,
        ];
    }

    private function maskRoute(string $route): string
    {
        if (str_contains($route, '@')) {
            [$local, $domain] = array_pad(explode('@', $route, 2), 2, '');
            $visible = Str::substr($local, 0, 1);

            return $visible.str_repeat('*', max(1, Str::length($local) - 1)).'@'.$domain;
        }

        return Str::mask($route, '*', 0, max(0, Str::length($route) - 4));
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(): array
    {
        return [
            'source' => 'x-change.feedback',
            'delivery_truth_source' => 'x-feedback',
            'canonical_audit_source' => 'x-journal',
            'redactions' => [
                'message_body_exposed' => false,
                'raw_recipient_exposed' => false,
                'provider_payload_exposed' => false,
                'transport_credentials_exposed' => false,
            ],
        ];
    }
}
