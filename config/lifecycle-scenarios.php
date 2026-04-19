<?php

declare(strict_types=1);

use App\Models\User;
use Bavix\Wallet\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\VoucherAccessContract;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedInstructionItemsFromPricingConfig();

    $this->systemUser = ensureLifecycleUser(
        email: (string) config('x-change.lifecycle.defaults.system_user_email', 'system@example.test'),
        mobile: (string) config('x-change.lifecycle.defaults.system_user_mobile', '639178251991'),
        name: 'System User',
    );

    $this->testUser = ensureLifecycleUser(
        email: (string) config('x-change.lifecycle.defaults.test_user_email', 'lester@hurtado.ph'),
        mobile: (string) config('x-change.lifecycle.defaults.test_user_mobile', '09173011987'),
        name: 'Lifecycle Test User',
    );

    fundLifecycleWallets($this->systemUser, $this->testUser);

    $this->vouchers = app(VoucherAccessContract::class);
    $this->generatePayCode = app(GeneratePayCode::class);
    $this->submitPayCodeClaim = app(SubmitPayCodeClaim::class);
});

function seedInstructionItemsFromPricingConfig(): void
{
    $items = (array) config('x-change.pricelist', []);

    foreach ($items as $index => $data) {
        if (! is_array($data)) {
            continue;
        }

        $meta = [
            'description' => $data['description'] ?? null,
            'label' => $data['label'] ?? null,
            'category' => $data['category'] ?? 'other',
        ];

        if (! empty($data['deprecated'])) {
            $meta['deprecated'] = true;
            $meta['deprecated_reason'] = $data['deprecated_reason'] ?? 'No longer in use';
        }

        DB::table('instruction_items')->updateOrInsert(
            ['index' => $index],
            [
                'name' => inferInstructionItemName($index, $data),
                'type' => inferInstructionItemType($index, $data),
                'price' => (int) ($data['price'] ?? 0),
                'currency' => (string) ($data['currency'] ?? 'PHP'),
                'meta' => json_encode($meta, JSON_UNESCAPED_SLASHES),
                'revenue_destination_type' => null,
                'revenue_destination_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

function inferInstructionItemName(string $index, array $data): string
{
    if (! empty($data['label']) && is_string($data['label'])) {
        return $data['label'];
    }

    return str($index)->replace(['.', '_'], ' ')->title()->toString();
}

function inferInstructionItemType(string $index, array $data): string
{
    if (! empty($data['category']) && is_string($data['category'])) {
        return $data['category'];
    }

    return match (true) {
        str_starts_with($index, 'inputs.fields.') => 'input_fields',
        str_starts_with($index, 'feedback.') => 'feedback',
        str_starts_with($index, 'validation.') => 'validation',
        str_starts_with($index, 'cash.validation.') => 'validation',
        str_starts_with($index, 'rider.') => 'rider',
        str_starts_with($index, 'voucher_type.') => 'base',
        $index === 'cash.amount' => 'base',
        default => 'other',
    };
}

function ensureLifecycleUser(string $email, string $mobile, string $name): User
{
    /** @var User $user */
    $user = User::query()->firstOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'password' => bcrypt('password'),
        ]
    );

    if ($mobile !== '' && $user instanceof HasMobileChannel && $user->getMobileChannel() !== $mobile) {
        $user->setMobileChannel($mobile);
        $user->refresh();
    }

    return $user;
}

function fundLifecycleWallets(User $systemUser, User $testUser): void
{
    $systemFloat = (float) config('x-change.lifecycle.defaults.system_float', 1_000_000);
    $userFloat = (float) config('x-change.lifecycle.defaults.user_float', 10_000);

    if ($systemFloat > 0 && method_exists($systemUser, 'depositFloat')) {
        $systemUser->depositFloat($systemFloat);
    }

    if ($userFloat > 0 && $systemUser->getKey() !== $testUser->getKey() && method_exists($systemUser, 'transferFloat')) {
        $systemUser->transferFloat($testUser, $userFloat);
    }
}

function makeBaseVoucherInput(array $overrides = []): array
{
    return array_replace_recursive([
        'issuer_id' => 1,
        'wallet_id' => 1,
        'cash' => [
            'amount' => 25,
            'currency' => 'PHP',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
                'mobile_verification' => null,
            ],
            'settlement_rail' => 'INSTAPAY',
            'fee_strategy' => 'absorb',
            'slice_mode' => null,
            'slices' => null,
            'max_slices' => null,
            'min_withdrawal' => null,
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
        'ttl' => null,
        'starts_at' => null,
        'expires_at' => null,
        'validation' => null,
        'metadata' => null,
        'voucher_type' => null,
        'target_amount' => null,
        'rules' => null,
        '_meta' => [
            'idempotency_key' => 'test-'.str()->uuid(),
        ],
    ], $overrides);
}

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

it('blocks redemption before starts_at', function () {
    Date::setTestNow(CarbonImmutable::parse('2026-04-17 08:00:00', 'Asia/Manila'));

    $generated = $this->generatePayCode->handle(makeBaseVoucherInput([
        'starts_at' => '2026-04-17T10:00:00+08:00',
    ]));

    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload()
    ))->toThrow(Throwable::class);
});

it('blocks redemption after expires_at', function () {
    Date::setTestNow(CarbonImmutable::parse('2026-04-17 12:00:00', 'Asia/Manila'));

    $generated = $this->generatePayCode->handle(makeBaseVoucherInput([
        'expires_at' => '2026-04-17T11:00:00+08:00',
    ]));

    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload()
    ))->toThrow(Throwable::class);
});

it('requires the correct secret', function () {
    $generated = $this->generatePayCode->handle(makeBaseVoucherInput([
        'cash' => [
            'validation' => [
                'secret' => '123456',
            ],
        ],
    ]));

    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'secret' => 'WRONG',
        ])
    ))->toThrow(Throwable::class);

    $result = $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'secret' => '123456',
        ])
    );

    expect(data_get($result, 'claimed', true))->toBeTrue();
});

it('restricts redemption to the configured mobile', function () {
    $generated = $this->generatePayCode->handle(makeBaseVoucherInput([
        'cash' => [
            'validation' => [
                'mobile' => '639173011987',
            ],
        ],
    ]));

    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'mobile' => '639199999999',
        ])
    ))->toThrow(Throwable::class);
});

it('requires configured input fields before completion', function () {
    $generated = $this->generatePayCode->handle(makeBaseVoucherInput([
        'inputs' => [
            'fields' => ['name', 'email', 'birth_date'],
        ],
    ]));

    $voucher = $this->vouchers->findByCodeOrFail($generated->code);

    expect(fn () => $this->submitPayCodeClaim->handle(
        $voucher,
        makeClaimPayload([
            'inputs' => [
                'name' => 'Juan Dela Cruz',
            ],
        ])
    ))->toThrow(Throwable::class);
});
