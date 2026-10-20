<?php

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Services\Commercial\CommercialOperationsEconomicsReadModel;

it('registers the operations economics report source and routes', function (): void {
    expect(config('report-registry.driver_sources'))
        ->toContain(dirname(__DIR__, 3).'/resources/report-drivers');

    expect(Route::has('x-change.operations.economics'))->toBeTrue()
        ->and(Route::has('x-change.operations.economics.report'))->toBeTrue()
        ->and(Route::has('x-change.cockpit.operations-economics'))->toBeTrue()
        ->and(Route::has('x-change.cockpit.operations-economics.report'))->toBeTrue();
});

it('ships the six operations report driver definitions', function (): void {
    $root = dirname(__DIR__, 3).'/resources/report-drivers';
    foreach ([
        'xchange-operations-summary',
        'xchange-operations-waterfall',
        'xchange-product-revenue',
        'xchange-position-balances',
        'xchange-operations-movements',
        'xchange-operations-exceptions',
    ] as $driver) {
        $files = glob($root.'/'.$driver.'/v*.yaml');

        expect($files)->toHaveCount(1)
            ->and(file_get_contents($files[0]))->toContain('group: operations');
    }
});

it('returns a minor-unit read-only summary for an empty period', function (): void {
    $summary = app(CommercialOperationsEconomicsReadModel::class)->summary(['period' => 'today']);

    expect($summary['data'][0])->toMatchArray([
        'checkouts' => 0,
        'payments_received' => 0,
        'orders_issued' => 0,
        'principal_minor' => 0,
        'provider_cost_minor' => 0,
        'product_revenue_minor' => 0,
        'state' => 'read_only',
    ])->and($summary['meta']['currency'])->toBe('PHP');
});
