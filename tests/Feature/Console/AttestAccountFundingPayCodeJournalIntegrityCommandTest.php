<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Actions\Funding\IssueSystemAccountFundingPayCode;
use LBHurtado\XChange\Data\Funding\IssueSystemAccountFundingPayCodeData;
use LBHurtado\XChange\Models\SystemAccountFundingPayCodeIssuance;
use LBHurtado\XChange\Services\Funding\AccountFundingPayCodeJournal;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalIdempotencyHasher;
use LBHurtado\XJournal\Services\ExecutionJournalIntegrityHasher;
use LBHurtado\XJournal\Services\JournalIntegrityVerifier;

it('previews and idempotently appends a proved timestamp precision attestation', function (): void {
    [$issuance, $inspection] = legacyTimestampPrecisionLossInspection();
    $voucherId = (int) $issuance->voucher_id;
    $original = $inspection->toArray();
    $operationCount = TreasuryPositionOperation::query()->count();
    $journalCount = ExecutionJournalEntry::query()->count();

    $previewExit = Artisan::call(
        'x-change:funding:attest-pay-code-journal-integrity',
        [
            '--voucher' => [(string) $voucherId],
            '--json' => true,
        ],
    );
    $previewOutput = Artisan::output();
    $preview = json_decode($previewOutput, true);

    expect($previewExit)->toBe(Command::SUCCESS, $previewOutput)
        ->and($preview['status'])->toBe('ready')
        ->and($preview['classification'])
        ->toBe('timestamp_precision_loss')
        ->and($preview['entries'])->toHaveCount(1)
        ->and($preview['entries'][0]['voucher_id'])->toBe($voucherId)
        ->and($preview['entries'][0]['recovered_microseconds'])
        ->toBe(654321)
        ->and($preview['entries'][0]['candidate_count'])->toBe(1)
        ->and($preview['committed'])->toBeFalse()
        ->and(ExecutionJournalEntry::query()->count())
        ->toBe($journalCount);

    $guardExit = Artisan::call(
        'x-change:funding:attest-pay-code-journal-integrity',
        [
            '--voucher' => [(string) $voucherId],
            '--authorization-reference' => 'test-control-20260726',
            '--commit' => true,
            '--json' => true,
        ],
    );
    $guardOutput = Artisan::output();
    $guard = json_decode($guardOutput, true);

    expect($guardExit)->toBe(Command::FAILURE, $guardOutput)
        ->and($guard['status'])->toBe('rejected')
        ->and(ExecutionJournalEntry::query()->count())
        ->toBe($journalCount);

    $commitExit = Artisan::call(
        'x-change:funding:attest-pay-code-journal-integrity',
        [
            '--voucher' => [(string) $voucherId],
            '--authorization-reference' => 'test-control-20260726',
            '--commit' => true,
            '--confirm-append-only-exception' => true,
            '--json' => true,
        ],
    );
    $commitOutput = Artisan::output();
    $commit = json_decode($commitOutput, true);

    expect($commitExit)->toBe(Command::SUCCESS, $commitOutput)
        ->and($commit['status'])->toBe('attested')
        ->and($commit['committed'])->toBeTrue()
        ->and($commit['original_entries_unchanged'])->toBeTrue()
        ->and($commit['base_verifier_remains_unverified'])->toBeTrue()
        ->and($commit['provider_calls'])->toBeFalse()
        ->and($commit['treasury_changed'])->toBeFalse()
        ->and($commit['entries'][0]['attestation_reference_number'])
        ->toStartWith('ERN-')
        ->and($inspection->fresh()?->toArray())->toBe($original)
        ->and(ExecutionJournalEntry::query()->count())
        ->toBe($journalCount + 1)
        ->and(TreasuryPositionOperation::query()->count())
        ->toBe($operationCount);

    $attestation = ExecutionJournalEntry::query()
        ->where(
            'event_type',
            'account_funding.pay_code.integrity_exception_attested',
        )
        ->sole();
    $encodedAttestation = json_encode(
        $attestation->toArray(),
        JSON_THROW_ON_ERROR,
    );

    expect($attestation->payload['classification'])
        ->toBe('timestamp_precision_loss')
        ->and($attestation->payload['original_entry_unchanged'])
        ->toBeTrue()
        ->and($encodedAttestation)->not->toContain('FUND-')
        ->and(collect(app(JournalIntegrityVerifier::class)
            ->verify()
            ->issues)
            ->map(fn (object $issue): string => $issue->code)
            ->all())->toBe(['hash_mismatch']);

    $replayExit = Artisan::call(
        'x-change:funding:attest-pay-code-journal-integrity',
        [
            '--voucher' => [(string) $voucherId],
            '--authorization-reference' => 'test-control-20260726',
            '--commit' => true,
            '--confirm-append-only-exception' => true,
            '--json' => true,
        ],
    );
    $replayOutput = Artisan::output();
    $replay = json_decode($replayOutput, true);

    expect($replayExit)->toBe(Command::SUCCESS, $replayOutput)
        ->and($replay['status'])->toBe('already_attested')
        ->and(ExecutionJournalEntry::query()->count())
        ->toBe($journalCount + 1)
        ->and(TreasuryPositionOperation::query()->count())
        ->toBe($operationCount);
});

