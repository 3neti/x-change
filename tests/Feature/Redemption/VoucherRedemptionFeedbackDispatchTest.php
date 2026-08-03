<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\DispatchVoucherRedemptionFeedback;
use LBHurtado\XChange\Actions\Redemption\RecordVoucherClaim;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XChange\Jobs\Redemption\DispatchVoucherRedemptionFeedbackJob;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XFeedback\Contracts\FeedbackWebhookSenderContract;
use LBHurtado\XFeedback\Data\FeedbackDeliveryData;
use LBHurtado\XFeedback\Data\FeedbackWebhookMessageData;
use LBHurtado\XFeedback\Data\FeedbackWebhookSendResultData;
use LBHurtado\XFeedback\Mail\FeedbackEmailMessage;
use LBHurtado\XFeedback\Models\FeedbackDeliveryRecord;

beforeEach(function () {
    config()->set('x-change.redemption.feedback.enabled', true);
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');
    config()->set('x-feedback.transports.sms.driver', 'engagespark');
    config()->set('x-feedback.transports.sms.sender', 'cashless');
});

it('queues redemption feedback only after a successful claim with configured routes', function () {
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);

    $voucher = redemptionFeedbackVoucher('TEST-FEEDBACK-QUEUE', [
        'email' => 'issuer@example.test',
    ]);

    $claim = app(RecordVoucherClaim::class)->handle(
        $voucher,
        redemptionFeedbackResult($voucher, status: 'redeemed'),
    );

    Bus::assertDispatched(
        DispatchVoucherRedemptionFeedbackJob::class,
        fn (DispatchVoucherRedemptionFeedbackJob $job): bool => $job->voucherClaimId === $claim->getKey()
            && $job->afterCommit === true
            && $job->queue === 'x-change-feedback',
    );
});

it('does not queue redemption feedback for an unsuccessful claim or empty instructions', function () {
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);

    $failedVoucher = redemptionFeedbackVoucher('TEST-FEEDBACK-FAILED', [
        'email' => 'issuer@example.test',
    ]);
    app(RecordVoucherClaim::class)->handle(
        $failedVoucher,
        redemptionFeedbackResult($failedVoucher, status: 'failed', claimed: false),
    );

    $emptyVoucher = redemptionFeedbackVoucher('TEST-FEEDBACK-EMPTY');
    app(RecordVoucherClaim::class)->handle(
        $emptyVoucher,
        redemptionFeedbackResult($emptyVoucher, status: 'redeemed'),
    );

    Bus::assertNotDispatched(DispatchVoucherRedemptionFeedbackJob::class);
});

it('delivers configured email sms and webhook feedback through x-feedback', function () {
    Mail::fake();
    Bus::fake([DeliverQueuedFeedbackSmsJob::class]);
    $webhookSender = new RecordingRedemptionFeedbackWebhookSender;
    app()->instance(FeedbackWebhookSenderContract::class, $webhookSender);

    $voucher = redemptionFeedbackVoucher('TEST-FEEDBACK-DELIVERY', [
        'email' => 'issuer@example.test',
        'mobile' => '09173011987',
        'webhook' => 'https://example.test/x-feedback',
    ]);
    $claim = redemptionFeedbackClaim($voucher);

    app(DispatchVoucherRedemptionFeedback::class)->handle($claim->getKey());

    Mail::assertSent(
        FeedbackEmailMessage::class,
        fn (FeedbackEmailMessage $mail): bool => $mail->hasTo('issuer@example.test')
            && $mail->intent->key === 'voucher.redemption.recorded',
    );
    expect($webhookSender->messages)->toHaveCount(1)
        ->and($webhookSender->messages[0]->url)->toBe('https://example.test/x-feedback')
        ->and($webhookSender->messages[0]->payload['intent_key'])->toBe('voucher.redemption.recorded')
        ->and($webhookSender->messages[0]->payload['subject_id'])->toBe($voucher->code);
    Bus::assertDispatched(
        DeliverQueuedFeedbackSmsJob::class,
        fn (DeliverQueuedFeedbackSmsJob $job): bool => $job->queue === 'x-change-feedback',
    );

    expect(FeedbackDeliveryRecord::query()->count())->toBe(3)
        ->and(FeedbackDeliveryRecord::query()->pluck('channel')->sort()->values()->all())
        ->toBe(['email', 'sms', 'webhook'])
        ->and(FeedbackDeliveryRecord::query()->where('channel', 'email')->value('status'))->toBe('sent')
        ->and(FeedbackDeliveryRecord::query()->where('channel', 'sms')->value('status'))->toBe('queued')
        ->and(FeedbackDeliveryRecord::query()->where('channel', 'webhook')->value('status'))->toBe('queued');
});

