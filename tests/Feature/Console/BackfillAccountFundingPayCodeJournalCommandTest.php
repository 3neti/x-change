<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Actions\Funding\IssueSystemAccountFundingPayCode;
use LBHurtado\XChange\Data\Funding\IssueSystemAccountFundingPayCodeData;
use LBHurtado\XChange\Models\SystemAccountFundingPayCodeIssuance;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\JournalIntegrityVerifier;

it('previews and idempotently backfills only missing claim journal events', function (): void {
    [$issuance, $claim] = claimedSystemAccountFundingPayCodeForJournalBackfill();
    $voucherId = (int) $issuance->voucher_id;
    $operationCount = TreasuryPositionOperation::query()->count();

    DB::table('execution_journal_entries')
        ->whereIn('event_type', [
            'account_funding.pay_code.outcome_selected',
            'account_funding.pay_code.applied',
        ])
        ->delete();

    expect(ExecutionJournalEntry::query()->pluck('event_type')->all())
        ->toBe(['account_funding.pay_code.issued']);

    $previewExit = Artisan::call(
        'x-change:funding:backfill-pay-code-journal',
        [
            'voucher' => (string) $voucherId,
            '--json' => true,
        ],
    );
    $previewOutput = Artisan::output();
    $preview = json_decode($previewOutput, true);

    expect($previewExit)->toBe(Command::SUCCESS, $previewOutput)
        ->and($preview['status'])->toBe('ready')
        ->and($preview['event_count'])->toBe(2)
        ->and($preview['events'])->toBe([
            'account_funding.pay_code.outcome_selected',
            'account_funding.pay_code.applied',
        ])
        ->and($preview['committed'])->toBeFalse()
        ->and($preview['provider_calls'])->toBeFalse()
        ->and($preview['treasury_changed'])->toBeFalse()
        ->and(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()->count())->toBe(
            $operationCount,
        );

    $commitExit = Artisan::call(
        'x-change:funding:backfill-pay-code-journal',
        [
            'voucher' => (string) $voucherId,
            '--commit' => true,
            '--json' => true,
        ],
    );
    $commitOutput = Artisan::output();
    $commit = json_decode($commitOutput, true);

    expect($commitExit)->toBe(Command::SUCCESS, $commitOutput)
        ->and($commit['status'])->toBe('backfilled')
        ->and($commit['journal_references'])->toHaveCount(2)
        ->and($commit['treasury_changed'])->toBeFalse()
        ->and(ExecutionJournalEntry::query()
            ->orderBy('id')
            ->pluck('event_type')
            ->all())->toBe([
                'account_funding.pay_code.issued',
                'account_funding.pay_code.outcome_selected',
                'account_funding.pay_code.applied',
            ])
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'account_funding.pay_code.applied')
            ->sole()
            ->references['metadata']['treasury_operation_reference'])
        ->toBe($claim->treasury_operation_reference)
        ->and(TreasuryPositionOperation::query()->count())->toBe(
            $operationCount,
        )
        ->and(app(JournalIntegrityVerifier::class)->verify()->verified)
        ->toBeTrue();

    $replayExit = Artisan::call(
        'x-change:funding:backfill-pay-code-journal',
        [
            'voucher' => (string) $voucherId,
            '--commit' => true,
            '--json' => true,
        ],
    );
    $replayOutput = Artisan::output();
    $replay = json_decode($replayOutput, true);

    expect($replayExit)->toBe(Command::SUCCESS, $replayOutput)
        ->and($replay['status'])->toBe('already_complete')
        ->and($replay['event_count'])->toBe(0)
        ->and(ExecutionJournalEntry::query()->count())->toBe(3)
        ->and(TreasuryPositionOperation::query()->count())->toBe(
            $operationCount,
        );
});

it('rejects an unclaimed voucher without changing its journal or Treasury', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);
    fundTestSystemAccountFundingReserve(
        $system,
        10_000,
        'journal-backfill-unclaimed',
    );
    $issuance = app(IssueSystemAccountFundingPayCode::class)->handle(
        new IssueSystemAccountFundingPayCodeData(
            amountMinor: 1_000,
            connectionReference: 'netbank-primary',
            idempotencyReference: 'journal-backfill-unclaimed',
            expiresAt: now()->addDay(),
            recipient: $recipient,
            evidenceReference: 'evidence:journal-backfill-unclaimed',
            authorizationReference: 'authorization:journal-backfill-unclaimed',
        ),
    );
    $journalCount = ExecutionJournalEntry::query()->count();
    $operationCount = TreasuryPositionOperation::query()->count();

    $exitCode = Artisan::call(
        'x-change:funding:backfill-pay-code-journal',
        [
            'voucher' => (string) $issuance->voucher_id,
            '--commit' => true,
            '--json' => true,
        ],
    );
    $output = Artisan::output();
    $payload = json_decode($output, true);

    expect($exitCode)->toBe(Command::FAILURE, $output)
        ->and($payload['status'])->toBe('rejected')
        ->and($payload['committed'])->toBeFalse()
        ->and($payload['treasury_changed'])->toBeFalse()
        ->and(ExecutionJournalEntry::query()->count())->toBe($journalCount)
        ->and(TreasuryPositionOperation::query()->count())->toBe(
            $operationCount,
        );
});

/**
 * @return array{SystemAccountFundingPayCodeIssuance, VoucherClaim}
 */
function claimedSystemAccountFundingPayCodeForJournalBackfill(): array
{
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);
    fundTestSystemAccountFundingReserve(
        $system,
        50_000,
        'journal-backfill-claimed',
    );
    $issuance = app(IssueSystemAccountFundingPayCode::class)->handle(
        new IssueSystemAccountFundingPayCodeData(
            amountMinor: 1_500,
            connectionReference: 'netbank-primary',
            idempotencyReference: 'journal-backfill-claimed',
            expiresAt: now()->addDay(),
            recipient: $recipient,
            evidenceReference: 'evidence:journal-backfill-claimed',
            authorizationReference: 'authorization:journal-backfill-claimed',
        ),
    );
    $claim = app(DispatchVoucherClaimOutcome::class)->handle(
        voucher: $issuance->voucher,
        requestedOutcome: 'account_funding',
        payload: [],
        claimant: $recipient,
    );

    return [$issuance, $claim];
}
