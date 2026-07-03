<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers read-only cockpit routes under the x cockpit namespace', function () {
    expect(route('x-change.cockpit.dashboard'))->toBe('http://localhost/x/cockpit')
        ->and(route('x-change.cockpit.quick-generate'))->toBe('http://localhost/x/cockpit/quick-generate')
        ->and(route('x-change.cockpit.pay-codes.index'))->toBe('http://localhost/x/cockpit/pay-codes')
        ->and(route('x-change.cockpit.pay-codes.show', ['code' => 'PC-READY-001']))->toBe('http://localhost/x/cockpit/pay-codes/PC-READY-001')
        ->and(route('x-change.cockpit.pay-codes.distribution', ['code' => 'PC-READY-001']))->toBe('http://localhost/x/cockpit/pay-codes/PC-READY-001/distribution');
});

it('renders cockpit pages as read-only inertia endpoints', function (string $route, array $parameters, string $component) {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route($route, $parameters))
        ->assertOk()
        ->assertJsonPath('component', $component);
})->with([
    'dashboard' => ['x-change.cockpit.dashboard', [], 'x-change/cockpit/Dashboard'],
    'quick generate' => ['x-change.cockpit.quick-generate', [], 'x-change/cockpit/QuickGenerate'],
    'pay code explorer' => ['x-change.cockpit.pay-codes.index', [], 'x-change/cockpit/PayCodeExplorer'],
    'voucher detail' => ['x-change.cockpit.pay-codes.show', ['code' => 'PC-READY-001'], 'x-change/cockpit/VoucherDetail'],
    'distribution workspace' => ['x-change.cockpit.pay-codes.distribution', ['code' => 'PC-READY-001'], 'x-change/cockpit/DistributionWorkspace'],
]);

it('does not register cockpit mutation routes', function () {
    $mutatingRoutes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'x-change.cockpit.'))
        ->filter(fn ($route): bool => collect($route->methods())
            ->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])
            ->isNotEmpty()
        );

    expect($mutatingRoutes)->toHaveCount(0);
});
