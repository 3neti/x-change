<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use LBHurtado\Voucher\Exceptions\RedemptionException;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Tests\Fakes\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('x-change.onboarding.issuer_model', User::class);

    $this->issuer = actingAsTestUser();
    $this->vouchers = app(VoucherAccessContract::class);
    $this->generatePayCode = app(GeneratePayCode::class);
    $this->submitPayCodeClaim = app(SubmitPayCodeClaim::class);
});

function makeClaimPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'mobile' => '639171234567',
        'recipient_country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
        'inputs' => [],
    ], $overrides);
}

it('persists starts_at on generated voucher', function () {
    Date::setTestNow(CarbonImmutable::parse('2026-04-17 08:00:00', 'Asia/Manila'));

    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = [];
    $payload['starts_at'] = '2026-04-17T10:00:00+08:00';

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);
    $voucher->refresh();

    expect($voucher->starts_at)->not->toBeNull();
    expect($voucher->starts_at->toIso8601String())->toBe(
        CarbonImmutable::parse('2026-04-17T10:00:00+08:00')->toIso8601String()
    );
});

it('blocks redemption before starts_at', function () {
    Date::setTestNow(CarbonImmutable::parse('2026-04-17 08:00:00', 'Asia/Manila'));

    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = [];
    $payload['starts_at'] = '2026-04-17T10:00:00+08:00';

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload()
    ))->toThrow(RuntimeException::class, 'Failed to redeem voucher.');
});

it('blocks redemption after expires_at', function () {
    Date::setTestNow(CarbonImmutable::parse('2026-04-17 12:00:00', 'Asia/Manila'));

    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = [];
    $payload['expires_at'] = '2026-04-17T11:00:00+08:00';

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload()
    ))->toThrow(RuntimeException::class, 'Failed to redeem voucher.');
});

it('requires the correct secret', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = [];
    $payload['cash']['validation']['secret'] = '123456';

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'secret' => 'WRONG',
        ])
    ))->toThrow(RedemptionException::class, 'Invalid secret code provided.');

    $result = $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'secret' => '123456',
        ])
    );

    expect(data_get($result, 'claimed', true))->toBeTrue();
});

it('restricts redemption to the configured mobile', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = [];
    $payload['cash']['validation']['mobile'] = '639173011987';

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'mobile' => '639199999999',
        ])
    ))->toThrow(RedemptionException::class, 'This voucher is restricted to a specific mobile number.');
});

it('requires configured input fields before completion', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['name', 'email', 'birth_date'];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'inputs' => [
                'name' => 'Juan Dela Cruz',
            ],
        ])
    ))->toThrow(RedemptionException::class, 'Missing required fields: Email and Birth Date.');
});

/** new tests */
it('allows redemption after starts_at', function () {
    Date::setTestNow(CarbonImmutable::parse('2026-04-17 10:30:00', 'Asia/Manila'));

    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = [];
    $payload['starts_at'] = '2026-04-17T10:00:00+08:00';

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    $result = $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload()
    );

    expect(data_get($result, 'claimed', true))->toBeTrue();
});

it('allows redemption before expires_at', function () {
    Date::setTestNow(CarbonImmutable::parse('2026-04-17 10:30:00', 'Asia/Manila'));

    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = [];
    $payload['expires_at'] = '2026-04-17T11:00:00+08:00';

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    $result = $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload()
    );

    expect(data_get($result, 'claimed', true))->toBeTrue();
});

it('accepts the correct secret', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = [];
    $payload['cash']['validation']['secret'] = '123456';

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    $result = $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'secret' => '123456',
        ])
    );

    expect(data_get($result, 'claimed', true))->toBeTrue();
});

it('accepts the configured mobile', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = [];
    $payload['cash']['validation']['mobile'] = '639173011987';

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    $result = $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'mobile' => '639173011987',
        ])
    );

    expect(data_get($result, 'claimed', true))->toBeTrue();
});

it('completes when all configured input fields are present', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['name', 'email', 'birth_date'];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    $result = $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'inputs' => [
                'name' => 'Juan Dela Cruz',
                'email' => 'juan@example.com',
                'birth_date' => '1990-01-01',
            ],
        ])
    );

    expect(data_get($result, 'claimed', true))->toBeTrue();
});

it('requires otp when configured', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['otp'];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload()
    ))->toThrow(
        \LBHurtado\Voucher\Exceptions\RedemptionException ::class,
        'Missing required fields: Otp.'
    );
});

