<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Actions\Funding\PrepareFundingRequest;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Data\Funding\PrepareFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Events\FundingProjectionChanged;
use LBHurtado\XChange\Events\FundingRequestChanged;
use LBHurtado\XChange\Jobs\Funding\PayApprovedFundingRequestJob;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Tests\Fakes\User;

it('interactively approves once and queues one Treasury payment', function (): void {
    Event::fake([
        FundingProjectionChanged::class,
        FundingRequestChanged::class,
    ]);
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    fundTestSystemAccountFundingReserve(
        $system,
        100_00,
        'checker-command-queue-reserve',
    );
    [$request, $maker, $checker] = checkerCommandPreparedRequest(22_00);
    Queue::fake();

    $this->artisan('x-change:funding:checker:approve', [
        'pay-code' => $request->voucher->code,
    ])
        ->expectsQuestion(
            'Checker operator',
            'Artisan Funding Checker · #'.$checker->getKey(),
        )
        ->expectsConfirmation('Approve and fund this Account?', 'yes')
        ->expectsOutputToContain('status: approved_and_queued')
        ->expectsOutputToContain('payment_queued: true')
        ->assertSuccessful();

    $approved = $request->refresh();

    expect($approved->status)->toBe(FundingRequestStatus::PayCodeIssued)
        ->and($approved->approved_by_id)->toBe((string) $checker->getKey())
        ->and($approved->reviewed_by_id)->toBe((string) $maker->getKey())
        ->and($approved->voucher->state)->toBe(VoucherState::ACTIVE)
        ->and(checkerCommandPositionBalance(
            $system,
            TreasuryPositionPurpose::AccountFundingReserve,
        ))->toBe(78_00)
        ->and(checkerCommandPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(22_00);
    Queue::assertPushedOn(
        'x-change-funding',
        PayApprovedFundingRequestJob::class,
    );
    Queue::assertPushed(PayApprovedFundingRequestJob::class, 1);

    $replayExit = Artisan::call(
        'x-change:funding:checker:approve',
        [
            'pay-code' => $request->voucher->code,
            '--operator' => (string) $checker->getKey(),
            '--commit' => true,
            '--json' => true,
        ],
    );
    $replayOutput = Artisan::output();
    $replay = json_decode($replayOutput, true);

    expect($replayExit)->toBe(Command::SUCCESS, $replayOutput)
        ->and($replay['status'])->toBe('already_approved')
        ->and($request->events()
            ->where('event_type', 'reviewed_funding_pay_code_approved')
            ->count())->toBe(1);
    Queue::assertPushed(PayApprovedFundingRequestJob::class, 1);
    fakePayoutProvider()->assertNoDisbursementAttempted();
});

it('completes inline once when the queue is synchronous', function (): void {
    Event::fake([
        FundingProjectionChanged::class,
        FundingRequestChanged::class,
    ]);
    config()->set('queue.default', 'sync');
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    fundTestSystemAccountFundingReserve(
        $system,
        100_00,
        'checker-command-sync-reserve',
    );
    [$request, , $checker, $requester] = checkerCommandPreparedRequest(17_00);
    $arguments = [
        'pay-code' => $request->voucher->code,
        '--operator' => (string) $checker->getKey(),
        '--commit' => true,
        '--json' => true,
    ];

    $exit = Artisan::call(
        'x-change:funding:checker:approve',
        $arguments,
    );
    $output = Artisan::output();
    $result = json_decode($output, true);

    expect($exit)->toBe(Command::SUCCESS, $output)
        ->and($result['status'])->toBe('funded')
        ->and($request->refresh()->status)->toBe(FundingRequestStatus::Completed)
        ->and($request->voucher->refresh()->state)->toBe(VoucherState::CLOSED)
        ->and(VoucherCollection::query()->count())->toBe(1)
        ->and(checkerCommandPositionBalance(
            $system,
            TreasuryPositionPurpose::AccountFundingReserve,
        ))->toBe(83_00)
        ->and(checkerCommandPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe(0)
        ->and(checkerCommandPositionBalance(
            $requester,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe(17_00);

    $replayExit = Artisan::call(
        'x-change:funding:checker:approve',
        $arguments,
    );
    $replay = json_decode(Artisan::output(), true);

    expect($replayExit)->toBe(Command::SUCCESS)
        ->and($replay['status'])->toBe('already_approved')
        ->and(VoucherCollection::query()->count())->toBe(1)
        ->and(checkerCommandPositionBalance(
            $requester,
            TreasuryPositionPurpose::ClientFunds,
        ))->toBe(17_00);

    fakePayoutProvider()->assertNoDisbursementAttempted();
});

/**
 * @return array{FundingRequest, User, User, User}
 */
function checkerCommandPreparedRequest(int $amountMinor): array
{
    $requester = actingAsTestUser(0);
    $maker = User::query()->create([
        'name' => 'Artisan Funding Maker',
        'email' => 'checker-command-maker+'.str()->ulid().'@example.test',
        'password' => 'password',
    ]);
    $checker = User::query()->create([
        'name' => 'Artisan Funding Checker',
        'email' => 'checker-command-checker+'.str()->ulid().'@example.test',
        'password' => 'password',
    ]);
    config()->set('auth.providers.users.model', User::class);
    config()->set('x-change.onboarding.issuer_model', User::class);
    config()->set('x-change.funding.requests.maker_ids', [
        (string) $maker->getKey(),
    ]);
    config()->set('x-change.funding.requests.checker_ids', [
        (string) $checker->getKey(),
    ]);
    $request = app(CreateFundingRequest::class)->handle(
        new CreateFundingRequestData(
            accountReference: 'wallet:'.$requester->wallet->uuid,
            requesterType: $requester::class,
            requesterId: (string) $requester->getKey(),
            fundingType: FundingRequestType::BankTransfer,
            requestedValueMinor: $amountMinor,
            currency: 'PHP',
            description: 'Artisan checker approval test.',
            idempotencyKey: 'checker-command-'.str()->ulid(),
        ),
    );
    $prepared = app(PrepareFundingRequest::class)->handle(
        $request,
        new PrepareFundingRequestData(
            recognizedValueMinor: $amountMinor,
            currency: 'PHP',
            connectionReference: 'netbank-primary',
            evidenceReference: 'bank-match:checker-command-'.$request->reference,
            reviewerType: $maker::class,
            reviewerId: (string) $maker->getKey(),
        ),
    );

    return [$prepared, $maker, $checker, $requester];
}

function checkerCommandPositionBalance(
    User $owner,
    TreasuryPositionPurpose $purpose,
): int {
    $principal = app(
        TreasuryPrincipalReferenceResolverContract::class,
    )->resolve($owner);

    return collect(
        app(TreasuryPositionReadModelContract::class)->forPrincipal($principal),
    )
        ->first(
            fn ($position): bool => $position->purpose === $purpose,
        )
        ?->balanceMinor ?? 0;
}
