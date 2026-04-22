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

it('records ledger rows for successful open-slice claims', function () {
    $this->markTestSkipped('Enable once open-slice execution is wired to succeed from the first claim.');

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
            'slice_mode' => 'open',
            'max_slices' => 3,
            'min_withdrawal' => 50,
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
            'idempotency_key' => 'feature-open-slice-ledger-001',
        ],
    ]);

    $voucher = Voucher::query()->findOrFail($generated->voucher_id);

    $submit = app(SubmitPayCodeClaim::class);

    $first = $submit->handle($voucher, [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
        'inputs' => [],
        'amount' => 100,
        '_meta' => [
            'idempotency_key' => 'feature-open-slice-ledger-001-claim-1',
        ],
        'reference' => 'FEATURE-OPEN-SLICE-001-1',
    ]);

    $second = $submit->handle($voucher->fresh(), [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
        'inputs' => [],
        'amount' => 50,
        '_meta' => [
            'idempotency_key' => 'feature-open-slice-ledger-001-claim-2',
        ],
        'reference' => 'FEATURE-OPEN-SLICE-001-2',
    ]);

    $claims = VoucherClaim::query()
        ->where('voucher_id', $voucher->id)
        ->orderBy('claim_number')
        ->get();

    expect($first->claim_type)->toBe('withdraw');
    expect($first->claimed)->toBeTrue();
    expect($first->remaining_balance)->toBe(200.0);
    expect($first->fully_claimed)->toBeFalse();

    expect($second->claim_type)->toBe('withdraw');
    expect($second->claimed)->toBeTrue();
    expect($second->remaining_balance)->toBe(150.0);
    expect($second->fully_claimed)->toBeFalse();

    expect($claims)->toHaveCount(2);

    expect($claims[0]->claim_number)->toBe(1);
    expect($claims[0]->claim_type)->toBe('withdraw');
    expect($claims[0]->status)->toBe('succeeded');
    expect($claims[0]->requested_amount_minor)->toBe(10000);
    expect($claims[0]->disbursed_amount_minor)->toBe(10000);
    expect($claims[0]->remaining_balance_minor)->toBe(20000);
    expect($claims[0]->idempotency_key)->toBe('feature-open-slice-ledger-001-claim-1');

    expect($claims[1]->claim_number)->toBe(2);
    expect($claims[1]->claim_type)->toBe('withdraw');
    expect($claims[1]->status)->toBe('succeeded');
    expect($claims[1]->requested_amount_minor)->toBe(5000);
    expect($claims[1]->disbursed_amount_minor)->toBe(5000);
    expect($claims[1]->remaining_balance_minor)->toBe(15000);
    expect($claims[1]->idempotency_key)->toBe('feature-open-slice-ledger-001-claim-2');
});

it('records a failed overdraw attempt appropriately for open-slice vouchers', function () {
    $this->markTestSkipped('Decide first whether failed open-slice attempts should also be persisted in voucher_claims.');

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
            'slice_mode' => 'open',
            'max_slices' => 3,
            'min_withdrawal' => 50,
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
            'idempotency_key' => 'feature-open-slice-ledger-002',
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
        'amount' => 250,
        '_meta' => [
            'idempotency_key' => 'feature-open-slice-ledger-002-claim-1',
        ],
        'reference' => 'FEATURE-OPEN-SLICE-002-1',
    ]);

    expect(fn () => $submit->handle($voucher->fresh(), [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
        'inputs' => [],
        'amount' => 100,
        '_meta' => [
            'idempotency_key' => 'feature-open-slice-ledger-002-claim-2',
        ],
        'reference' => 'FEATURE-OPEN-SLICE-002-2',
    ]))->toThrow(RuntimeException::class);

    $claims = VoucherClaim::query()
        ->where('voucher_id', $voucher->id)
        ->orderBy('claim_number')
        ->get();

    expect($claims->count())->toBeGreaterThanOrEqual(1);
});
