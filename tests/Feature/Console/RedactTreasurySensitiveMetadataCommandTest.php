<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Actions\Funding\IssueSystemAccountFundingPayCode;
use LBHurtado\XChange\Data\Funding\IssueSystemAccountFundingPayCodeData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('guardedly redacts legacy Treasury secrets without changing money or request hashes', function (): void {
    $system = enableNetbankTreasuryForTests();
    fundTestUserWallet($system, 0);
    $recipient = actingAsTestUser(0);
    fundTestSystemAccountFundingReserve(
        $system,
        50_000,
        'treasury-sensitive-redaction',
    );
    $issuance = app(IssueSystemAccountFundingPayCode::class)->handle(
        new IssueSystemAccountFundingPayCodeData(
            amountMinor: 2_200,
            connectionReference: 'netbank-primary',
            idempotencyReference: 'treasury-sensitive-redaction',
            expiresAt: now()->addDay(),
            recipient: $recipient,
            evidenceReference: 'evidence:treasury-sensitive-redaction',
        ),
    );
    $operation = TreasuryPositionOperation::query()
        ->where(
            'operation_reference',
            $issuance->reservation_operation_reference,
        )
        ->sole();
    $requestHash = $operation->request_hash;
    $sourceBalance = $operation->sourcePosition->balance_minor;
    $destinationBalance = $operation->destinationPosition->balance_minor;
    $legacyMetadata = [
        ...$operation->metadata,
        'pay_code' => 'FUND-LEGACY',
    ];
    DB::table($operation->getTable())
        ->where('id', $operation->getKey())
        ->update([
            'metadata' => json_encode(
                $legacyMetadata,
                JSON_THROW_ON_ERROR,
            ),
        ]);
    DB::table((new Transaction)->getTable())
        ->whereIn('id', [
            $operation->source_transaction_id,
            $operation->destination_transaction_id,
        ])
        ->get()
        ->each(function (object $transaction): void {
            $metadata = json_decode(
                (string) $transaction->meta,
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            $metadata['pay_code'] = 'FUND-LEGACY';
            DB::table((new Transaction)->getTable())
                ->where('id', $transaction->id)
                ->update([
                    'meta' => json_encode(
                        $metadata,
                        JSON_THROW_ON_ERROR,
                    ),
                ]);
        });
    $journalCount = ExecutionJournalEntry::query()->count();

    $previewExit = Artisan::call(
        'x-change:treasury:redact-sensitive-metadata',
        ['--json' => true],
    );
    $previewOutput = Artisan::output();
    $preview = json_decode($previewOutput, true);

    expect($previewExit)->toBe(Command::SUCCESS, $previewOutput)
        ->and($preview['status'])->toBe('ready')
        ->and($preview['candidate_count'])->toBe(3)
        ->and($preview['position_operation_count'])->toBe(1)
        ->and($preview['ledger_transaction_count'])->toBe(2)
        ->and($preview['committed'])->toBeFalse();

    $commitExit = Artisan::call(
        'x-change:treasury:redact-sensitive-metadata',
        [
            '--authorization-reference' => 'security-redaction-test-20260726',
            '--commit' => true,
            '--confirm-security-redaction' => true,
            '--json' => true,
        ],
    );
    $commitOutput = Artisan::output();
    $commit = json_decode($commitOutput, true);
    $operation->refresh();

    expect($commitExit)->toBe(Command::SUCCESS, $commitOutput)
        ->and($commit['status'])->toBe('redacted')
        ->and($commit['request_hashes_changed'])->toBeFalse()
        ->and($commit['money_changed'])->toBeFalse()
        ->and($commit['provider_calls'])->toBeFalse()
        ->and($operation->request_hash)->toBe($requestHash)
        ->and($operation->metadata)->not->toHaveKey('pay_code')
        ->and($operation->sourcePosition->refresh()->balance_minor)
        ->toBe($sourceBalance)
        ->and($operation->destinationPosition->refresh()->balance_minor)
        ->toBe($destinationBalance)
        ->and(Transaction::query()
            ->whereIn('id', [
                $operation->source_transaction_id,
                $operation->destination_transaction_id,
            ])
            ->get()
            ->every(
                static fn (Transaction $transaction): bool => ! array_key_exists(
                    'pay_code',
                    $transaction->meta,
                ),
            ))->toBeTrue()
        ->and(ExecutionJournalEntry::query()->count())
        ->toBe($journalCount + 1);

    $journal = ExecutionJournalEntry::query()
        ->where(
            'event_type',
            'treasury.sensitive_metadata.redacted',
        )
        ->sole();

    expect(json_encode($journal->toArray(), JSON_THROW_ON_ERROR))
        ->not->toContain('FUND-LEGACY')
        ->and($journal->payload['field_values_persisted'])
        ->toBeFalse();

    $replayExit = Artisan::call(
        'x-change:treasury:redact-sensitive-metadata',
        [
            '--authorization-reference' => 'security-redaction-test-20260726',
            '--commit' => true,
            '--confirm-security-redaction' => true,
            '--json' => true,
        ],
    );
    $replayOutput = Artisan::output();
    $replay = json_decode($replayOutput, true);

    expect($replayExit)->toBe(Command::SUCCESS, $replayOutput)
        ->and($replay['status'])->toBe('already_sanitized')
        ->and(ExecutionJournalEntry::query()->count())
        ->toBe($journalCount + 1);
});