/**
 * @return array{
 *     SystemAccountFundingPayCodeIssuance,
 *     ExecutionJournalEntry
 * }
 */
function legacyTimestampPrecisionLossInspection(): array
{
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);
    fundTestSystemAccountFundingReserve(
        $system,
        50_000,
        'journal-integrity-attestation',
    );
    $issuance = app(IssueSystemAccountFundingPayCode::class)->handle(
        new IssueSystemAccountFundingPayCodeData(
            amountMinor: 2_200,
            connectionReference: 'netbank-primary',
            idempotencyReference: 'journal-integrity-attestation',
            expiresAt: now()->addDay(),
            recipient: $recipient,
            evidenceReference: 'evidence:journal-integrity-attestation',
            authorizationReference: 'authorization:journal-integrity-attestation',
        ),
    );
    app(AccountFundingPayCodeJournal::class)->recordInspected(
        (int) $issuance->voucher_id,
        $recipient,
        'test-inspection-token',
    );
    $inspection = ExecutionJournalEntry::query()
        ->where(
            'event_type',
            'account_funding.pay_code.inspected',
        )
        ->sole();
    $recoveredOccurredAt = CarbonImmutable::instance(
        $inspection->occurred_at,
    )->setMicrosecond(654321);
    $entryData = ExecutionJournalEntryData::fromArray([
        'reference_number' => $inspection->reference_number,
        'event_type' => $inspection->event_type,
        'occurred_at' => $recoveredOccurredAt,
        'actor' => $inspection->actor,
        'subject' => $inspection->subject,
        'money' => $inspection->money,
        'references' => $inspection->references,
        'payload' => $inspection->payload,
        'integrity' => $inspection->integrity,
        'metadata' => $inspection->metadata,
        'idempotency_key' => $inspection->idempotency_key,
    ]);
    $integrity = $inspection->integrity;
    $integrity['hash'] = app(
        ExecutionJournalIntegrityHasher::class,
    )->hash(
        $entryData,
        ['previous_hash' => $integrity['previous_hash']],
    );

    DB::table('execution_journal_entries')
        ->where('id', $inspection->getKey())
        ->update([
            'integrity' => json_encode(
                $integrity,
                JSON_THROW_ON_ERROR,
            ),
            'idempotency_fingerprint' => app(
                ExecutionJournalIdempotencyHasher::class,
            )->fingerprint($entryData),
        ]);

    app(DispatchVoucherClaimOutcome::class)->handle(
        voucher: $issuance->voucher,
        requestedOutcome: 'account_funding',
        payload: [],
        claimant: $recipient,
    );

    return [$issuance, $inspection->fresh()];
}
