<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\RiderStampRecipientResolverContract;

it('matches the canvas with a privacy-safe mobile recipient label', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'feedback' => [
            'mobile' => '+639467438575',
        ],
    ]));

    $recipient = app(RiderStampRecipientResolverContract::class)
        ->resolve($voucher);

    expect($recipient->visible)->toBeTrue()
        ->and($recipient->eyebrow)->toBe('Prepared for')
        ->and($recipient->label)->toBe('Mobile ending 8575')
        ->and($recipient->label)->not->toContain('+639467438575');
});

it('uses the canvas fallback when no mobile recipient is stored', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'feedback' => [
            'mobile' => null,
        ],
    ]));

    $recipient = app(RiderStampRecipientResolverContract::class)
        ->resolve($voucher);

    expect($recipient->visible)->toBeTrue()
        ->and($recipient->label)->toBe('Anyone with this Pay Code');
});

it('matches the canvas by preserving a vendor alias recipient', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'cash' => [
            'validation' => [
                'payable' => 'MERALCO-BILLER',
            ],
        ],
        'feedback' => [
            'mobile' => null,
        ],
    ]));

    $recipient = app(RiderStampRecipientResolverContract::class)
        ->resolve($voucher);

    expect($recipient->visible)->toBeTrue()
        ->and($recipient->eyebrow)->toBe('Prepared for')
        ->and($recipient->label)->toBe('MERALCO-BILLER');
});

it('can suppress the recipient presentation without changing instructions', function (): void {
    config()->set('x-change.claim.share.recipient.enabled', false);
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'feedback' => [
            'mobile' => '+639467438575',
        ],
    ]));

    $recipient = app(RiderStampRecipientResolverContract::class)
        ->resolve($voucher);

    expect($recipient->visible)->toBeFalse()
        ->and($recipient->eyebrow)->toBe('')
        ->and($recipient->label)->toBe('');
});
