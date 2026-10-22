<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimRedirectController;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Support\Claim\ClaimRiderHandoff;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XRider\Contracts\RiderAnalyticsRecorderContract;
use LBHurtado\XRider\Contracts\RiderExperienceResolverContract;
use LBHurtado\XRider\Contracts\SuccessRedirectResolverContract;
use LBHurtado\XRider\Data\RiderAnalyticsEventData;
use LBHurtado\XRider\Data\RiderExperienceData;
use LBHurtado\XRider\Data\RiderRedirectData;
use LBHurtado\XRider\Data\RiderStageCollectionData;
use LBHurtado\XRider\Data\RiderSubjectData;
use LBHurtado\XRider\Enums\RiderOutcomeState;

beforeEach(function () {
    $this->app->instance(
        RiderExperienceResolverContract::class,
        new class implements RiderExperienceResolverContract
        {
            public function resolve(RiderSubjectData $subject, array $context = []): RiderExperienceData
            {
                $url = data_get($context, 'rider.url');
                $state = RiderOutcomeState::tryFrom((string) data_get($context, 'state')) ?? RiderOutcomeState::AcceptedSuccess;

                return new RiderExperienceData(
                    state: $state,
                    subject: $subject,
                    redirect: filled($url) && $state->riderMayRun()
                        ? new RiderRedirectData(
                            enabled: true,
                            url: $url,
                            timeout: 5,
                        )
                        : null,
                    stages: new RiderStageCollectionData(stages: []),
                );
            }
        }
    );

    $this->app->instance(
        SuccessRedirectResolverContract::class,
        new class implements SuccessRedirectResolverContract
        {
            public function resolve(RiderExperienceData $experience): string
            {
                return $experience->redirect?->url ?? '';
            }
        }
    );

    $this->app->instance(
        RiderAnalyticsRecorderContract::class,
        new class implements RiderAnalyticsRecorderContract
        {
            public function record(RiderAnalyticsEventData $event): void
            {
                //
            }
        }
    );

    Route::get('/x/claim/{code}/redirect', ClaimRedirectController::class)
        ->name('x-change.claim.redirect');
});

it('rejects a direct rider redirect before a completed claim', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'message' => 'SUCCESS DEMO: Thank you for claiming.',
                'url' => 'https://example.com/after-claim',
            ],
        ],
    ));

    $this->get(route('x-change.claim.redirect', [
        'code' => $voucher->code,
    ]))
        ->assertNotFound();
});

it('redirects a completed claim from its authorized browser session', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => ['url' => 'https://example.com/after-claim'],
    ]));
    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'disbursement',
        'status' => 'succeeded',
        'idempotency_key' => 'claim-rider-test',
        'completed_at' => now(),
        'currency' => 'PHP',
    ]);
    $journal = Mockery::mock(ExecutionJournalRecorder::class);
    $journal->shouldReceive('record')->times(3)->andReturn(new ExecutionJournalEntry);
    $this->app->instance(ExecutionJournalRecorder::class, $journal);

    $prepared = app(ClaimRiderHandoff::class)->prepare($voucher, new SubmitPayCodeClaimResultData(
        voucher_code: (string) $voucher->code,
        claim_type: 'disbursement',
        claimed: true,
        status: 'succeeded',
    ), 'claim-rider-test');

    expect($prepared)->toBeTrue();
    $this->get(route('x-change.claim.redirect', ['code' => $voucher->code]))
        ->assertRedirect('https://example.com/after-claim');

    $fallback = Mockery::mock(SuccessRedirectResolverContract::class);
    $fallback->shouldReceive('resolve')->once()->andReturn('/');
    $this->app->instance(SuccessRedirectResolverContract::class, $fallback);

    $this->get(route('x-change.claim.redirect', ['code' => $voucher->code]))
        ->assertRedirect(route('x-change.claim.success', ['code' => $voucher->code]));
});