it('reports a reconciliation-required payout as pending rather than successful', function () {
    Mail::fake();

    $voucher = redemptionFeedbackVoucher('TEST-FEEDBACK-PENDING', [
        'email' => 'issuer@example.test',
    ]);
    $metadata = $voucher->metadata;
    data_set($metadata, 'disbursement.status', 'pending');
    data_set($metadata, 'disbursement.requires_reconciliation', true);
    $voucher->forceFill(['metadata' => $metadata])->save();
    $claim = redemptionFeedbackClaim($voucher);

    app(DispatchVoucherRedemptionFeedback::class)->handle($claim->getKey());

    Mail::assertSent(
        FeedbackEmailMessage::class,
        fn (FeedbackEmailMessage $mail): bool => $mail->intent->key === 'voucher.redemption.pending'
            && $mail->intent->message->body === 'Pay Code TEST-FEEDBACK-PENDING was claimed. Provider payout is pending verification.'
            && ! str_contains($mail->intent->message->body, 'successfully'),
    );
});

it('does not redeliver channels that already have durable terminal evidence', function () {
    Mail::fake();
    Bus::fake([DeliverQueuedFeedbackSmsJob::class]);
    $webhookSender = new RecordingRedemptionFeedbackWebhookSender;
    app()->instance(FeedbackWebhookSenderContract::class, $webhookSender);

    $voucher = redemptionFeedbackVoucher('TEST-FEEDBACK-IDEMPOTENT', [
        'email' => 'issuer@example.test',
        'mobile' => '09173011987',
        'webhook' => 'https://example.test/x-feedback',
    ]);
    $claim = redemptionFeedbackClaim($voucher);
    $dispatch = app(DispatchVoucherRedemptionFeedback::class);

    $dispatch->handle($claim->getKey());
    $dispatch->handle($claim->getKey());

    Mail::assertSent(FeedbackEmailMessage::class, 1);
    Bus::assertDispatchedTimes(DeliverQueuedFeedbackSmsJob::class, 1);
    expect($webhookSender->messages)->toHaveCount(1)
        ->and(FeedbackDeliveryRecord::query()->count())->toBe(3);
});

/**
 * @param  array{email?: string|null, mobile?: string|null, webhook?: string|null}  $feedback
 */
function redemptionFeedbackVoucher(string $code, array $feedback = []): Voucher
{
    return Voucher::query()->create([
        'code' => $code,
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => 500,
                    'currency' => 'PHP',
                    'validation' => [
                        'secret' => null,
                        'mobile' => null,
                        'payable' => null,
                        'country' => 'PH',
                        'location' => null,
                        'radius' => null,
                    ],
                    'fee_strategy' => 'absorb',
                ],
                'inputs' => ['fields' => []],
                'feedback' => [
                    'email' => $feedback['email'] ?? null,
                    'mobile' => $feedback['mobile'] ?? null,
                    'webhook' => $feedback['webhook'] ?? null,
                ],
                'rider' => [],
                'count' => 1,
                'prefix' => 'TEST',
                'mask' => '****',
            ],
        ],
        'state' => 'active',
    ]);
}

function redemptionFeedbackResult(
    Voucher $voucher,
    string $status,
    bool $claimed = true,
): SubmitPayCodeClaimResultData {
    return new SubmitPayCodeClaimResultData(
        voucher_code: $voucher->code,
        claim_type: 'redeem',
        claimed: $claimed,
        status: $status,
        requested_amount: 500,
        disbursed_amount: 500,
        currency: 'PHP',
        remaining_balance: 0,
        fully_claimed: $claimed,
        disbursement: ['status' => 'requested'],
        messages: [],
    );
}

function redemptionFeedbackClaim(Voucher $voucher): VoucherClaim
{
    return VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'redeem',
        'status' => 'redeemed',
        'currency' => 'PHP',
        'attempted_at' => now(),
        'completed_at' => now(),
        'meta' => [
            'disbursement' => ['status' => 'requested'],
        ],
    ]);
}

final class RecordingRedemptionFeedbackWebhookSender implements FeedbackWebhookSenderContract
{
    /** @var list<FeedbackWebhookMessageData> */
    public array $messages = [];

    public function send(FeedbackWebhookMessageData $message): FeedbackWebhookSendResultData
    {
        $this->messages[] = $message;

        return new FeedbackWebhookSendResultData(
            message_id: 'webhook-message-'.count($this->messages),
            status: FeedbackDeliveryData::StatusQueued,
            result: [
                'transport' => 'test',
                'url' => $message->url,
            ],
        );
    }
}
