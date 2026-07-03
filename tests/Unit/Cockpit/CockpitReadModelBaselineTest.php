<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Services\Cockpit\NullCockpitReadModelProvider;

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

it('binds the cockpit read model provider contract to the null provider baseline', function () {
    expect(app(CockpitReadModelProviderContract::class))
        ->toBeInstanceOf(NullCockpitReadModelProvider::class);
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
