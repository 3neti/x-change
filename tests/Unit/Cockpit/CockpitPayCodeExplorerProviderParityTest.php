<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Services\Cockpit\VoucherLifecycleCockpitReadModelProvider;

function cockpitWave30VoucherLifecycle(array $rows): VoucherLifecycleServiceContract
{
    return new class($rows) implements VoucherLifecycleServiceContract
    {
        /**
         * @param  array<int, array<string, mixed>>  $rows
         */
        public function __construct(private readonly array $rows) {}

        public function list(array $filters = []): array
        {
            return $this->rows;
        }

        public function show(string $voucher): mixed
        {
            return null;
        }

        public function showByCode(string $code): mixed
        {
            return null;
        }

        public function status(string $voucher): mixed
        {
            return null;
        }

        public function cancel(string $voucher, array $payload = []): mixed
        {
            return [];
        }

        public function expire(string $voucher, array $payload = []): mixed
        {
            return [];
        }
    };
}

it('filters cockpit pay code explorer records using legacy index search and status semantics', function () {
    $provider = new VoucherLifecycleCockpitReadModelProvider(cockpitWave30VoucherLifecycle([
        [
            'code' => 'pc-active-001',
            'template' => 'Money Changer',
            'capability' => [
                'key' => 'collection',
                'label' => 'Collection',
                'voucher_type_label' => 'Payable',
            ],
            'instruction_badges' => [
                ['key' => 'vendor_bound', 'label' => 'Vendor-bound'],
                ['key' => 'otp', 'label' => 'OTP'],
            ],
            'party' => [
                'state' => 'targeted',
                'label' => 'Vendor',
                'primary' => 'TESTSHOP',
                'masked' => false,
            ],
            'formatted_amount' => 'PHP 25.00',
            'currency' => 'PHP',
            'mobile' => '09173011987',
            'status' => 'active',
            'owner' => 'Treasury',
        ],
        [
            'code' => 'pc-approval-001',
            'amount' => 100,
            'currency' => 'PHP',
            'approval' => ['required' => true],
            'account_number' => 'ACCOUNT-555',
        ],
        [
            'code' => 'pc-redeemed-001',
            'amount' => 50,
            'currency' => 'PHP',
            'redeemed_at' => '2026-07-01T10:00:00+08:00',
        ],
        [
            'code' => 'pc-expired-001',
            'amount' => 75,
            'currency' => 'PHP',
            'expires_at' => '2020-01-01T00:00:00+08:00',
        ],
    ]));

    $readModel = $provider->forPayCodeList(new CockpitReadModelQueryData(
        payCodeSearch: 'account-555',
        payCodeStatus: 'awaiting_approval',
    ));

    expect($readModel->toArray())->toMatchArray([
        'status' => 'available',
        'authorized' => true,
        'query' => 'ACCOUNT-555',
        'status_filter' => 'awaiting_approval',
        'stats' => [
            'total' => 4,
            'active' => 1,
            'awaiting_approval' => 1,
            'redeemed' => 1,
            'expired' => 1,
            'pending' => 0,
            'failed' => 0,
            'filtered' => 1,
        ],
    ])
        ->and($readModel->records)->toHaveCount(1)
        ->and($readModel->records[0]->code)->toBe('PC-APPROVAL-001')
        ->and($readModel->records[0]->status)->toBe('awaiting_approval')
        ->and($readModel->records[0]->display_status)->toBe('awaiting_approval')
        ->and($readModel->records[0]->actions)->toHaveCount(4)
        ->and($readModel->records[0]->actions[0]->toArray())->toMatchArray([
            'key' => 'detail',
            'label' => 'View details',
            'enabled' => true,
            'read_only' => true,
            'href' => '/x/cockpit/pay-codes/PC-APPROVAL-001',
        ])
        ->and($readModel->records[0]->actions[1]->toArray())->toMatchArray([
            'key' => 'distribution',
            'label' => 'Distribution',
            'enabled' => true,
            'read_only' => true,
            'href' => '/x/cockpit/pay-codes/PC-APPROVAL-001/distribution',
        ])
        ->and($readModel->records[0]->actions[2]->toArray())->toMatchArray([
            'key' => 'timeline',
            'enabled' => false,
            'read_only' => true,
        ])
        ->and($readModel->records[0]->actions[3]->toArray())->toMatchArray([
            'key' => 'notify',
            'enabled' => false,
            'read_only' => true,
        ])
        ->and($readModel->filters)->toHaveCount(9)
        ->and($readModel->filters[0]->toArray())->toMatchArray([
            'key' => 'search',
            'value' => 'ACCOUNT-555',
            'active' => true,
            'read_only' => true,
        ])
        ->and(collect($readModel->filters)->firstWhere('value', 'awaiting_approval')->active)->toBeTrue()
        ->and($readModel->toArray())->not->toHaveKey('account_number')
        ->and($readModel->toArray())->not->toHaveKey('mobile');
});

