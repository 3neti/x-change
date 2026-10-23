<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\ClaimValidationDecision;
use LBHurtado\XChange\Services\Claim\ClaimValidationDecisionJournal;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

it('keeps the decision durable and retries a failed journal projection without exposing evidence', function (): void {
    $voucher = Voucher::query()->create([
        'code' => 'JOURNAL-CLAIM',
        'metadata' => ['instructions' => validVoucherInstructions()->toArray()],
        'state' => 'active',
    ]);
    $payload = [
        'mobile' => '09173011987',
        'inputs' => ['name' => 'Amelia Secret', 'otp' => ['code' => '123456']],
        '_meta' => ['idempotency_key' => 'claim-attempt-1'],
    ];
    $recorder = Mockery::mock(ExecutionJournalRecorder::class);
    $recorder->shouldReceive('record')->once()->andThrow(new RuntimeException('Journal unavailable.'));
    $recorder->shouldReceive('record')
        ->once()
        ->withArgs(function (ExecutionJournalEntryData $entry): bool {
            expect($entry->eventType)->toBe('claim.validation.warned')
                ->and($entry->idempotencyKey)->toStartWith('x-change:claim-validation:')
                ->and(json_encode($entry->payload))->not->toContain('Amelia Secret', '123456', '09173011987');

            return true;
        })
        ->andReturn(Mockery::mock(ExecutionJournalEntry::class));
    $journal = new ClaimValidationDecisionJournal($recorder);

    $decision = $journal->record($voucher, $payload, 'warned', [
        ['field' => 'time', 'code' => 'outside_window', 'severity' => 'warn'],
    ]);

    expect($decision->journal_status)->toBe('pending')
        ->and($decision->journal_attempts)->toBe(1)
        ->and(ClaimValidationDecision::query()->count())->toBe(1);

    expect($journal->deliverPending())->toBe(1);
    expect($decision->fresh()->journal_status)->toBe('delivered');
    expect($decision->fresh()->journal_attempts)->toBe(2);

    $sameDecision = $journal->record($voucher, $payload, 'warned', [
        ['field' => 'time', 'code' => 'outside_window', 'severity' => 'warn'],
    ]);

    expect($sameDecision->is($decision))->toBeTrue()
        ->and(ClaimValidationDecision::query()->count())->toBe(1)
        ->and($journal->deliverPending())->toBe(0);
});

it('projects one sanitized validation event to x-journal for a repeated claim attempt', function (): void {
    $voucher = Voucher::query()->create([
        'code' => 'JOURNAL-REAL',
        'metadata' => ['instructions' => validVoucherInstructions()->toArray()],
        'state' => 'active',
    ]);
    $payload = [
        'mobile' => '09173011987',
        'inputs' => ['name' => 'Private Claimant'],
        '_meta' => ['idempotency_key' => 'journal-real-attempt'],
    ];
    $journal = app(ClaimValidationDecisionJournal::class);

    $first = $journal->record($voucher, $payload, 'passed', []);
    $second = $journal->record($voucher, $payload, 'passed', []);
    $entries = ExecutionJournalEntry::query()
        ->where('idempotency_key', 'x-change:claim-validation:'.$first->decision_key)
        ->get();

    expect($second->is($first))->toBeTrue()
        ->and($first->fresh()->journal_status)->toBe('delivered')
        ->and($entries)->toHaveCount(1)
        ->and($entries->first()->event_type)->toBe('claim.validation.passed')
        ->and(json_encode($entries->first()->payload))->not->toContain('Private Claimant', '09173011987');
});
