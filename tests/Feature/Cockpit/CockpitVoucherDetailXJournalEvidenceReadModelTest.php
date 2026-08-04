<?php

declare(strict_types=1);

use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('hydrates voucher detail with real x-journal read-only evidence entries', function () {
    actingAsTestUser();

    $voucher = issueVoucher(validVoucherInstructions(125.00));

    $legacyEntry = ExecutionJournalEntry::query()->create([
        'reference_number' => 'ERN-COCKPIT-VOUCHER-EVIDENCE-001',
        'event_type' => 'voucher.audit.recorded',
        'occurred_at' => now(),
        'actor_type' => 'operator',
        'actor_id' => 'operator-cockpit-evidence',
        'subject_type' => 'voucher',
        'subject_id' => $voucher->code,
        'correlation_id' => 'corr-cockpit-voucher-evidence',
        'causation_id' => 'cause-cockpit-voucher-evidence',
        'execution_id' => null,
        'actor' => [
            'type' => 'operator',
            'id' => 'operator-cockpit-evidence',
        ],
        'subject' => [
            'type' => 'voucher',
            'id' => $voucher->code,
        ],
        'money' => [
            'amount' => '125.00',
            'currency' => 'PHP',
        ],
        'references' => [
            'code' => $voucher->code,
            'correlation_id' => 'corr-cockpit-voucher-evidence',
        ],
        'payload' => [
            'summary' => 'Voucher evidence summary from x-journal.',
            'raw_payload' => ['secret' => 'must-not-render'],
            'provider_payload' => ['token' => 'must-not-render'],
        ],
        'integrity' => [
            'hash' => 'hash-cockpit-voucher-evidence',
        ],
        'metadata' => [
            'source' => 'x-journal-test-fixture',
            'wallet' => ['balance' => 'must-not-render'],
        ],
    ]);

    $payCodeEntry = $legacyEntry->replicate();
    $payCodeEntry->forceFill([
        'reference_number' => 'ERN-COCKPIT-PAY-CODE-001',
        'event_type' => 'execution.result.recorded',
        'occurred_at' => now()->subSecond(),
        'subject_type' => 'pay_code',
        'subject_id' => $voucher->code,
        'correlation_id' => $voucher->code,
        'subject' => ['type' => 'pay_code', 'id' => $voucher->code],
    ])->save();

    $voucherEntry = $legacyEntry->replicate();
    $voucherEntry->forceFill([
        'reference_number' => 'ERN-COCKPIT-VOUCHER-ID-001',
        'event_type' => 'treasury.pay_code.disbursement.settled',
        'occurred_at' => now()->subSeconds(2),
        'subject_type' => 'voucher',
        'subject_id' => (string) $voucher->getKey(),
        'correlation_id' => 'pay-code-disbursement:'.$voucher->code,
        'subject' => ['type' => 'voucher', 'id' => (string) $voucher->getKey()],
    ])->save();

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/VoucherDetail')
        ->assertJsonPath('props.read_model.code', $voucher->code)
        ->assertJsonPath('props.read_model.journal.status', 'available')
        ->assertJsonPath('props.read_model.journal.authorized', true)
        ->assertJsonPath('props.read_model.journal.redactions.payloads', 'journal-evidence-summary-only')
        ->assertJsonPath('props.read_model.journal.redactions.source', 'x-journal')
        ->assertJsonPath('props.read_model.journal.redactions.evidence_only', true)
        ->assertJsonPath('props.read_model.journal.redactions.writes_journal_entries', false)
        ->assertJsonPath('props.read_model.journal.entries.0.reference_number', 'ERN-COCKPIT-VOUCHER-EVIDENCE-001')
        ->assertJsonPath('props.read_model.journal.entries.0.event_type', 'voucher.audit.recorded')
        ->assertJsonPath('props.read_model.journal.entries.0.subject.id', $voucher->code)
        ->assertJsonMissingPath('props.read_model.journal.entries.0.provider_payload')
        ->assertJsonMissingPath('props.read_model.journal.entries.0.raw_payload')
        ->assertJsonMissingPath('props.read_model.journal.entries.0.wallet')
        ->assertJsonMissingPath('props.read_model.provider_payload')
        ->assertJsonMissingPath('props.read_model.raw_payload')
        ->assertJsonMissingPath('props.read_model.wallet');

    $entry = data_get($response->json(), 'props.read_model.journal.entries.0');

    expect($entry)->toBeArray()
        ->and(data_get($entry, 'payload.summary'))->toBe('Voucher evidence summary from x-journal.')
        ->and(data_get($entry, 'payload.raw_payload'))->toBe('[redacted]')
        ->and(data_get($entry, 'payload.provider_payload'))->toBe('[redacted]')
        ->and(data_get($entry, 'metadata.wallet'))->toBe('[redacted]');

    expect(collect(data_get($response->json(), 'props.read_model.journal.entries'))
        ->pluck('reference_number')
        ->all())
        ->toContain(
            'ERN-COCKPIT-VOUCHER-EVIDENCE-001',
            'ERN-COCKPIT-PAY-CODE-001',
            'ERN-COCKPIT-VOUCHER-ID-001',
        );
});