it('keeps an accepted claim rider handoff when a pending payout later fails', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => ['url' => 'https://example.com/after-claim'],
    ]));
    $metadata = $voucher->metadata ?? [];
    data_set($metadata, 'disbursement.status', 'pending');
    $voucher->metadata = $metadata;
    $voucher->save();
    $claim = VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'disbursement',
        'status' => 'pending_review',
        'idempotency_key' => 'pending-rider-claim',
        'completed_at' => now(),
        'currency' => 'PHP',
    ]);
    $journal = Mockery::mock(ExecutionJournalRecorder::class);
    $journal->shouldReceive('record')->times(3)->andReturn(new ExecutionJournalEntry);
    $this->app->instance(ExecutionJournalRecorder::class, $journal);
    $analytics = Mockery::mock(RiderAnalyticsRecorderContract::class);
    $analytics->shouldReceive('record')->once()->withArgs(
        fn (RiderAnalyticsEventData $event): bool => $event->event === 'rider.redirect.started'
            && data_get($event->context, 'claim_outcome') === RiderOutcomeState::RejectedFailure->value
    );
    $this->app->instance(RiderAnalyticsRecorderContract::class, $analytics);

    expect(app(ClaimRiderHandoff::class)->prepare($voucher, new SubmitPayCodeClaimResultData(
        voucher_code: (string) $voucher->code,
        claim_type: 'disbursement',
        claimed: true,
        status: 'pending_review',
        disbursement: ['status' => 'pending'],
    ), 'pending-rider-claim'))->toBeTrue();

    $metadata = $voucher->metadata ?? [];
    data_set($metadata, 'disbursement.status', 'failed');
    $voucher->metadata = $metadata;
    $voucher->save();
    $claim->update(['status' => 'payout_rejected']);

    $this->get(route('x-change.claim.redirect', ['code' => $voucher->code]))
        ->assertRedirect('https://example.com/after-claim');
});

it('does not authorize a rider handoff for a claim awaiting approval', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => ['url' => 'https://example.com/after-claim'],
    ]));
    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'disbursement',
        'status' => 'pending_review',
        'idempotency_key' => 'pending-rider-claim',
        'completed_at' => now(),
        'currency' => 'PHP',
    ]);
    $journal = Mockery::mock(ExecutionJournalRecorder::class);
    $journal->shouldNotReceive('record');
    $this->app->instance(ExecutionJournalRecorder::class, $journal);

    expect(app(ClaimRiderHandoff::class)->prepare($voucher, new SubmitPayCodeClaimResultData(
        voucher_code: (string) $voucher->code,
        claim_type: 'disbursement',
        claimed: false,
        status: 'pending_review',
    ), 'pending-rider-claim'))->toBeFalse();

    $this->get(route('x-change.claim.redirect', ['code' => $voucher->code]))
        ->assertNotFound();
});

it('redirects an accepted claim even when the provider immediately rejects payout', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => ['url' => 'https://example.com/after-claim'],
    ]));
    $metadata = $voucher->metadata ?? [];
    data_set($metadata, 'disbursement.status', 'failed');
    $voucher->metadata = $metadata;
    $voucher->save();
    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'disbursement',
        'status' => 'succeeded',
        'idempotency_key' => 'rejected-rider-claim',
        'completed_at' => now(),
        'currency' => 'PHP',
    ]);
    $journal = Mockery::mock(ExecutionJournalRecorder::class);
    $journal->shouldReceive('record')->times(3)->andReturn(new ExecutionJournalEntry);
    $this->app->instance(ExecutionJournalRecorder::class, $journal);

    expect(app(ClaimRiderHandoff::class)->prepare($voucher, new SubmitPayCodeClaimResultData(
        voucher_code: (string) $voucher->code,
        claim_type: 'disbursement',
        claimed: true,
        status: 'succeeded',
        disbursement: ['status' => 'FAILED'],
    ), 'rejected-rider-claim'))->toBeTrue();

    $this->get(route('x-change.claim.redirect', ['code' => $voucher->code]))
        ->assertRedirect('https://example.com/after-claim');
});

it('returns not found when voucher does not exist', function () {
    $this->withoutMiddleware();

    $this->get(route('x-change.claim.redirect', [
        'code' => 'MISSING-CODE',
    ]))
        ->assertNotFound();
});

it('returns not found when voucher has no rider url', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'message' => 'SUCCESS DEMO: Thank you for claiming.',
                'url' => null,
            ],
        ],
    ));

    $this->get(route('x-change.claim.redirect', [
        'code' => $voucher->code,
    ]))
        ->assertNotFound();
});