it('filters canonical operational outcomes and preserves legacy aliases', function () {
    $provider = new VoucherLifecycleCockpitReadModelProvider(cockpitWave30VoucherLifecycle([
        ['code' => 'pc-active-001', 'display_status' => 'active'],
        ['code' => 'pc-locked-001', 'display_status' => 'locked'],
        ['code' => 'pc-paid-001', 'display_status' => 'paid'],
        ['code' => 'pc-redeemed-001', 'display_status' => 'redeemed'],
        [
            'code' => 'pc-rejected-001',
            'display_status' => 'payout_rejected',
            'attention' => [
                'key' => 'payout_rejected',
                'label' => 'Payout Rejected',
                'message' => 'Destination correction required.',
            ],
        ],
    ]));

    $readModel = $provider->forPayCodeList(new CockpitReadModelQueryData(
        payCodeStatus: ' REDEEMED ',
    ));

    expect($readModel->status_filter)->toBe('completed')
        ->and(collect($readModel->records)->pluck('code')->all())->toBe([
            'PC-PAID-001',
            'PC-REDEEMED-001',
        ])
        ->and(collect($readModel->filters)->firstWhere('value', 'completed')->active)->toBeTrue()
        ->and(collect($readModel->filters)->pluck('value')->all())->toBe([
            '',
            'all',
            'active',
            'awaiting_approval',
            'processing',
            'completed',
            'needs_attention',
            'expired',
            'cancelled',
        ]);

    $attention = $provider->forPayCodeList(new CockpitReadModelQueryData(
        payCodeStatus: 'failed',
    ));

    expect($attention->status_filter)->toBe('needs_attention')
        ->and($attention->records)->toHaveCount(1)
        ->and($attention->records[0]->code)->toBe('PC-REJECTED-001');
});

it('keeps only typed operational summaries in cockpit list records', function () {
    $provider = new VoucherLifecycleCockpitReadModelProvider(cockpitWave30VoucherLifecycle([
        [
            'code' => 'pc-summary-001',
            'status' => 'paid',
            'display_status' => 'paid',
            'voucher_status' => 'expired',
            'operational_status' => [
                'key' => 'paid',
                'label' => 'Paid',
                'tone' => 'positive',
                'availability_key' => 'closed',
                'availability_label' => 'Closed',
                'settlement_outcome' => 'succeeded',
                'is_terminal' => true,
                'can_claim' => false,
                'can_retry_payout' => false,
                'provider_payload' => 'must-not-leak',
            ],
            'capability' => [
                'key' => 'settlement',
                'label' => 'Settlement',
                'voucher_type_label' => 'Bidirectional',
            ],
            'instruction_badges' => [
                ['key' => 'mobile_bound', 'label' => 'Mobile-bound'],
                ['key' => '', 'label' => 'Ignored'],
            ],
            'party' => [
                'state' => 'targeted',
                'label' => 'For',
                'primary' => '•••• 1987',
                'masked' => true,
            ],
            'timing' => [
                'created_at' => '2026-07-27T08:00:00+08:00',
                'expires_at' => '2026-08-03T08:00:00+08:00',
            ],
            'instructions' => [
                'cash' => [
                    'validation' => [
                        'mobile' => '09173011987',
                    ],
                ],
            ],
        ],
    ]));

    $record = $provider->forPayCodeList(new CockpitReadModelQueryData)->records[0]->toArray();

    expect($record)->toMatchArray([
        'capability' => [
            'key' => 'settlement',
            'label' => 'Settlement',
            'voucher_type_label' => 'Bidirectional',
        ],
        'instruction_badges' => [
            ['key' => 'mobile_bound', 'label' => 'Mobile-bound'],
        ],
        'party' => [
            'state' => 'targeted',
            'label' => 'For',
            'primary' => '•••• 1987',
            'secondary' => null,
            'masked' => true,
        ],
        'timing' => [
            'created_at' => '2026-07-27T08:00:00+08:00',
            'starts_at' => null,
            'expires_at' => '2026-08-03T08:00:00+08:00',
            'redeemed_at' => null,
        ],
    ])
        ->and(json_encode($record))->not->toContain('09173011987')
        ->and($record)->not->toHaveKey('instructions');
});

it('url-encodes cockpit pay code explorer row action destinations', function () {
    $provider = new VoucherLifecycleCockpitReadModelProvider(cockpitWave30VoucherLifecycle([
        ['code' => 'pc weird/001', 'status' => 'active'],
    ]));

    $readModel = $provider->forPayCodeList(new CockpitReadModelQueryData);

    expect($readModel->records)->toHaveCount(1)
        ->and($readModel->records[0]->code)->toBe('PC WEIRD/001')
        ->and($readModel->records[0]->actions[0]->href)->toBe('/x/cockpit/pay-codes/PC%20WEIRD%2F001')
        ->and($readModel->records[0]->actions[1]->href)->toBe('/x/cockpit/pay-codes/PC%20WEIRD%2F001/distribution');
});

it('normalizes unknown cockpit pay code explorer status filters to all records', function () {
    $provider = new VoucherLifecycleCockpitReadModelProvider(cockpitWave30VoucherLifecycle([
        ['code' => 'pc-one', 'status' => 'active'],
        ['code' => 'pc-two', 'status' => 'failed'],
    ]));

    $readModel = $provider->forPayCodeList(new CockpitReadModelQueryData(
        payCodeStatus: 'not-a-status',
    ));

    expect($readModel->status_filter)->toBeNull()
        ->and($readModel->records)->toHaveCount(2)
        ->and($readModel->stats->filtered)->toBe(2)
        ->and(collect($readModel->filters)->firstWhere('value', 'all')->active)->toBeTrue();
});
