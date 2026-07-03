<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Exceptions\VoucherNotFound;
use LBHurtado\XChange\Services\Cockpit\NullCockpitReadModelProvider;
use LBHurtado\XChange\Services\Cockpit\VoucherLifecycleCockpitReadModelProvider;

it('builds a cockpit read model query without implying side effects', function () {
    $query = new CockpitReadModelQueryData(
        code: 'PC-READY-001',
        operatorId: 'operator-1',
        include: ['voucher', 'execution', 'journal', 'actions', 'feedback'],
        correlationId: 'corr-1',
    );

    expect($query->code)->toBe('PC-READY-001')
        ->and($query->operatorId)->toBe('operator-1')
        ->and($query->include)->toBe(['voucher', 'execution', 'journal', 'actions', 'feedback'])
        ->and($query->correlationId)->toBe('corr-1');
});

it('returns an empty not wired cockpit read model bundle by default', function () {
    $provider = new NullCockpitReadModelProvider;

    $bundle = $provider->forVoucher(new CockpitReadModelQueryData(
        code: 'PC-READY-001',
        include: ['voucher', 'execution', 'journal', 'actions', 'feedback'],
    ));

    expect($bundle->code)->toBe('PC-READY-001')
        ->and($bundle->voucher->status)->toBe('not_wired')
        ->and($bundle->voucher->authorized)->toBeFalse()
        ->and($bundle->voucher->summary)->toBe([])
        ->and($bundle->execution->status)->toBe('not_wired')
        ->and($bundle->execution->events)->toBe([])
        ->and($bundle->journal->status)->toBe('not_wired')
        ->and($bundle->journal->entries)->toBe([])
        ->and($bundle->actions->status)->toBe('not_wired')
        ->and($bundle->actions->actions)->toBe([])
        ->and($bundle->feedback->status)->toBe('not_wired')
        ->and($bundle->feedback->deliveries)->toBe([]);
});

it('serializes cockpit read model placeholders without broad payload exposure', function () {
    $bundle = (new NullCockpitReadModelProvider)->forVoucher(new CockpitReadModelQueryData(
        code: 'PC-READY-001',
    ));

    expect($bundle->toArray())->toBe([
        'code' => 'PC-READY-001',
        'voucher' => [
            'code' => 'PC-READY-001',
            'status' => 'not_wired',
            'summary' => [],
            'redactions' => ['payloads' => 'not-loaded'],
            'authorized' => false,
        ],
        'execution' => [
            'execution_id' => null,
            'status' => 'not_wired',
            'driver' => null,
            'events' => [],
            'metadata' => [],
            'redactions' => ['payloads' => 'not-loaded'],
            'authorized' => false,
        ],
        'journal' => [
            'status' => 'not_wired',
            'entries' => [],
            'redactions' => ['payloads' => 'not-loaded'],
            'authorized' => false,
        ],
        'actions' => [
            'status' => 'not_wired',
            'actions' => [],
            'diagnostics' => [],
            'redactions' => ['payloads' => 'not-loaded'],
            'authorized' => false,
        ],
        'feedback' => [
            'status' => 'not_wired',
            'deliveries' => [],
            'redactions' => ['payloads' => 'not-loaded'],
            'authorized' => false,
        ],
    ]);
});

