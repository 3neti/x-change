<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;
use LBHurtado\XFeedback\Mail\FeedbackEmailMessage;
use LBHurtado\XFeedback\Models\FeedbackDeliveryRecord;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

beforeEach(function () {
    config([
        'x-change.lifecycle.defaults.user_model' => FakeLifecycleUser::class,
        'x-change.lifecycle.scenarios.feedback_email_sms' => [
            'label' => 'Feedback email and SMS',
            'mode' => 'feedback_delivery',
            'mobile' => '09173011987',
            'feedback' => [
                'email' => null,
                'mobile' => null,
            ],
        ],
        'x-feedback.transports.sms.driver' => 'engagespark',
        'x-feedback.transports.sms.sender' => 'cashless',
        'x-change.redemption.feedback.queue' => 'x-change-feedback',
    ]);
});

it('previews a feedback lifecycle scenario without vouchers or provider delivery', function () {
    Mail::fake();
    $issuer = feedbackLifecycleIssuer();

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'feedback_email_sms',
        '--issuer' => (string) $issuer->getKey(),
        '--feedback-email' => 'recipient@example.test',
        '--feedback-mobile' => '09173011987',
        '--run-reference' => 'feedback-lifecycle-preview-1',
        '--json' => true,
    ]);

    Mail::assertNothingSent();
    $json = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and(data_get($json, 'mode'))->toBe('feedback_delivery')
        ->and(data_get($json, 'live'))->toBeFalse()
        ->and(data_get($json, 'deliveries'))->toHaveCount(2)
        ->and(Voucher::query()->count())->toBe(0)
        ->and(FeedbackDeliveryRecord::query()->count())->toBe(0)
        ->and(ExecutionJournalEntry::query()->count())->toBe(0);
});

it('runs live email and sms feedback lifecycle with delivery and journal evidence', function () {
    Mail::fake();
    Bus::fake([DeliverQueuedFeedbackSmsJob::class]);

    $issuer = feedbackLifecycleIssuer();

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'feedback_email_sms',
        '--issuer' => (string) $issuer->getKey(),
        '--feedback-email' => 'recipient@example.test',
        '--feedback-mobile' => '09173011987',
        '--run-reference' => 'feedback-lifecycle-live-1',
        '--live-feedback' => true,
        '--json' => true,
    ]);

    Mail::assertSent(FeedbackEmailMessage::class, 1);
    Bus::assertDispatched(
        DeliverQueuedFeedbackSmsJob::class,
        fn (DeliverQueuedFeedbackSmsJob $job): bool => $job->queue === 'x-change-feedback',
    );
    $json = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and(data_get($json, 'live'))->toBeTrue()
        ->and(data_get($json, 'deliveries'))->toHaveCount(2)
        ->and(data_get($json, 'journal.events'))->toBe(4)
        ->and(Voucher::query()->count())->toBe(0)
        ->and(FeedbackDeliveryRecord::query()->count())->toBe(2)
        ->and(ExecutionJournalEntry::query()->where('event_type', 'like', 'feedback.%')->count())->toBe(4);
});

function feedbackLifecycleIssuer(): FakeLifecycleUser
{
    $issuer = FakeLifecycleUser::query()->create([
        'name' => 'Feedback Lifecycle Issuer',
        'email' => 'issuer@example.test',
        'password' => bcrypt('password'),
    ]);
    $issuer->setMobileChannel('09173011987');
    $issuer->save();

    return $issuer;
}
