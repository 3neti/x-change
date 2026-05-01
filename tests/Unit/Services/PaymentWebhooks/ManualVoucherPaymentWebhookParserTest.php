<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\PaymentWebhooks\ManualVoucherPaymentWebhookParser;

it('parses manual payment webhook payload', function () {
    $result = app(ManualVoucherPaymentWebhookParser::class)->parse([
        'voucher_code' => 'PAY-1234',
        'amount' => 100.00,
        'currency' => 'php',
        'status' => 'succeeded',
        'provider_reference' => 'REF-WEBHOOK-1',
        'provider_transaction_id' => 'TXN-WEBHOOK-1',
        'payer' => [
            'name' => 'Juan Dela Cruz',
            'mobile' => '09171234567',
        ],
    ]);

    expect($result->voucher_code)->toBe('PAY-1234')
        ->and($result->status)->toBe('succeeded')
        ->and($result->amount)->toBe(100.00)
        ->and($result->currency)->toBe('PHP')
        ->and($result->provider)->toBe('manual')
        ->and($result->provider_reference)->toBe('REF-WEBHOOK-1')
        ->and(data_get($result->payer, 'mobile'))->toBe('09171234567');
});

it('extracts voucher code from supported payload locations', function () {
    $parser = app(ManualVoucherPaymentWebhookParser::class);

    expect($parser->voucherCode(['voucher_code' => 'A']))->toBe('A')
        ->and($parser->voucherCode(['code' => 'B']))->toBe('B')
        ->and($parser->voucherCode(['metadata' => ['voucher_code' => 'C']]))->toBe('C')
        ->and($parser->voucherCode(['meta' => ['voucher_code' => 'D']]))->toBe('D')
        ->and($parser->voucherCode([]))->toBeNull();
});
