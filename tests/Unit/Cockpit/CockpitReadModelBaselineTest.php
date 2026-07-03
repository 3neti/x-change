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
