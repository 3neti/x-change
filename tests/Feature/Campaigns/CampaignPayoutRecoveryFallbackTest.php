<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XChange\Actions\Campaigns\DispatchCampaignFeedback;
use LBHurtado\XChange\Actions\Campaigns\PlanCampaignPayoutRecoveryFallbacks;
use LBHurtado\XChange\Actions\Campaigns\SubmitCampaignPayoutRecoveryClaim;
use LBHurtado\XChange\Actions\Claim\ValidateCompiledClaimVoucher;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Jobs\Campaigns\DispatchCampaignFeedbackJob;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;

beforeEach(function (): void {
    config()->set('x-change.campaigns.payout_recovery.enabled', true);
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
    data_set($metadata, 'instructions.cash.validation.mobile', '09175180722');
    data_set($metadata, 'instructions.validation.otp', ['required' => true, 'on_failure' => 'block']);
    data_set($metadata, 'instructions.inputs.fields', ['mobile', 'otp']);
    data_set($metadata, 'instructions.metadata.custom.claim_evidence.requirements', ['mobile', 'otp']);
    data_set($metadata, 'instructions.metadata.custom.campaign.claim_activation', 'provider_rejection');
    $voucher->forceFill(['metadata' => $metadata, 'redeemed_at' => now()])->save();

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
        ->and(CampaignDeliveryAttempt::query()->count())->toBe(1)
        ->and($fixture['fulfillment']->refresh()->status)->toBe('recovery_ready')
        ->and(data_get($fixture['fulfillment']->metadata, 'fallback.mode'))
        ->toBe('canonical_claim');
    Bus::assertDispatchedTimes(DispatchCampaignFeedbackJob::class, 1);

    $replay = app(PlanCampaignPayoutRecoveryFallbacks::class)->handle(
        $fixture['authorization']->refresh(),
        $fixture['owner'],
    );

    expect($replay)->toBe(['planned' => 0, 'queued' => 0, 'skipped' => 1])
        ->and(CampaignDeliveryAttempt::query()->count())->toBe(1);
});

it('requires the canonical execution claim before recovery notification', function (): void {
    $fixture = campaignRejectedPayoutFixture();
    VoucherClaim::query()->where('voucher_id', $fixture['voucher']->getKey())->delete();

    expect(fn () => app(PlanCampaignPayoutRecoveryFallbacks::class)->handle(
        $fixture['authorization'],
        $fixture['owner'],
    ))->toThrow(ModelNotFoundException::class);

    expect(CampaignDeliveryAttempt::query()->count())->toBe(0)
        ->and($fixture['fulfillment']->refresh()->status)->toBe('recovery_required');
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
    ) use ($fixture): bool {
        $claimUrl = route('x-change.claim.show', ['code' => $fixture['voucher']->code]);

        expect($job->message)->toContain($claimUrl)
            ->not->toContain('/payout-recovery/')
            ->not->toContain('recovery_reference');

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

    expect(CampaignDeliveryAttempt::query()->count())->toBe(0)
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

    expect(CampaignDeliveryAttempt::query()->count())->toBe(0);
})->with([
    'pending' => ['pending', 'recorded'],
    'unknown' => ['unknown', 'recorded'],
]);

it('uses the ordinary claim entry and instruction-driven otp workflow for recovery', function (): void {
    $fixture = campaignRejectedPayoutFixture();
    app(PlanCampaignPayoutRecoveryFallbacks::class)->handle(
        $fixture['authorization'],
        $fixture['owner'],
    );
    auth()->logout();

    $response = $this->withHeader('X-Inertia', 'true')->get(route('x-change.claim.show', [
        'code' => $fixture['voucher']->code,
    ]))->assertOk()
        ->assertHeader('Cache-Control', 'no-cache, private')
        ->assertJsonPath('component', 'x-change/claim/Entry')
        ->assertJsonPath('props.claim_surface.state.key', 'active')
        ->assertJsonPath('props.claim_surface.state.can_claim', true)
        ->assertJsonPath('props.claim_surface.state.terminal', false);

    $components = collect($response->json('props.claim_surface.components'));
    $requirements = collect($components->firstWhere('type', 'xray_preview')['props']['requirements']);
    expect($components->pluck('type'))->toContain('xray_preview')
        ->and($requirements->pluck('key')->all())->toContain('mobile', 'otp', 'assigned_mobile');

    $workflow = app(ClaimWorkflowResolverContract::class)->resolve($fixture['voucher']->refresh());
    expect($workflow->key)->toBe('campaign.payout-recovery.v1')
        ->and($workflow->required_claim_fields)->toBe(['mobile', 'otp'])
        ->and($workflow->requires_destination)->toBeTrue()
        ->and($workflow->requires_amount)->toBeFalse();
});

it('keeps the same campaign Pay Code dormant until provider rejection activates recovery', function (): void {
    $fixture = campaignRejectedPayoutFixture();
    $metadata = (array) $fixture['voucher']->metadata;
    data_set($metadata, 'treasury.pay_code_reservation.status', 'reserved');
    $fixture['voucher']->forceFill(['metadata' => $metadata, 'redeemed_at' => null])->save();

    expect(app(ValidateCompiledClaimVoucher::class)->handle($fixture['voucher']->refresh()))
        ->toBe('This Pay Code is being processed by the approved campaign transfer.');

    data_set($metadata, 'treasury.pay_code_reservation.status', 'recovery_pending');
    $fixture['voucher']->forceFill(['metadata' => $metadata, 'redeemed_at' => now()])->save();

    expect(app(ValidateCompiledClaimVoucher::class)->handle($fixture['voucher']->refresh()))->toBeNull();
});

it('rejects a recovery claim whose verified otp mobile is not the frozen beneficiary', function (): void {
    $fixture = campaignRejectedPayoutFixture();

    expect(fn () => app(SubmitCampaignPayoutRecoveryClaim::class)->handle(
        $fixture['voucher'],
        [
            'mobile' => '09173011987',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
            'inputs' => [
                'mobile' => '09173011987',
                'otp_verified' => true,
                'otp' => [
                    'verified' => true,
                    'mobile' => '09173011987',
                ],
            ],
        ],
    ))->toThrow(ValidationException::class);

    expect($fixture['voucher']->refresh()->redeemed_at)->not->toBeNull()
        ->and(DisbursementReconciliation::query()->count())->toBe(1);
});

it('does not alter the ordinary claim experience for unrelated Pay Codes', function (): void {
    $voucher = issueVoucher();

    $response = $this->withHeader('X-Inertia', 'true')->get(route('x-change.claim.show', [
        'code' => $voucher->code,
    ]))->assertOk();

    expect($response->json('component'))->toBe('x-change/claim/Entry');
});
