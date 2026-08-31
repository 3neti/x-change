<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Bus;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XChange\Actions\Campaigns\DispatchCampaignFeedback;
use LBHurtado\XChange\Actions\Campaigns\PlanCampaignPayoutRecoveryFallbacks;
use LBHurtado\XChange\Jobs\Campaigns\DispatchCampaignFeedbackJob;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XChange\Models\CampaignPayoutRecoveryGrant;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Tests\Fakes\FakeOtpChallengeGateway;

beforeEach(function (): void {
    config()->set('x-change.campaigns.payout_recovery.enabled', true);
    config()->set('x-change.onboarding.identity_otp.driver', 'fake');
    Bus::fake([
        DispatchCampaignFeedbackJob::class,
        DeliverQueuedFeedbackSmsJob::class,
    ]);
});

function campaignRejectedPayoutFixture(): array
{
    $owner = actingAsTestUser();
    $voucher = issueVoucher();
    $metadata = (array) $voucher->metadata;
    data_set($metadata, 'treasury.pay_code_reservation.status', 'recovery_pending');
    $voucher->forceFill(['metadata' => $metadata])->save();

    $worksheet = CampaignWorksheet::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'profile' => 'payroll',
        'name' => 'Rejected payout recovery',
        'currency' => 'PHP',
        'status' => 'frozen',
        'fulfillment_mode' => 'direct_bank_transfer',
        'frozen_at' => now(),
    ]);
    $row = $worksheet->rows()->create([
        'ordinal' => 1,
        'beneficiary_ciphertext' => [
            'name' => 'Sample beneficiary',
            'mobile' => '09175180722',
            'bank_code' => 'BNORPHMMXXX',
            'bank_account' => '********2316',
        ],
        'amount_minor' => 2_500,
        'currency' => 'PHP',
        'delivery_preference' => 'manual',
        'status' => 'authorized',
    ]);
    $authorization = $worksheet->authorizations()->create([
        'manifest_hash' => hash('sha256', 'rejected-payout-recovery'),
        'beneficiary_count' => 1,
        'principal_minor' => 2_500,
        'currency' => 'PHP',
        'status' => 'authorized',
        'approved_by_type' => $owner->getMorphClass(),
        'approved_by_id' => (string) $owner->getKey(),
        'approved_at' => now(),
    ]);
    $fulfillment = $authorization->fulfillments()->create([
        'campaign_worksheet_row_id' => $row->getKey(),
        'mode' => 'direct_bank_transfer',
        'status' => 'recovery_required',
        'pay_code' => $voucher->code,
        'provider_transfer_reference' => 'campaign-transfer-rejected-1',
        'metadata' => [],
    ]);
    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'payout_rejected',
        'requested_amount_minor' => 2_500,
        'currency' => 'PHP',
        'claimer_mobile' => '09175180722',
        'failure_message' => 'AC01 (Incorrect account number)',
    ]);
    $reconciliation = DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => $voucher->code,
        'claim_type' => 'withdraw',
        'provider' => 'netbank',
        'provider_reference' => $voucher->code.'-1',
        'provider_transaction_id' => 'NETBANK-REJECTED-1',
        'status' => 'failed',
        'internal_status' => 'recovery_opened',
        'amount' => 25,
        'currency' => 'PHP',
        'settlement_rail' => 'INSTAPAY',
        'needs_review' => false,
        'completed_at' => now(),
    ]);

    return compact('owner', 'voucher', 'worksheet', 'authorization', 'fulfillment', 'reconciliation');
}

