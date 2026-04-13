<?php

declare(strict_types=1);

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Services\VoucherInstructionFormatter;

it('formats voucher instructions as pretty json', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100.00,
            'currency' => 'PHP',
            'settlement_rail' => 'INSTAPAY',
            'fee_strategy' => 'absorb',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => 'otp',
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => ['selfie', 'signature', 'kyc'],
        ],
        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => '09171234567',
            'webhook' => 'https://example.com/hook',
        ],
        'rider' => [
            'message' => 'Bring a valid ID.',
            'url' => 'https://example.com/claim',
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
        'ttl' => 'PT30M',
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'metadata' => [],
    ]);

    $json = VoucherInstructionFormatter::formatAsJson($instructions);

    expect($json)
        ->toBeString()
        ->and($json)->toContain('"amount": 100')
        ->and($json)->toContain('"currency": "PHP"')
        ->and($json)->toContain('"settlement_rail": "INSTAPAY"')
        ->and($json)->toContain('"fee_strategy": "absorb"')
        ->and($json)->toContain('"inputs"')
        ->and($json)->toContain('"selfie"')
        ->and($json)->toContain('"signature"')
        ->and($json)->toContain('"kyc"')
        ->and($json)->toContain('"email": "example@example.com"')
        ->and($json)->toContain('"mobile": "09171234567"')
        ->and($json)->toContain('"webhook": "https://example.com/hook"')
        ->and($json)->toContain('"message": "Bring a valid ID."')
        ->and($json)->toContain('"url": "https://example.com/claim"')
        ->and($json)->toContain('"ttl": "PT30M"');
});

it('formats voucher instructions as human readable text', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100.00,
            'currency' => 'PHP',
            'settlement_rail' => 'INSTAPAY',
            'fee_strategy' => 'absorb',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => ['name', 'signature', 'selfie', 'kyc'],
        ],
        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => '09171234567',
            'webhook' => 'https://example.com/hook',
        ],
        'rider' => [
            'message' => 'Bring a valid ID.',
            'url' => 'https://example.com/claim',
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'metadata' => [],
    ]);

    $text = VoucherInstructionFormatter::formatAsHuman($instructions);

    expect($text)
        ->toBeString()
        ->and($text)->toContain('Amount:')
        ->and($text)->toContain('Rail: INSTAPAY')
        ->and($text)->toContain('fee absorbed by issuer')
        ->and($text)->toContain('Inputs:')
        ->and($text)->toContain('Name')
        ->and($text)->toContain('Signature')
        ->and($text)->toContain('Selfie')
        ->and($text)->toContain('KYC')
        ->and($text)->toContain('Feedback:')
        ->and($text)->toContain('example@example.com')
        ->and($text)->toContain('09171234567')
        ->and($text)->toContain('https://example.com/hook')
        ->and($text)->toContain('Message: Bring a valid ID.');
});

it('formats sms output and truncates long content', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100.00,
            'currency' => 'PHP',
            'settlement_rail' => 'INSTAPAY',
            'fee_strategy' => 'absorb',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => ['name', 'email', 'mobile', 'address', 'birth_date', 'gross_monthly_income', 'signature', 'selfie', 'kyc', 'location', 'otp'],
        ],
        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => '09171234567',
            'webhook' => 'https://example.com/hook',
        ],
        'rider' => [
            'message' => str_repeat('Please complete all requirements carefully. ', 10),
            'url' => 'https://example.com/claim',
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
        'ttl' => 'PT30M',
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'metadata' => [],
    ]);

    $sms = VoucherInstructionFormatter::formatForSms($instructions, 'human');

    expect($sms)
        ->toBeString()
        ->and(mb_strlen($sms))->toBeLessThanOrEqual(200);
});

it('returns null for sms when format is none', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100.00,
            'currency' => 'PHP',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'metadata' => [],
    ]);

    expect(VoucherInstructionFormatter::formatForSms($instructions, 'none'))->toBeNull();
});

it('formats email output without truncation', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100.00,
            'currency' => 'PHP',
            'settlement_rail' => 'INSTAPAY',
            'fee_strategy' => 'absorb',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => ['name', 'signature'],
        ],
        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => 'Bring a valid ID.',
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'metadata' => [],
    ]);

    $email = VoucherInstructionFormatter::formatForEmail($instructions, 'human');

    expect($email)
        ->toBeString()
        ->and($email)->toContain('Amount:')
        ->and($email)->toContain('Inputs:')
        ->and($email)->toContain('Message: Bring a valid ID.');
});

it('formats compact sms content when human output is too long', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100.00,
            'currency' => 'PHP',
            'settlement_rail' => 'INSTAPAY',
            'fee_strategy' => 'absorb',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => ['selfie', 'signature', 'kyc', 'location'],
        ],
        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => '09171234567',
            'webhook' => 'https://example.com/hook',
        ],
        'rider' => [
            'message' => str_repeat('Long rider message. ', 20),
            'url' => 'https://example.com/claim',
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
        'ttl' => 'PT30M',
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'metadata' => [],
    ]);

    $sms = VoucherInstructionFormatter::formatForSms($instructions, 'human');

    expect($sms)
        ->toBeString()
        ->and($sms)->toContain('Inputs:')
        ->and($sms)->toContain('Rail: INSTAPAY');
});
