<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Feedback;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\XChange\Actions\Feedback\SendTestFeedback;
use Throwable;

final class TestFeedbackEmailCommand extends Command
{
    protected $signature = 'x-change:feedback:test-email
        {email : Destination email address}
        {--subject=X-Change feedback delivery test : Email subject}
        {--message=X-Change email feedback is configured and working. : Message body}
        {--run-reference= : Stable idempotency reference required for live delivery}
        {--send : Perform the live provider delivery}
        {--json : Output JSON}';

    protected $description = 'Preview or send a direct journaled x-feedback email without Laravel Notifications.';

    public function handle(SendTestFeedback $feedback): int
    {
        $email = trim((string) $this->argument('email'));
        $send = (bool) $this->option('send');
        $runReference = $this->runReference($send);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('The email address is invalid.');

            return self::FAILURE;
        }

        if ($runReference === null) {
            $this->components->error('Live delivery requires a stable --run-reference.');

            return self::FAILURE;
        }

        try {
            $result = $feedback->handle(
                channel: 'email',
                route: $email,
                message: (string) $this->option('message'),
                runReference: $runReference,
                send: $send,
                title: (string) $this->option('subject'),
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->renderFailure($exception);

            return self::FAILURE;
        }

        $this->renderResult($result->toArray(), $send);

        return $send && ! $result->sent ? self::FAILURE : self::SUCCESS;
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

        $this->components->info($send ? 'Email feedback delivery completed.' : 'Preview only; no email was sent.');
        $this->table(
            ['Mode', 'Channel', 'Recipient', 'Status', 'Delivery ID'],
            [[
                $send ? 'live' : 'preview',
                'email',
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

        $this->components->error('Email feedback delivery failed. Inspect the application log for the provider-safe error.');
    }
}