it('queues one same-pay-code claim notification only after trusted rejection evidence', function (): void {
    $fixture = campaignRejectedPayoutFixture();

    $result = app(PlanCampaignPayoutRecoveryFallbacks::class)->handle(
        $fixture['authorization'],
        $fixture['owner'],
    );

    expect($result)->toBe(['planned' => 1, 'queued' => 1, 'skipped' => 0])
        ->and(CampaignPayoutRecoveryGrant::query()->count())->toBe(1)
        ->and(CampaignDeliveryAttempt::query()->count())->toBe(1)
        ->and($fixture['fulfillment']->refresh()->status)->toBe('recovery_ready')
        ->and(data_get($fixture['fulfillment']->metadata, 'fallback.mode'))
        ->toBe('same_pay_code_recipient_correction');

    $grant = CampaignPayoutRecoveryGrant::query()->sole();
    expect($grant->voucher_id)->toBe($fixture['voucher']->getKey())
        ->and($grant->rejected_reconciliation_id)->toBe($fixture['reconciliation']->getKey())
        ->and($grant->status)->toBe('available');
    Bus::assertDispatchedTimes(DispatchCampaignFeedbackJob::class, 1);

    $replay = app(PlanCampaignPayoutRecoveryFallbacks::class)->handle(
        $fixture['authorization']->refresh(),
        $fixture['owner'],
    );

    expect($replay)->toBe(['planned' => 0, 'queued' => 0, 'skipped' => 1])
        ->and(CampaignPayoutRecoveryGrant::query()->count())->toBe(1)
        ->and(CampaignDeliveryAttempt::query()->count())->toBe(1);
});

it('accepts the execution-engine recovery state without an optional claim projection', function (): void {
    $fixture = campaignRejectedPayoutFixture();
    VoucherClaim::query()->where('voucher_id', $fixture['voucher']->getKey())->delete();

    $result = app(PlanCampaignPayoutRecoveryFallbacks::class)->handle(
        $fixture['authorization'],
        $fixture['owner'],
    );

    expect($result)->toBe(['planned' => 1, 'queued' => 1, 'skipped' => 0])
        ->and($fixture['fulfillment']->refresh()->status)->toBe('recovery_ready')
        ->and(CampaignPayoutRecoveryGrant::query()->sole()->voucher_id)
        ->toBe($fixture['voucher']->getKey());
});

it('delivers only the canonical claim URL without exposing the recovery grant', function (): void {
    config()->set('x-feedback.transports.sms.driver', 'engagespark');
    config()->set('x-feedback.transports.sms.sender', 'cashless');
    config()->set('x-change.redemption.feedback.queue', 'x-change-feedback');
    $fixture = campaignRejectedPayoutFixture();
    app(PlanCampaignPayoutRecoveryFallbacks::class)->handle(
        $fixture['authorization'],
        $fixture['owner'],
    );
    $grant = CampaignPayoutRecoveryGrant::query()->sole();
    $queued = null;
    Bus::assertDispatched(DispatchCampaignFeedbackJob::class, function (
        DispatchCampaignFeedbackJob $job,
    ) use (&$queued): bool {
        $queued = $job;

        return true;
    });

    expect($queued)->toBeInstanceOf(DispatchCampaignFeedbackJob::class);
    app(DispatchCampaignFeedback::class)->handle($queued->attemptId, $queued->recipient);

    Bus::assertDispatched(DeliverQueuedFeedbackSmsJob::class, function (
        DeliverQueuedFeedbackSmsJob $job,
    ) use ($fixture, $grant): bool {
        $claimUrl = route('x-change.claim.show', ['code' => $fixture['voucher']->code]);

        expect($job->message)->toContain($claimUrl)
            ->not->toContain('/payout-recovery/')
            ->not->toContain((string) $grant->reference);

        return true;
    });
});

it('refuses fallback while provider failure still needs operator review', function (): void {
    $fixture = campaignRejectedPayoutFixture();
    $fixture['reconciliation']->forceFill(['needs_review' => true])->save();

    expect(fn () => app(PlanCampaignPayoutRecoveryFallbacks::class)->handle(
        $fixture['authorization'],
        $fixture['owner'],
    ))->toThrow(ModelNotFoundException::class);

    expect(CampaignPayoutRecoveryGrant::query()->count())->toBe(0)
        ->and(CampaignDeliveryAttempt::query()->count())->toBe(0)
        ->and($fixture['fulfillment']->refresh()->status)->toBe('recovery_required');
});

it('never sends recovery for a nonterminal or indeterminate provider outcome', function (
    string $status,
    string $internalStatus,
): void {
    $fixture = campaignRejectedPayoutFixture();
    $fixture['reconciliation']->forceFill([
        'status' => $status,
        'internal_status' => $internalStatus,
        'completed_at' => null,
    ])->save();

    expect(fn () => app(PlanCampaignPayoutRecoveryFallbacks::class)->handle(
        $fixture['authorization'],
        $fixture['owner'],
    ))->toThrow(ModelNotFoundException::class);

    expect(CampaignPayoutRecoveryGrant::query()->count())->toBe(0)
        ->and(CampaignDeliveryAttempt::query()->count())->toBe(0);
})->with([
    'pending' => ['pending', 'recorded'],
    'unknown' => ['unknown', 'recorded'],
]);

