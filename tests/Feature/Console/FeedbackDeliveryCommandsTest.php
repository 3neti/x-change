<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XFeedback\Mail\FeedbackEmailMessage;

it('previews direct email by default', function () {
    Mail::fake();

    $exitCode = Artisan::call('x-change:feedback:test-email', [
        'email' => 'recipient@example.test',
        '--message' => 'Preview email',
    ]);

    Mail::assertNothingSent();

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Preview')
        ->and(Artisan::output())->not->toContain('recipient@example.test');
});

it('requires a stable run reference before sending email', function () {
    Mail::fake();

    $exitCode = Artisan::call('x-change:feedback:test-email', [
        'email' => 'recipient@example.test',
        '--send' => true,
    ]);

    Mail::assertNothingSent();

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('--run-reference');
});

it('sends direct email without Laravel notifications', function () {
    Mail::fake();

    $exitCode = Artisan::call('x-change:feedback:test-email', [
        'email' => 'recipient@example.test',
        '--message' => 'Command email',
        '--run-reference' => 'command-email-1',
        '--send' => true,
        '--json' => true,
    ]);

    Mail::assertSent(FeedbackEmailMessage::class, 1);

    $json = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and(data_get($json, 'status'))->toBe('sent')
        ->and(data_get($json, 'channel'))->toBe('email')
        ->and(data_get($json, 'journal_event_types'))->toBe([
            'feedback.created',
            'feedback.sent',
        ]);
});

it('queues direct sms on the feedback queue and renders masked history', function () {
    Bus::fake([DeliverQueuedFeedbackSmsJob::class]);

    config()->set('x-feedback.transports.sms.driver', 'engagespark');
    config()->set('x-feedback.transports.sms.sender', 'cashless');
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');

    $exitCode = Artisan::call('x-change:feedback:test-sms', [
        'mobile' => '09173011987',
        '--message' => 'Command SMS',
        '--run-reference' => 'command-sms-1',
        '--send' => true,
    ]);

    expect($exitCode)->toBe(0);
    Bus::assertDispatched(
        DeliverQueuedFeedbackSmsJob::class,
        fn (DeliverQueuedFeedbackSmsJob $job): bool => $job->queue === 'x-change-feedback',
    );

    $historyExitCode = Artisan::call('x-change:feedback:history', [
        '--channel' => 'sms',
    ]);
    $history = Artisan::output();

    expect($historyExitCode)->toBe(0)
        ->and($history)->toContain('sms')
        ->and($history)->toContain('queued')
        ->and($history)->toContain('********1987')
        ->and($history)->not->toContain('09173011987');
});
