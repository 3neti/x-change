<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\XChange\Services\WithdrawalPendingDisbursementRecorder;

it('records pending disbursement metadata on voucher', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
    ));

    $input = PayoutRequestData::from([
        'reference' => $voucher->code.'-09173011987-S1',
        'amount' => 100.00,
        'account_number' => '09173011987',
        'bank_code' => 'GXCHPHM2XXX',
        'settlement_rail' => 'INSTAPAY',
    ]);

    $bankRegistry = Mockery::mock(BankRegistry::class);
    $bankRegistry->shouldReceive('getBankName')
        ->once()
        ->with('GXCHPHM2XXX')
        ->andReturn('GCash');

    $bankRegistry->shouldReceive('getBankLogo')
        ->once()
        ->with('GXCHPHM2XXX')
        ->andReturn('https://example.com/gcash.png');

    $bankRegistry->shouldReceive('isEMI')
        ->once()
        ->with('GXCHPHM2XXX')
        ->andReturn(true);

    $recorder = new WithdrawalPendingDisbursementRecorder($bankRegistry);

    $recorder->record(
        voucher: $voucher,
        input: $input,
        e: new RuntimeException('Provider timeout'),
    );

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'disbursement.gateway'))->toBe('unknown')
        ->and(data_get($voucher->metadata, 'disbursement.transaction_id'))->toBe($input->reference)
        ->and(data_get($voucher->metadata, 'disbursement.status'))->toBe('pending')
        ->and((float) data_get($voucher->metadata, 'disbursement.amount'))->toBe(100.00)
        ->and(data_get($voucher->metadata, 'disbursement.currency'))->toBe('PHP')
        ->and(data_get($voucher->metadata, 'disbursement.settlement_rail'))->toBe('INSTAPAY')
        ->and(data_get($voucher->metadata, 'disbursement.recipient_identifier'))->toBe('09173011987')
        ->and(data_get($voucher->metadata, 'disbursement.recipient_name'))->toBe('GCash')
        ->and(data_get($voucher->metadata, 'disbursement.payment_method'))->toBe('bank_transfer')
        ->and(data_get($voucher->metadata, 'disbursement.error'))->toBe('Provider timeout')
        ->and(data_get($voucher->metadata, 'disbursement.requires_reconciliation'))->toBeTrue()
        ->and(data_get($voucher->metadata, 'disbursement.metadata.bank_code'))->toBe('GXCHPHM2XXX')
        ->and(data_get($voucher->metadata, 'disbursement.metadata.bank_name'))->toBe('GCash')
        ->and(data_get($voucher->metadata, 'disbursement.metadata.bank_logo'))->toBe('https://example.com/gcash.png')
        ->and(data_get($voucher->metadata, 'disbursement.metadata.rail'))->toBe('INSTAPAY')
        ->and(data_get($voucher->metadata, 'disbursement.metadata.is_emi'))->toBeTrue();
});