it('requires beneficiary OTP before exposing a corrected payout destination', function (): void {
    $fixture = campaignRejectedPayoutFixture();
    app(PlanCampaignPayoutRecoveryFallbacks::class)->handle(
        $fixture['authorization'],
        $fixture['owner'],
    );
    $grant = CampaignPayoutRecoveryGrant::query()->sole();
    $otp = new FakeOtpChallengeGateway;
    $otp->expectedCode = '123456';
    app()->instance(OtpChallengeGateway::class, $otp);

    $this->get('/x/claim/WRONG/payout-recovery/'.$grant->reference)->assertNotFound();

    $this->withHeader('X-Inertia', 'true')->get(route('x-change.claim.show', [
        'code' => $fixture['voucher']->code,
    ]))->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Pragma', 'no-cache')
        ->assertJsonPath('component', 'x-change/claim/PayoutRecovery')
        ->assertJsonPath('props.status', 'available')
        ->assertJsonMissingPath('props.mobile')
        ->assertJsonMissingPath('props.recovery_reference');

    $this->post(route('x-change.claim.payout-recovery.challenge', [
        'code' => $fixture['voucher']->code,
    ]))->assertRedirect();

    expect($grant->refresh()->status)->toBe('otp_pending')
        ->and($otp->request?->mobile)->toBe('+639175180722')
        ->and($otp->request?->client_reference)->toBe($grant->reference);

    $this->post(route('x-change.claim.payout-recovery.verification', [
        'code' => $fixture['voucher']->code,
    ]), ['code' => '000000'])->assertSessionHasErrors('code');

    expect($grant->refresh()->status)->toBe('otp_pending')
        ->and($grant->attempts)->toBe(1);

    $this->post(route('x-change.claim.payout-recovery.verification', [
        'code' => $fixture['voucher']->code,
    ]), ['code' => '123456'])->assertRedirect();

    expect($grant->refresh()->status)->toBe('verified')
        ->and($grant->verified_at)->not->toBeNull();
});

it('fails closed when more than one active recovery grant exists for a Pay Code', function (): void {
    $fixture = campaignRejectedPayoutFixture();
    app(PlanCampaignPayoutRecoveryFallbacks::class)->handle(
        $fixture['authorization'],
        $fixture['owner'],
    );
    $secondRejection = $fixture['reconciliation']->replicate();
    $secondRejection->provider_reference = $fixture['voucher']->code.'-2';
    $secondRejection->provider_transaction_id = 'NETBANK-REJECTED-2';
    $secondRejection->save();
    CampaignPayoutRecoveryGrant::query()->create([
        'voucher_id' => $fixture['voucher']->getKey(),
        'campaign_worksheet_fulfillment_id' => $fixture['fulfillment']->getKey(),
        'rejected_reconciliation_id' => $secondRejection->getKey(),
        'mobile_hash' => hash('sha256', 'second-grant'),
        'provider' => 'fake',
        'purpose' => 'campaign.payout-recovery',
        'status' => 'available',
        'expires_at' => now()->addHour(),
    ]);

    $this->post(route('x-change.claim.payout-recovery.challenge', [
        'code' => $fixture['voucher']->code,
    ]))->assertNotFound();
});

it('does not replace the ordinary claim experience without authoritative recovery state', function (): void {
    $voucher = issueVoucher();

    $response = $this->withHeader('X-Inertia', 'true')->get(route('x-change.claim.show', [
        'code' => $voucher->code,
    ]))->assertOk();

    expect($response->json('component'))->toBe('x-change/claim/Entry');

    $fixture = campaignRejectedPayoutFixture();
    app(PlanCampaignPayoutRecoveryFallbacks::class)->handle(
        $fixture['authorization'],
        $fixture['owner'],
    );
    $fixture['reconciliation']->forceFill([
        'status' => 'pending',
        'internal_status' => 'recorded',
        'completed_at' => null,
    ])->save();

    $this->post(route('x-change.claim.payout-recovery.challenge', [
        'code' => $fixture['voucher']->code,
    ]))->assertNotFound();
});