it('accepts otp when configured and provided', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['otp'];
    $payload['validation']['otp'] = [
        'required' => true,
        'on_failure' => 'block',
    ];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    $result = $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'inputs' => [
                'otp' => [
                    'otp_code' => '123456',
                    'verified_at' => now()->toIso8601String(),
                ],
            ],
        ])
    );

    expect(data_get($result, 'claimed', true))->toBeTrue();
});

it('requires signature when configured', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['signature'];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload()
    ))->toThrow(
        \LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class,
        'Voucher redemption contract validation failed.'
    );
});

it('accepts signature when configured and provided', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['signature'];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    $result = $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'inputs' => [
                'signature' => 'data:image/png;base64,DEMO_SIGNATURE',
            ],
        ])
    );

    expect(data_get($result, 'claimed', true))->toBeTrue();
});

it('requires selfie when configured', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['selfie'];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload()
    ))->toThrow(
        \LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class,
        'Voucher redemption contract validation failed.'
    );
});

it('accepts selfie when configured and provided', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['selfie'];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    $result = $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'inputs' => [
                'selfie' => 'data:image/jpeg;base64,DEMO_SELFIE',
            ],
        ])
    );

    expect(data_get($result, 'claimed', true))->toBeTrue();
});

it('requires location when configured', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['location'];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload()
    ))->toThrow(
        \LBHurtado\Voucher\Exceptions\RedemptionException::class,
        'Location data is required for this voucher.'
    );
});

it('accepts location when configured and provided', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['location'];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    $result = $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'inputs' => [
                'location' => [
                    'lat' => 14.5995,
                    'lng' => 120.9842,
                ],
            ],
        ])
    );

    expect(data_get($result, 'claimed', true))->toBeTrue();
});

it('blocks redemption when location validation is configured and claimant is outside the allowed radius', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['location'];
    $payload['validation']['location'] = [
        'required' => true,
        'target_lat' => 14.5995,
        'target_lng' => 120.9842,
        'radius_meters' => 100,
        'on_failure' => 'block',
    ];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'inputs' => [
                'location' => [
                    'lat' => 14.6095,
                    'lng' => 120.9942,
                ],
            ],
        ])
    ))->toThrow(
        \LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class,
        'Voucher redemption contract validation failed.'
    );
});

it('accepts redemption when location validation is configured and claimant is within the allowed radius', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['location'];
    $payload['validation']['location'] = [
        'required' => true,
        'target_lat' => 14.5995,
        'target_lng' => 120.9842,
        'radius_meters' => 100,
        'on_failure' => 'block',
    ];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    $result = $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'inputs' => [
                'location' => [
                    'lat' => 14.59955,
                    'lng' => 120.98425,
                ],
            ],
        ])
    );

    expect(data_get($result, 'claimed', true))->toBeTrue();
});

it('requires kyc when configured', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['kyc'];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload()
    ))->toThrow(
        \LBHurtado\Voucher\Exceptions\RedemptionException::class,
        'KYC verification is required but not approved. Please complete identity verification.'
    );
});

it('blocks redemption when contact is not kyc approved even if kyc payload is present', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['kyc'];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'inputs' => [
                'kyc' => [
                    'transaction_id' => 'MOCK-KYC-123',
                    'status' => 'approved',
                    'name' => 'Juan Dela Cruz',
                    'id_number' => 'ABC123456',
                    'id_type' => 'National ID',
                ],
            ],
        ])
    ))->toThrow(
        RuntimeException::class,
        'Identity verification required. Please complete KYC before redeeming.'
    );
});

function approveContactKyc(\LBHurtado\Contact\Models\Contact $contact): void
{
    $contact->meta->set('kyc_status', 'approved');
    $contact->save();
}

it('accepts kyc when configured and provided for a kyc-approved contact', function () {
    $payload = validPayCodePayload(25, 'INSTAPAY');
    $payload['issuer_id'] = $this->issuer->id;
    $payload['inputs']['fields'] = ['kyc'];

    $generated = $this->generatePayCode->handle($payload);
    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    $claim = makeClaimPayload([
        'inputs' => [
            'kyc' => [
                'transaction_id' => 'MOCK-KYC-123',
                'status' => 'approved',
                'name' => 'Juan Dela Cruz',
                'id_number' => 'ABC123456',
                'id_type' => 'National ID',
            ],
        ],
    ]);

    /** @var \LBHurtado\Contact\Models\Contact $contact */
    $contact = \LBHurtado\Contact\Models\Contact::query()->firstOrCreate([
        'mobile' => $claim['mobile'],
    ]);

    approveContactKyc($contact);

    $result = $this->submitPayCodeClaim->handle($voucher, $claim);

    expect(data_get($result, 'claimed', true))->toBeTrue();
});
