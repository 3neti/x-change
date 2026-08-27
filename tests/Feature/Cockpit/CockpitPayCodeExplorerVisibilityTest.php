<?php

declare(strict_types=1);

use FrittenKeeZ\Vouchers\Config;
use Illuminate\Support\Facades\DB;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Services\Cockpit\PayCodeTerminalControlReadModel;

it('shows an account holder only their own Pay Codes', function () {
    $issuer = actingAsTestUser();
    $visibleVoucher = issueVoucher();

    actingAsTestUser();
    $hiddenVoucher = issueVoucher();

    $this->actingAs($issuer)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index'))
        ->assertOk()
        ->assertJsonPath('props.pay_codes_read_model.records.0.code', $visibleVoucher->code)
        ->assertJsonMissingPath('props.pay_codes_read_model.records.1')
        ->assertJsonMissing(['code' => $hiddenVoucher->code]);
});

it('prefetches terminal eligibility with fixed query cost instead of per-row queries', function () {
    $issuer = actingAsTestUser();
    collect(range(1, 25))->each(fn () => issueVoucher());
    $vouchers = Voucher::query()->with('owner')->get();

    DB::enableQueryLog();
    DB::flushQueryLog();

    app(PayCodeTerminalControlReadModel::class)->forVouchers($vouchers, $issuer);

    $eligibilityQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'voucher_claims')
            || str_contains($query['query'], 'disbursement_reconciliations'));

    expect($eligibilityQueries)->toHaveCount(2);
});

it('projects a claimed contact without exposing the full mobile number', function () {
    $issuer = actingAsTestUser();
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
    $voucher->forceFill(['redeemed_at' => now()])->save();

    $record = collect(app(VoucherLifecycleServiceContract::class)->list([
        'issuer_id' => $issuer->getKey(),
        'issuer_type' => $issuer->getMorphClass(),
        'include' => ['redeemer'],
    ]))->sole();

    expect($record['code'])->toBe($voucher->code)
        ->and($record['party'])->toBe([
            'state' => 'claimed',
            'label' => 'Claimed by',
            'primary' => 'Leslie Chong',
            'secondary' => '•••• 4567',
            'masked' => true,
        ])
        ->and(json_encode($record))->not->toContain('09171234567');
});

it('projects a rejected payout as the primary outcome with destination attention', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher();
    $metadata = $voucher->metadata;

    data_set($metadata, 'disbursement.requires_recovery', true);
    data_set($metadata, 'disbursement.rejection_reason', 'AC01 (Incorrect account number)');

    $voucher->forceFill([
        'metadata' => $metadata,
        'redeemed_at' => now(),
    ])->save();

    $this->actingAs($issuer)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index'))
        ->assertOk()
        ->assertJsonPath('props.pay_codes_read_model.records.0.code', $voucher->code)
        ->assertJsonPath('props.pay_codes_read_model.records.0.status', 'payout_rejected')
        ->assertJsonPath('props.pay_codes_read_model.records.0.attention.key', 'payout_rejected')
        ->assertJsonPath('props.pay_codes_read_model.records.0.attention.label', 'Payout rejected')
        ->assertJsonPath('props.pay_codes_read_model.records.0.attention.message', 'AC01 (Incorrect account number)')
        ->assertJsonPath('props.pay_codes_read_model.records.0.attention.tone', 'critical');
});

it('projects capability instructions target and timing without raw instruction payloads', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'voucher_type' => 'payable',
        'target_amount' => 250,
        'cash' => [
            'validation' => [
                'payable' => 'TESTSHOP',
            ],
        ],
        'validation' => [
            'otp' => [
                'required' => true,
            ],
        ],
        'inputs' => [
            'fields' => ['mobile', 'email', 'name', 'otp', 'selfie'],
        ],
        'rider' => [
            'message' => 'School transport allowance',
        ],
        'metadata' => [
            'collection_wallet_id' => (string) $issuer->wallet()->where('slug', 'platform')->sole()->getKey(),
            'custom' => [
                'external_reference' => 'BPLS-TRANSPORT-2026-0042',
            ],
        ],
    ]));

    $record = collect(app(VoucherLifecycleServiceContract::class)->list([
        'issuer_id' => $issuer->getKey(),
        'issuer_type' => $issuer->getMorphClass(),
        'include' => ['redeemer'],
    ]))->sole();

    expect($record)->toMatchArray([
        'code' => $voucher->code,
        'capability' => [
            'key' => 'collection',
            'label' => 'Collection',
            'voucher_type_label' => 'Payable',
        ],
        'instruction_badges' => [
            ['key' => 'vendor_bound', 'label' => 'Vendor-bound'],
            ['key' => 'settlement_rail', 'label' => 'InstaPay'],
            ['key' => 'input_mobile', 'label' => 'Mobile'],
            ['key' => 'input_email', 'label' => 'Email'],
            ['key' => 'input_name', 'label' => 'Name'],
            ['key' => 'otp', 'label' => 'OTP'],
            ['key' => 'selfie', 'label' => 'Selfie'],
        ],
        'party' => [
            'state' => 'targeted',
            'label' => 'Vendor',
            'primary' => 'TESTSHOP',
            'secondary' => null,
            'masked' => false,
        ],
        'purpose' => 'BPLS-TRANSPORT-2026-0042',
        'consumer_status' => 'payable',
        'collection' => [
            'currency' => 'PHP',
            'target_amount_minor' => 25000,
            'collected_total_minor' => 0,
            'remaining_to_collect_minor' => 25000,
            'is_fully_collected' => false,
            'is_overpaid' => false,
            'overpaid_amount_minor' => 0,
        ],
    ])
        ->and($record['timing']['created_at'])->not->toBeNull()
        ->and(collect($record['instruction_badges'])->pluck('label')->duplicates())->toBeEmpty()
        ->and(collect($record['instruction_badges'])->pluck('label'))->not->toContain('Inputs · 5')
        ->and($record)->not->toHaveKey('instructions');

    $this->actingAs($issuer)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index', ['search' => 'bpls-transport-2026-0042']))
        ->assertOk()
        ->assertJsonPath('props.pay_codes_read_model.records.0.code', $voucher->code)
        ->assertJsonPath('props.pay_codes_read_model.records.0.purpose', 'BPLS-TRANSPORT-2026-0042')
        ->assertJsonPath('props.pay_codes_read_model.records.0.consumer_status', 'payable')
        ->assertJsonPath('props.pay_codes_read_model.records.0.collection.remaining_to_collect_minor', 25000)
        ->assertJsonPath('props.pay_codes_read_model.records.0.terminal_control.status', 'blocked');

    $this->actingAs($issuer)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('props.read_model.voucher.collection.schema', 'x-change.cockpit.pay-code-collection.v1')
        ->assertJsonPath('props.read_model.voucher.collection.consumer_status', 'payable')
        ->assertJsonPath('props.read_model.voucher.collection.target_amount_minor', 25000);
});

it('falls back to the rider message when no external reference is present', function (): void {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'message' => 'Community snacks collection',
        ],
    ]));

    $record = collect(app(VoucherLifecycleServiceContract::class)->list([
        'issuer_id' => $issuer->getKey(),
        'issuer_type' => $issuer->getMorphClass(),
    ]))->sole();

    expect($record['code'])->toBe($voucher->code)
        ->and($record['purpose'])->toBe('Community snacks collection');
});
