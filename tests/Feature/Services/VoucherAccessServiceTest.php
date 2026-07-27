<?php

declare(strict_types=1);

use FrittenKeeZ\Vouchers\Config;
use LBHurtado\XChange\Exceptions\VoucherNotFound;
use LBHurtado\XChange\Exceptions\VoucherNotRedeemable;
use LBHurtado\XChange\Services\VoucherAccessService;

it('finds a voucher by code', function () {
    $voucher = issueVoucher();

    $service = new VoucherAccessService;

    $found = $service->findByCode($voucher->code);

    expect($found)->not->toBeNull();
    expect($found?->is($voucher))->toBeTrue();
});

it('returns null when voucher code does not exist', function () {
    $service = new VoucherAccessService;

    expect($service->findByCode('NOPE-0000'))->toBeNull();
});

it('finds a voucher by code or fails', function () {
    $voucher = issueVoucher();

    $service = new VoucherAccessService;

    $found = $service->findByCodeOrFail($voucher->code);

    expect($found->is($voucher))->toBeTrue();
});

it('throws when find by code or fail cannot locate the voucher', function () {
    $service = new VoucherAccessService;

    expect(fn () => $service->findByCodeOrFail('NOPE-0000'))
        ->toThrow(VoucherNotFound::class, 'NOPE-0000');
});

it('does not throw when a voucher is redeemable', function () {
    $voucher = issueVoucher();

    $service = new VoucherAccessService;

    expect(fn () => $service->assertRedeemable($voucher))
        ->not->toThrow(VoucherNotRedeemable::class);
});

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\RedeemVoucher;

it('throws when a voucher is already redeemed', function () {
    $voucher = issueVoucher();
    $voucher->forceFill([
        'redeemed_at' => now(),
    ])->save();

    $service = new VoucherAccessService;

    expect(fn () => $service->assertRedeemable($voucher->fresh()))
        ->toThrow(VoucherNotRedeemable::class, 'Voucher is already redeemed.');
});

it('throws when a voucher is already redeemed (full pipeline)', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();

    $contact = Contact::factory()->create([
        'mobile' => '09171234567',
        'bank_account' => 'GCASH:09171234567',
    ]);

    expect(RedeemVoucher::run($contact, $voucher->code, [
        'mobile' => '09171234567',
        'country' => 'PH',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ]))->toBeTrue();

    $service = new VoucherAccessService;

    expect(fn () => $service->assertRedeemable($voucher->fresh()))
        ->toThrow(VoucherNotRedeemable::class, sprintf(
            'Voucher [%s] is not redeemable.',
            $voucher->code
        ));
})->skip('Pending full redemption pipeline: requires Contact + HyperVerge + wallet + payout integration to be stabilized in x-change test environment.');

it('scopes voucher lists to the exact issuer morph and identifier', function () {
    $firstIssuer = actingAsTestUser();
    $firstVoucher = issueVoucher();
    $secondIssuer = actingAsTestUser();
    $secondVoucher = issueVoucher();

    $service = new VoucherAccessService;
    $results = collect($service->list([
        'issuer_id' => $firstIssuer->getKey(),
        'issuer_type' => $firstIssuer->getMorphClass(),
    ]));

    expect($results)->toHaveCount(1)
        ->and($results->sole()->is($firstVoucher))->toBeTrue()
        ->and($results->contains(fn ($voucher): bool => $voucher->is($secondVoucher)))->toBeFalse();
});

it('eager loads the redeemer projection only when requested', function () {
    $voucher = issueVoucher();
    $contact = Contact::factory()->create([
        'mobile' => '09171234567',
        'name' => 'Leslie Chong',
        'bank_account' => 'GCASH:09171234567',
    ]);
    $redeemerClass = Config::model('redeemer');
    $redeemer = new $redeemerClass(['metadata' => []]);
    $redeemer->redeemer()->associate($contact);
    $voucher->redeemers()->save($redeemer);

    $service = new VoucherAccessService;
    $result = collect($service->list([
        'include' => ['redeemer'],
    ]))->sole();

    expect($result->relationLoaded('redeemers'))->toBeTrue()
        ->and($result->redeemers->sole()->relationLoaded('redeemer'))->toBeTrue()
        ->and($result->redeemers->sole()->redeemer->is($contact))->toBeTrue();
});
