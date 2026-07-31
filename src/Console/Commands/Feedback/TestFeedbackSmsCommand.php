<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Feedback;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\XChange\Actions\Feedback\SendTestFeedback;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LBHurtado\XFeedback\Data\FeedbackDeliveryData;
use Throwable;

final class TestFeedbackSmsCommand extends Command
{
    protected $signature = 'x-change:feedback:test-sms
        {mobile : Destination Philippine mobile number}
        {--message=X-Change SMS feedback is configured and working. : Message body}
        {--run-reference= : Stable idempotency reference required for live delivery}
        {--send : Perform the live provider delivery}
        {--json : Output JSON}';

    protected $description = 'Preview or send a direct journaled x-feedback SMS without Laravel Notifications.';

    public function handle(SendTestFeedback $feedback): int
    {
        $mobile = MobileNumber::normalize((string) $this->argument('mobile'));
        $send = (bool) $this->option('send');
        $runReference = $this->runReference($send);

        if (! is_string($mobile) || $mobile === '') {
            $this->components->error('The mobile number is invalid.');

            return self::FAILURE;
        }

        if ($runReference === null) {
            $this->components->error('Live delivery requires a stable --run-reference.');

            return self::FAILURE;
        }

        try {
            $result = $feedback->handle(
                channel: 'sms',
                route: $mobile,
                message: (string) $this->option('message'),
                runReference: $runReference,
                send: $send,
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->renderFailure($exception);

            return self::FAILURE;
        }

        $this->renderResult($result->toArray(), $send);

        return $send && ! in_array($result->status, [
            FeedbackDeliveryData::StatusQueued,
            FeedbackDeliveryData::StatusSent,
            FeedbackDeliveryData::StatusDelivered,
        ], true)
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function runReference(bool $send): ?string
    {
        $runReference = trim((string) $this->option('run-reference'));

        if ($runReference !== '') {
            return $runReference;
        }

        return $send ? null : 'preview-'.Str::uuid();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function renderResult(array $result, bool $send): void
    {
        $payload = [
            'status' => $result['status'],
            'channel' => $result['channel'],
            'recipient' => $result['maskedRoute'],
            'run_reference' => $result['runReference'],
            'sent' => $result['sent'],
            'replayed' => $result['replayed'],
            'delivery_id' => $result['deliveryId'],
            'provider_message_id' => $result['providerMessageId'],
            'provider_status' => $result['providerStatus'],
            'journal_event_types' => $result['journalEventTypes'],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->components->info(
            $send
                ? 'SMS feedback delivery was accepted for processing.'
                : 'Preview only; no SMS was queued.',
        );
        $this->table(
            ['Mode', 'Channel', 'Recipient', 'Status', 'Delivery ID'],
            [[
                $send ? 'live' : 'preview',
                'sms',
                $payload['recipient'],
                $payload['status'],
                $payload['delivery_id'] ?? '—',
            ]],
        );
    }

    private function renderFailure(Throwable $exception): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'status' => 'failed',
                'error' => $exception::class,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->components->error('SMS feedback delivery failed. Inspect the application log for the provider-safe error.');
    }
}