it('adapts voucher lifecycle details into a sanitized cockpit voucher summary', function () {
    $lifecycle = new class implements VoucherLifecycleServiceContract
    {
        public string $requestedCode = '';

        public function list(array $filters = []): array
        {
            return [];
        }

        public function show(string $voucher): mixed
        {
            return null;
        }

        public function showByCode(string $code): mixed
        {
            $this->requestedCode = $code;

            return [
                'id' => 123,
                'voucher_id' => 123,
                'code' => $code,
                'amount' => 1500.75,
                'currency' => 'PHP',
                'status' => 'issued',
                'display_status' => 'ready',
                'issuer_id' => 45,
                'claimed' => false,
                'fully_claimed' => false,
                'created_at' => '2026-07-03T10:00:00+08:00',
                'starts_at' => '2026-07-03T11:00:00+08:00',
                'expires_at' => '2026-07-10T11:00:00+08:00',
                'redeemed_at' => null,
                'instructions' => ['provider_payload' => ['secret' => 'should-not-leak']],
                'claims' => [['mobile' => '+639171234567']],
                'approval' => ['reference_id' => 'approval-reference'],
                'provider_payload' => ['token' => 'provider-token'],
                'raw_payload' => ['secret' => 'raw-secret'],
                'wallet' => ['account_number' => '000123'],
                'provider' => 'paynamics',
            ];
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

    $bundle = (new VoucherLifecycleCockpitReadModelProvider($lifecycle))
        ->forVoucher(new CockpitReadModelQueryData(
            code: ' pc-ready-001 ',
            include: ['voucher', 'execution', 'journal', 'actions', 'feedback'],
        ));

    expect($lifecycle->requestedCode)->toBe('PC-READY-001')
        ->and($bundle->code)->toBe('PC-READY-001')
        ->and($bundle->voucher->code)->toBe('PC-READY-001')
        ->and($bundle->voucher->status)->toBe('issued')
        ->and($bundle->voucher->authorized)->toBeTrue()
        ->and($bundle->voucher->summary)->toBe([
            'code' => 'PC-READY-001',
            'status' => 'issued',
            'display_status' => 'ready',
            'amount' => 1500.75,
            'currency' => 'PHP',
            'claimed' => false,
            'fully_claimed' => false,
            'created_at' => '2026-07-03T10:00:00+08:00',
            'starts_at' => '2026-07-03T11:00:00+08:00',
            'expires_at' => '2026-07-10T11:00:00+08:00',
            'redeemed_at' => null,
        ])
        ->and($bundle->voucher->redactions)->toBe([
            'payloads' => 'sanitized-summary-only',
            'excluded' => [
                'id',
                'voucher_id',
                'issuer_id',
                'instructions',
                'claims',
                'approval',
                'provider_payload',
                'raw_payload',
                'wallet',
                'provider',
            ],
        ])
        ->and($bundle->execution->status)->toBe('not_wired')
        ->and($bundle->journal->status)->toBe('not_wired')
        ->and($bundle->actions->status)->toBe('not_wired')
        ->and($bundle->feedback->status)->toBe('not_wired')
        ->and($bundle->toArray())->not->toHaveKeys([
            'provider_payload',
            'raw_payload',
            'wallet',
            'provider',
        ])
        ->and($bundle->voucher->summary)->not->toHaveKeys([
            'id',
            'voucher_id',
            'issuer_id',
            'instructions',
            'claims',
            'approval',
            'provider_payload',
            'raw_payload',
            'wallet',
            'provider',
        ]);
});

it('falls back to the not wired bundle when voucher lifecycle lookup misses', function () {
    $lifecycle = new class implements VoucherLifecycleServiceContract
    {
        public function list(array $filters = []): array
        {
            return [];
        }

        public function show(string $voucher): mixed
        {
            return null;
        }

        public function showByCode(string $code): mixed
        {
            throw new VoucherNotFound($code);
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

    $bundle = (new VoucherLifecycleCockpitReadModelProvider($lifecycle))
        ->forVoucher(new CockpitReadModelQueryData(code: 'PC-MISSING-001'));

    expect($bundle->code)->toBe('PC-MISSING-001')
        ->and($bundle->voucher->status)->toBe('not_wired')
        ->and($bundle->voucher->authorized)->toBeFalse()
        ->and($bundle->voucher->summary)->toBe([])
        ->and($bundle->execution->status)->toBe('not_wired')
        ->and($bundle->journal->status)->toBe('not_wired')
        ->and($bundle->actions->status)->toBe('not_wired')
        ->and($bundle->feedback->status)->toBe('not_wired');
});

it('returns an empty not wired pay code list read model by default', function () {
    $readModel = (new NullCockpitReadModelProvider)
        ->forPayCodeList(new CockpitReadModelQueryData);

    expect($readModel->toArray())->toBe([
        'status' => 'not_wired',
        'authorized' => false,
        'query' => null,
        'records' => [],
        'redactions' => ['payloads' => 'not-loaded'],
    ]);
});

it('adapts voucher lifecycle list rows into sanitized cockpit pay code rows', function () {
    $lifecycle = new class implements VoucherLifecycleServiceContract
    {
        public array $requestedFilters = [];

        public function list(array $filters = []): array
        {
            $this->requestedFilters = $filters;

            return [
                [
                    'id' => 123,
                    'voucher_id' => 123,
                    'code' => ' pc-list-001 ',
                    'template' => 'Emergency Cash',
                    'amount' => 2500.5,
                    'currency' => 'PHP',
                    'status' => 'issued',
                    'display_status' => 'ready',
                    'owner' => 'Operations',
                    'last_activity' => '2026-07-03T10:00:00+08:00',
                    'issuer_id' => 45,
                    'approval' => ['reference_id' => 'approval-reference'],
                    'provider_payload' => ['token' => 'provider-token'],
                    'raw_payload' => ['secret' => 'raw-secret'],
                    'wallet' => ['account_number' => '000123'],
                    'provider' => 'paynamics',
                ],
                [
                    'code' => 'pc-list-002',
                    'amount' => '99.95',
                    'currency' => 'PHP',
                    'status' => 'redeemed',
                    'created_at' => '2026-07-02T10:00:00+08:00',
                    'issuer_id' => 99,
                ],
                [
                    'code' => '',
                    'status' => 'issued',
                    'provider_payload' => ['token' => 'must-not-render'],
                ],
            ];
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

    $readModel = (new VoucherLifecycleCockpitReadModelProvider($lifecycle))
        ->forPayCodeList(new CockpitReadModelQueryData(
            include: ['voucher'],
            correlationId: 'corr-1',
        ));

    expect($lifecycle->requestedFilters)->toBe([])
        ->and($readModel->toArray())->toBe([
            'status' => 'available',
            'authorized' => true,
            'query' => null,
            'records' => [
                [
                    'code' => 'PC-LIST-001',
                    'template' => 'Emergency Cash',
                    'amount' => 2500.5,
                    'currency' => 'PHP',
                    'status' => 'issued',
                    'display_status' => 'ready',
                    'owner' => 'Operations',
                    'last_activity' => '2026-07-03T10:00:00+08:00',
                ],
                [
                    'code' => 'PC-LIST-002',
                    'template' => 'Pay Code',
                    'amount' => '99.95',
                    'currency' => 'PHP',
                    'status' => 'redeemed',
                    'display_status' => 'redeemed',
                    'owner' => 'Redacted',
                    'last_activity' => '2026-07-02T10:00:00+08:00',
                ],
            ],
            'redactions' => [
                'payloads' => 'sanitized-list-summary-only',
                'excluded' => [
                    'id',
                    'voucher_id',
                    'issuer_id',
                    'instructions',
                    'claims',
                    'approval',
                    'provider_payload',
                    'raw_payload',
                    'wallet',
                    'provider',
                ],
            ],
        ]);
});

it('returns an empty not wired dashboard read model by default', function () {
    $readModel = (new NullCockpitReadModelProvider)
        ->forDashboard(new CockpitReadModelQueryData);

    expect($readModel->toArray())->toBe([
        'status' => 'not_wired',
        'authorized' => false,
        'metrics' => [],
        'pipeline' => [],
        'risk_signals' => [],
        'activity' => [],
        'redactions' => ['payloads' => 'not-loaded'],
    ]);
});

it('adapts voucher lifecycle list rows into sanitized cockpit dashboard facts', function () {
    $lifecycle = new class implements VoucherLifecycleServiceContract
    {
        public function list(array $filters = []): array
        {
            return [
                [
                    'id' => 1,
                    'code' => 'pc-issued-001',
                    'status' => 'issued',
                    'display_status' => 'ready',
                    'amount' => 1000.00,
                    'currency' => 'PHP',
                    'created_at' => '2026-07-03T10:00:00+08:00',
                    'issuer_id' => 10,
                    'provider_payload' => ['token' => 'must-not-leak'],
                    'wallet' => ['account_number' => '000123'],
                ],
                [
                    'id' => 2,
                    'code' => 'pc-redeemed-001',
                    'status' => 'redeemed',
                    'display_status' => 'redeemed',
                    'amount' => 500.00,
                    'currency' => 'PHP',
                    'redeemed_at' => '2026-07-03T11:00:00+08:00',
                    'approval' => ['reference_id' => 'must-not-leak'],
                ],
                [
                    'id' => 3,
                    'code' => 'pc-expired-001',
                    'status' => 'expired',
                    'display_status' => 'expired',
                    'amount' => 250.00,
                    'currency' => 'PHP',
                    'expires_at' => '2026-07-03T12:00:00+08:00',
                    'raw_payload' => ['secret' => 'must-not-leak'],
                ],
                [
                    'id' => 4,
                    'code' => 'pc-awaiting-001',
                    'status' => 'redeemed',
                    'display_status' => 'awaiting_approval',
                    'amount' => 200.00,
                    'currency' => 'PHP',
                    'updated_at' => '2026-07-03T13:00:00+08:00',
                    'claims' => [['mobile' => '+639171234567']],
                ],
            ];
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

    $readModel = (new VoucherLifecycleCockpitReadModelProvider($lifecycle))
        ->forDashboard(new CockpitReadModelQueryData(include: ['voucher']));

    expect($readModel->toArray())->toBe([
        'status' => 'available',
        'authorized' => true,
        'metrics' => [
            [
                'key' => 'pay-codes-visible',
                'label' => 'Pay Codes Visible',
                'value' => '4',
                'helper' => 'Sanitized voucher lifecycle list rows',
                'tone' => 'neutral',
            ],
            [
                'key' => 'issued-pay-codes',
                'label' => 'Issued',
                'value' => '1',
                'helper' => 'Read-only lifecycle summary',
                'tone' => 'healthy',
            ],
            [
                'key' => 'redeemed-pay-codes',
                'label' => 'Redeemed',
                'value' => '2',
                'helper' => 'Includes awaiting approval display states',
                'tone' => 'healthy',
            ],
            [
                'key' => 'attention-pay-codes',
                'label' => 'Needs Attention',
                'value' => '2',
                'helper' => 'Expired or awaiting approval summaries only',
                'tone' => 'warning',
            ],
        ],
        'pipeline' => [
            ['key' => 'issued', 'label' => 'Issued', 'value' => '1', 'tone' => 'neutral'],
            ['key' => 'redeemed', 'label' => 'Redeemed', 'value' => '2', 'tone' => 'healthy'],
            ['key' => 'expired', 'label' => 'Expired', 'value' => '1', 'tone' => 'warning'],
            ['key' => 'awaiting-approval', 'label' => 'Awaiting Approval', 'value' => '1', 'tone' => 'warning'],
        ],
        'risk_signals' => [
            [
                'key' => 'expired-pay-codes',
                'label' => 'Expired Pay Codes',
                'value' => '1 sanitized summaries',
                'severity' => 'warning',
            ],
            [
                'key' => 'awaiting-approval',
                'label' => 'Awaiting Approval',
                'value' => '1 sanitized summaries',
                'severity' => 'watch',
            ],
        ],
        'activity' => [
            [
                'id' => 'PC-AWAITING-001',
                'label' => 'PC-AWAITING-001',
                'description' => 'Status: awaiting_approval',
                'timestamp' => '2026-07-03T13:00:00+08:00',
                'source' => 'system',
            ],
            [
                'id' => 'PC-EXPIRED-001',
                'label' => 'PC-EXPIRED-001',
                'description' => 'Status: expired',
                'timestamp' => '2026-07-03T12:00:00+08:00',
                'source' => 'system',
            ],
            [
                'id' => 'PC-REDEEMED-001',
                'label' => 'PC-REDEEMED-001',
                'description' => 'Status: redeemed',
                'timestamp' => '2026-07-03T11:00:00+08:00',
                'source' => 'system',
            ],
        ],
        'redactions' => [
            'payloads' => 'sanitized-dashboard-summary-only',
            'excluded' => [
                'id',
                'voucher_id',
                'issuer_id',
                'instructions',
                'claims',
                'approval',
                'provider_payload',
                'raw_payload',
                'wallet',
                'provider',
            ],
        ],
    ]);
});

it('binds the cockpit read model provider contract to the voucher lifecycle adapter baseline', function () {
    expect(app(CockpitReadModelProviderContract::class))
        ->toBeInstanceOf(VoucherLifecycleCockpitReadModelProvider::class);
});

it('keeps the cockpit read model baseline free of direct integration package dependencies', function () {
    $files = collect([
        ...glob(__DIR__.'/../../../src/Data/Cockpit/*.php'),
        ...glob(__DIR__.'/../../../src/Services/Cockpit/*.php'),
        ...glob(__DIR__.'/../../../src/Contracts/CockpitReadModelProviderContract.php'),
    ]);

    $contents = $files
        ->map(fn (string $file): string => file_get_contents($file) ?: '')
        ->implode("\n");

    expect($contents)
        ->not->toContain('LBHurtado\\XJournal')
        ->not->toContain('LBHurtado\\XAction')
        ->not->toContain('LBHurtado\\XFeedback')
        ->not->toContain('FrittenKeeZ\\Vouchers\\Models\\Voucher');
});
