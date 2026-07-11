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
    };
}

it('filters cockpit pay code explorer records using legacy index search and status semantics', function () {
    $provider = new VoucherLifecycleCockpitReadModelProvider(cockpitWave30VoucherLifecycle([
        [
            'code' => 'pc-active-001',
            'template' => 'Money Changer',
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
        ->and($readModel->filters)->toHaveCount(8)
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
