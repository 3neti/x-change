<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XChange\Services\Execution\ExecutionResultJournalPayloadMapper;
use Propaganistas\LaravelPhone\PhoneNumber;

it('maps execution results to safe journal payloads without raw provider data', function () {
    $payload = app(ExecutionResultJournalPayloadMapper::class)->map(
        result: new ExecutionResultData(
            execution_id: 'exec-journal-001',
            successful: true,
            status: 'succeeded',
            driver: 'x_change_live_cash',
            providerReferences: [
                ['type' => 'provider_transaction_id', 'value' => 'TXN-001'],
            ],
            reconciliation: [
                'provider' => 'netbank',
                'destination_account' => [
                    'bank_code' => 'GXCHPHM2XXX',
                    'account_number' => '09173011987',
                    'account_number_masked' => '*******1987',
                ],
                'amount' => [
                    'currency' => 'PHP',
                    'value' => '1250',
                ],
                'raw' => [
                    'destination_account' => [
                        'account_number' => '09173011987',
                    ],
                ],
            ],
            metadata: [
                'destination_account' => [
                    'account_number' => '09173011987',
                    'account_number_masked' => '*******1987',
                ],
                'provider_payload' => [
                    'secret' => true,
                ],
            ],
        ),
        context: executionResultJournalPayloadContext(),
    );

    expect($payload->event_name)->toBe('execution.result.recorded')
        ->and($payload->subject['reference'])->toBe('PC-JOURNAL')
        ->and($payload->references['execution_id'])->toBe('exec-journal-001')
        ->and($payload->payload['reconciliation'])->not->toHaveKey('raw')
        ->and($payload->payload['reconciliation']['destination_account'])->not->toHaveKey('account_number')
        ->and($payload->payload['reconciliation']['destination_account']['account_number_masked'])->toBe('*******1987')
        ->and($payload->payload['metadata'])->not->toHaveKey('provider_payload')
        ->and($payload->payload['metadata']['destination_account'])->not->toHaveKey('account_number')
        ->and($payload->metadata['redactions']['raw_provider_payloads_exposed'])->toBeFalse()
        ->and($payload->metadata['redactions']['unmasked_account_numbers_exposed'])->toBeFalse();
});

function executionResultJournalPayloadContext(): ExecutionContextData
{
    return new ExecutionContextData(
        contact: Contact::fromPhoneNumber(new PhoneNumber('09173011987', 'PH')),
        voucherCode: 'PC-JOURNAL',
        correlation: [
            'idempotency_key' => 'corr-journal',
        ],
    );
}
