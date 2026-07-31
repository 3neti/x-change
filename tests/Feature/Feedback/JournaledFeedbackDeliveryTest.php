<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use LBHurtado\EngageSpark\Classes\ServiceMode;
use LBHurtado\EngageSpark\EngageSpark;
use LBHurtado\XChange\Actions\Feedback\CompleteQueuedFeedbackSmsDelivery;
use LBHurtado\XChange\Actions\Feedback\SendTestFeedback;
use LBHurtado\XChange\Contracts\FeedbackDeliveryJournalWriterContract;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XChange\Services\Feedback\XJournalFeedbackDeliveryWriter;
use LBHurtado\XFeedback\Data\FeedbackDeliveryRecordData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use LBHurtado\XFeedback\Mail\FeedbackEmailMessage;
use LBHurtado\XFeedback\Models\FeedbackDeliveryRecord;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('previews direct feedback without sending or writing delivery evidence', function () {
    Mail::fake();

    $result = app(SendTestFeedback::class)->handle(
        channel: 'email',
        route: 'preview@example.test',
        message: 'Preview feedback',
        runReference: 'preview-email-1',
        send: false,
    );

    Mail::assertNothingSent();

    expect($result->status)->toBe('preview')
        ->and($result->sent)->toBeFalse()
        ->and(FeedbackDeliveryRecord::query()->count())->toBe(0)
        ->and(ExecutionJournalEntry::query()->count())->toBe(0);
});

it('records created and sent journal events for direct email without exposing pii', function () {
    Mail::fake();

    $result = app(SendTestFeedback::class)->handle(
        channel: 'email',
        route: 'recipient@example.test',
        message: 'Journal-safe feedback body',
        runReference: 'journal-email-1',
        send: true,
    );

    Mail::assertSent(FeedbackEmailMessage::class, 1);

    $record = FeedbackDeliveryRecord::query()->sole();
    $entries = ExecutionJournalEntry::query()
        ->where('correlation_id', 'journal-email-1')
        ->orderBy('id')
        ->get();
    $serializedEntries = $entries->toJson();

    expect($result->status)->toBe('sent')
        ->and($result->sent)->toBeTrue()
        ->and($result->deliveryId)->toBe($record->delivery_id)
        ->and($entries->pluck('event_type')->all())->toBe([
            'feedback.created',
            'feedback.sent',
        ])
        ->and($serializedEntries)->not->toContain('recipient@example.test')
        ->and($serializedEntries)->not->toContain('Journal-safe feedback body');
});

it('replays terminal email evidence without sending the provider message again', function () {
    Mail::fake();

    $delivery = app(SendTestFeedback::class);
    $first = $delivery->handle(
        channel: 'email',
        route: 'recipient@example.test',
        message: 'Idempotent feedback',
        runReference: 'journal-email-replay-1',
        send: true,
    );
    $second = $delivery->handle(
        channel: 'email',
        route: 'recipient@example.test',
        message: 'Idempotent feedback',
        runReference: 'journal-email-replay-1',
        send: true,
    );

    Mail::assertSent(FeedbackEmailMessage::class, 1);

    expect($first->deliveryId)->toBe($second->deliveryId)
        ->and($second->replayed)->toBeTrue()
        ->and(FeedbackDeliveryRecord::query()->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()->count())->toBe(2);
});

it('recovers a failed final journal write without redelivering email', function () {
    Mail::fake();
    $realWriter = app(XJournalFeedbackDeliveryWriter::class);
    $failingWriter = new class($realWriter) implements FeedbackDeliveryJournalWriterContract
    {
        public function __construct(
            private readonly XJournalFeedbackDeliveryWriter $writer,
        ) {}

        public function writeCreated(
            FeedbackIntentData $intent,
            FeedbackRecipientData $recipient,
            string $channel,
            string $deliveryKey,
            int $attempt,
        ): ExecutionJournalEntry {
            return $this->writer->writeCreated($intent, $recipient, $channel, $deliveryKey, $attempt);
        }

        public function writeRecorded(FeedbackDeliveryRecordData $record): ExecutionJournalEntry
        {
            throw new RuntimeException('Synthetic final journal failure.');
        }
    };

    app()->instance(FeedbackDeliveryJournalWriterContract::class, $failingWriter);

    expect(fn () => app(SendTestFeedback::class)->handle(
        channel: 'email',
        route: 'recovery@example.test',
        message: 'Journal recovery feedback',
        runReference: 'journal-email-recovery-1',
        send: true,
    ))->toThrow(RuntimeException::class, 'Synthetic final journal failure.');

    Mail::assertSent(FeedbackEmailMessage::class, 1);
    expect(FeedbackDeliveryRecord::query()->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()->count())->toBe(1);

    app()->instance(FeedbackDeliveryJournalWriterContract::class, $realWriter);

    $recovered = app(SendTestFeedback::class)->handle(
        channel: 'email',
        route: 'recovery@example.test',
        message: 'Journal recovery feedback',
        runReference: 'journal-email-recovery-1',
        send: true,
    );

    Mail::assertSent(FeedbackEmailMessage::class, 1);
    expect($recovered->replayed)->toBeTrue()
        ->and(ExecutionJournalEntry::query()->count())->toBe(2);
});

