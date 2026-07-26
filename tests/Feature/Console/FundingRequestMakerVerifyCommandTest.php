<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Tests\Fakes\User;

it('interactively verifies exact backing without moving funds', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $requester = actingAsTestUser(0);
    $maker = User::query()->create([
        'name' => 'Artisan Funding Maker',
        'email' => 'artisan-funding-maker@example.test',
        'password' => 'password',
    ]);
    config()->set('auth.providers.users.model', User::class);
    config()->set('x-change.onboarding.issuer_model', User::class);
    config()->set('x-change.funding.requests.maker_ids', [
        (string) $maker->getKey(),
    ]);
    $request = makerCommandFundingRequest($requester, 22_00);
    $systemReserveBefore = makerCommandPositionBalance(
        $system,
        TreasuryPositionPurpose::AccountFundingReserve,
    );
    $systemPayCodeReserveBefore = makerCommandPositionBalance(
        $system,
        TreasuryPositionPurpose::PayCodeReserve,
    );

    $this->artisan('x-change:funding:maker:verify', [
        'pay-code' => $request->voucher->code,
    ])
        ->expectsQuestion(
            'Maker operator',
            'Artisan Funding Maker · #'.$maker->getKey(),
        )
        ->expectsQuestion(
            'Treasury connection',
            'netbank-primary · NETBANK PHP',
        )
        ->expectsQuestion('Recognized value', null)
        ->expectsQuestion('Independent evidence reference', null)
        ->expectsQuestion('Review notes', null)
        ->expectsConfirmation('Record backing verification?', 'yes')
        ->expectsOutputToContain('status: verified')
        ->expectsOutputToContain('funds_moved: false')
        ->assertSuccessful();

    $prepared = $request->refresh();
    $event = $prepared->events()->where(
        'event_type',
        'backing_prepared_for_approval',
    )->sole();

    expect($prepared->status)->toBe(FundingRequestStatus::AwaitingApproval)
        ->and($prepared->approved_value_minor)->toBe(22_00)
        ->and($prepared->connection_reference)->toBe('netbank-primary')
        ->and($prepared->reviewed_by_id)->toBe((string) $maker->getKey())
        ->and($prepared->evidence_reference)
        ->toBe('manual-verification:'.$prepared->reference)
        ->and($event->actor_id)->toBe((string) $maker->getKey())
        ->and(makerCommandPositionBalance(
            $system,
            TreasuryPositionPurpose::AccountFundingReserve,
        ))->toBe($systemReserveBefore)
        ->and(makerCommandPositionBalance(
            $system,
            TreasuryPositionPurpose::PayCodeReserve,
        ))->toBe($systemPayCodeReserveBefore);

    fakePayoutProvider()->assertNoDisbursementAttempted();
});

it('replays maker verification without another transition', function (): void {
    enableNetbankTreasuryForTests();
    $requester = actingAsTestUser(0);
    $maker = User::query()->create([
        'name' => 'Replay Funding Maker',
        'email' => 'replay-funding-maker@example.test',
        'password' => 'password',
    ]);
    config()->set('auth.providers.users.model', User::class);
    config()->set('x-change.onboarding.issuer_model', User::class);
    config()->set('x-change.funding.requests.maker_ids', [
        (string) $maker->getKey(),
    ]);
    $request = makerCommandFundingRequest($requester, 31_00);
    $arguments = [
        'pay-code' => $request->voucher->code,
        '--operator' => (string) $maker->getKey(),
        '--recognized-value' => '31.00',
        '--connection' => 'netbank-primary',
        '--evidence-reference' => 'bank-match:maker-command-replay',
        '--commit' => true,
        '--json' => true,
    ];

    $firstExit = Artisan::call(
        'x-change:funding:maker:verify',
        $arguments,
    );
    $firstOutput = Artisan::output();
    $first = json_decode($firstOutput, true);
    $replayExit = Artisan::call(
        'x-change:funding:maker:verify',
        [
            'pay-code' => $request->voucher->code,
            '--json' => true,
        ],
    );
    $replay = json_decode(Artisan::output(), true);

    expect($firstExit)->toBe(Command::SUCCESS, $firstOutput)
        ->and($first)->toBeArray()
        ->and($first['status'])->toBe('verified')
        ->and($replayExit)->toBe(Command::SUCCESS)
        ->and($replay['status'])->toBe('already_verified')
        ->and($request->events()->count())->toBe(2);
});

function makerCommandFundingRequest(
    User $requester,
    int $amountMinor,
): FundingRequest {
    return app(CreateFundingRequest::class)->handle(
        new CreateFundingRequestData(
            accountReference: 'wallet:'.$requester->wallet->uuid,
            requesterType: $requester::class,
            requesterId: (string) $requester->getKey(),
            fundingType: FundingRequestType::BankTransfer,
            requestedValueMinor: $amountMinor,
            currency: 'PHP',
            description: 'Artisan maker verification test.',
            idempotencyKey: 'maker-command-'.str()->ulid(),
        ),
    );
}

function makerCommandPositionBalance(
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
