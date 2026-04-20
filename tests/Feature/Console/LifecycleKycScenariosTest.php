<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;

beforeEach(function () {
    config()->set('x-change.lifecycle.defaults.user_model', \LBHurtado\XChange\Tests\Fakes\User::class);
    config()->set('x-change.lifecycle.defaults.system_user_email', 'system@example.test');
    config()->set('x-change.lifecycle.defaults.test_user_email', 'lester@hurtado.ph');
    config()->set('x-change.lifecycle.defaults.test_user_mobile', '09173011987');
    config()->set('queue.default', 'sync');

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);

    expect(Schema::hasTable('inputs'))->toBeTrue();
    expect(Schema::hasTable('contacts'))->toBeTrue();
});

it('blocks kyc-required redemption when contact is not approved', function () {
    Contact::query()->updateOrCreate(
        [
            'mobile' => '09171234567',
            'country' => 'PH',
        ],
        [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'kyc_status' => null,
            'kyc_completed_at' => null,
            'kyc_transaction_id' => null,
        ],
    );

    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'kyc_required_unapproved',
    ])
        ->expectsOutputToContain('Running scenario: kyc_required_unapproved')
        ->expectsOutputToContain('FAILED as expected')
        ->expectsOutputToContain('Status check: expected=failed actual=failed')
        ->expectsOutputToContain('Lifecycle scenario completed.')
        ->assertExitCode(0);

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->toBeNull();
});

it('allows kyc-required redemption when contact is approved', function () {
    Contact::query()->updateOrCreate(
        [
            'mobile' => '09171234567',
            'country' => 'PH',
        ],
        [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'kyc_status' => 'approved',
            'kyc_completed_at' => now(),
            'kyc_transaction_id' => 'MOCK-KYC-123',
        ],
    );

    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'kyc_required_approved',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
    ])
        ->expectsOutputToContain('Running scenario: kyc_required_approved')
        ->expectsOutputToContain('SUCCEEDED as expected')
        ->expectsOutputToContain('Status check: expected=succeeded actual=succeeded')
        ->expectsOutputToContain('Lifecycle scenario completed.')
        ->assertExitCode(0);

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->not->toBeNull();
});