it('records queued sms evidence and dispatches provider delivery to the feedback queue', function () {
    Bus::fake([DeliverQueuedFeedbackSmsJob::class]);

    config()->set('x-feedback.transports.sms.driver', 'engagespark');
    config()->set('x-feedback.transports.sms.sender', 'cashless');
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');

    $result = app(SendTestFeedback::class)->handle(
        channel: 'sms',
        route: '09173011987',
        message: 'Direct SMS feedback',
        runReference: 'journal-sms-1',
        send: true,
    );

    $entries = ExecutionJournalEntry::query()
        ->where('correlation_id', 'journal-sms-1')
        ->orderBy('id')
        ->get();

    Bus::assertDispatched(
        DeliverQueuedFeedbackSmsJob::class,
        fn (DeliverQueuedFeedbackSmsJob $job): bool => $job->queue === 'x-change-feedback'
            && $job->deliveryId === $result->deliveryId,
    );

    expect($result->status)->toBe('queued')
        ->and($result->sent)->toBeFalse()
        ->and($result->providerMessageId)->toBeNull()
        ->and($entries->pluck('event_type')->all())->toBe([
            'feedback.created',
            'feedback.queued',
        ])
        ->and($entries->toJson())->not->toContain('09173011987');
});

it('records sent sms evidence only after EngageSpark accepts the queued job', function () {
    Bus::fake([DeliverQueuedFeedbackSmsJob::class]);

    config()->set('x-feedback.transports.sms.sender', 'cashless');
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');

    $result = app(SendTestFeedback::class)->handle(
        channel: 'sms',
        route: '09173011987',
        message: 'Queued SMS feedback',
        runReference: 'journal-sms-provider-1',
        send: true,
    );
    $queuedJob = null;

    Bus::assertDispatched(
        DeliverQueuedFeedbackSmsJob::class,
        function (DeliverQueuedFeedbackSmsJob $job) use (&$queuedJob): bool {
            $queuedJob = $job;

            return true;
        },
    );

    $engageSpark = Mockery::mock(EngageSpark::class);
    $engageSpark->shouldReceive('getOrgId')->once()->andReturn('org-test');
    $engageSpark->shouldReceive('send')
        ->once()
        ->with(
            Mockery::on(fn (array $payload): bool => $payload === [
                'orgId' => 'org-test',
                'to' => '639173011987',
                'from' => 'cashless',
                'message' => 'Queued SMS feedback',
            ]),
            ServiceMode::SMS,
        )
        ->andReturn([
            'message_id' => 'engagespark-message-1',
            'status' => 'ACCEPTED',
        ]);
    app()->instance(EngageSpark::class, $engageSpark);

    expect($queuedJob)->toBeInstanceOf(DeliverQueuedFeedbackSmsJob::class);
    $queuedJob->handle(app(
        CompleteQueuedFeedbackSmsDelivery::class,
    ));

    $record = FeedbackDeliveryRecord::query()
        ->where('delivery_id', $result->deliveryId)
        ->sole();
    $entries = ExecutionJournalEntry::query()
        ->where('correlation_id', 'journal-sms-provider-1')
        ->orderBy('id')
        ->get();

    expect($record->status)->toBe('sent')
        ->and($record->provider_message_id)->toBe('engagespark-message-1')
        ->and($record->provider_status)->toBe('ACCEPTED')
        ->and($entries->pluck('event_type')->all())->toBe([
            'feedback.created',
            'feedback.queued',
            'feedback.sent',
        ])
        ->and($entries->toJson())->not->toContain('09173011987')
        ->and($entries->toJson())->not->toContain('Queued SMS feedback');
});

it('records a final sms failure when the dedicated job exhausts retries', function () {
    Bus::fake([DeliverQueuedFeedbackSmsJob::class]);

    $result = app(SendTestFeedback::class)->handle(
        channel: 'sms',
        route: '09173011987',
        message: 'Failed SMS feedback',
        runReference: 'journal-sms-failed-1',
        send: true,
    );
    $queuedJob = null;

    Bus::assertDispatched(
        DeliverQueuedFeedbackSmsJob::class,
        function (DeliverQueuedFeedbackSmsJob $job) use (&$queuedJob): bool {
            $queuedJob = $job;

            return true;
        },
    );

    expect($queuedJob)->toBeInstanceOf(DeliverQueuedFeedbackSmsJob::class);
    $queuedJob->failed(new RuntimeException('Synthetic provider failure.'));

    $record = FeedbackDeliveryRecord::query()
        ->where('delivery_id', $result->deliveryId)
        ->sole();
    $entries = ExecutionJournalEntry::query()
        ->where('correlation_id', 'journal-sms-failed-1')
        ->orderBy('id')
        ->get();

    expect($record->status)->toBe('failed_final')
        ->and($record->provider_status)->toBe('FAILED')
        ->and($entries->pluck('event_type')->all())->toBe([
            'feedback.created',
            'feedback.queued',
            'feedback.failed',
        ])
        ->and($entries->toJson())->not->toContain('Synthetic provider failure.');
});
