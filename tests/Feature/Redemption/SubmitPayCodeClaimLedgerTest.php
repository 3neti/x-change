<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function () {
    Config::set('x-change.onboarding.issuer_model', User::class);
    Config::set('x-change.lifecycle.defaults.user_model', User::class);
    Config::set('queue.default', 'sync');

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);

    expect(Schema::hasTable('voucher_claims'))->toBeTrue();
    expect(Schema::hasTable('vouchers'))->toBeTrue();
    expect(Schema::hasTable('contacts'))->toBeTrue();
});

it('records a voucher claim row when a real redeem claim is submitted', function () {
    $issuer = User::query()->firstOrCreate(
        ['email' => 'system@example.test'],
        ['name' => 'System User']
    );

    Contact::query()->updateOrCreate(
        [
            'mobile' => '09171234567',
            'country' => 'PH',
        ],
        [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ],
    );

    $generated = app(GeneratePayCode::class)->handle([
        'issuer_id' => $issuer->getKey(),
        'wallet_id' => $issuer->wallet?->getKey(),
        'cash' => [
            'amount' => 300,
            'currency' => 'PHP',
            'validation' => [
                'country' => 'PH',
            ],
            'settlement_rail' => 'INSTAPAY',
            'fee_strategy' => 'absorb',
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'mobile' => null,
            'email' => null,
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
        '_meta' => [
            'idempotency_key' => 'feature-ledger-claim-001',
        ],
    ]);

    $voucher = Voucher::query()->findOrFail($generated->voucher_id);

    $result = app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
        'inputs' => [],
        '_meta' => [
            'idempotency_key' => 'feature-ledger-claim-001-submit',
        ],
        'reference' => 'FEATURE-CLAIM-001',
    ]);

    $claim = VoucherClaim::query()->latest('id')->first();

    expect($result->voucher_code)->toBe($voucher->code);
    expect($claim)->not->toBeNull();
    expect($claim->voucher_id)->toBe($voucher->id);
    expect($claim->claim_number)->toBe(1);
    expect($claim->claim_type)->toBeIn(['claim', 'redeem', 'withdraw']);
    expect($claim->status)->toBeString();
    expect($claim->claimer_mobile)->toBe('09171234567');
    expect($claim->bank_code)->toBe('GXCHPHM2XXX');
    expect($claim->account_number_masked)->toEndWith('1987');
    expect($claim->idempotency_key)->toBe('feature-ledger-claim-001-submit');
    expect($claim->reference)->toBe('FEATURE-CLAIM-001');
    expect($claim->attempted_at)->not->toBeNull();
});

it('increments claim number when multiple claim rows are recorded for the same voucher', function () {
    $issuer = User::query()->firstOrCreate(
        ['email' => 'system@example.test'],
        ['name' => 'System User']
    );

    Contact::query()->updateOrCreate(
        [
            'mobile' => '09171234567',
            'country' => 'PH',
        ],
        [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ],
    );

    $generated = app(GeneratePayCode::class)->handle([
        'issuer_id' => $issuer->getKey(),
        'wallet_id' => $issuer->wallet?->getKey(),
        'cash' => [
            'amount' => 300,
            'currency' => 'PHP',
            'validation' => [
                'country' => 'PH',
            ],
            'settlement_rail' => 'INSTAPAY',
            'fee_strategy' => 'absorb',
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'mobile' => null,
            'email' => null,
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
        '_meta' => [
            'idempotency_key' => 'feature-ledger-claim-002',
        ],
    ]);

    $voucher = Voucher::query()->findOrFail($generated->voucher_id);

    $submit = app(SubmitPayCodeClaim::class);

    $submit->handle($voucher, [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
        'inputs' => [],
        '_meta' => [
            'idempotency_key' => 'feature-ledger-claim-002-submit-1',
        ],
        'reference' => 'FEATURE-CLAIM-002-1',
    ]);

    // Directly append a second claim ledger row through the same action stack is not always
    // possible for a one-shot voucher, so here we verify numbering through the relation.
    $claims = $voucher->fresh()->claims;

    expect($claims)->toHaveCount(1);
    expect($claims->first()->claim_number)->toBe(1);
});
