<?php

declare(strict_types=1);

it('registers every Cockpit Inertia page exposed by package controllers', function () {
    $packageRoot = dirname(__DIR__, 3);
    $components = collect(glob(
        $packageRoot.'/src/Http/Controllers/Web/Cockpit/*Controller.php',
    ))->flatMap(function (string $controllerPath): array {
        preg_match_all(
            "/Inertia::render\\('(?<component>x-change\\/cockpit\\/[^']+)'/",
            file_get_contents($controllerPath),
            $matches,
        );

        return $matches['component'];
    })->unique()->sort()->values();

    expect($components)->not->toBeEmpty();

    $components->each(function (string $component) use ($packageRoot): void {
        expect($packageRoot.'/resources/js/pages/'.$component.'.vue')->toBeFile();
    });

    $pagePath = $packageRoot.'/resources/js/pages/x-change/cockpit/TreasuryOperations.vue';

    expect(file_get_contents($pagePath))
        ->toContain('import TreasuryOperations from "../../../cockpit/pages/TreasuryOperations.vue";')
        ->toContain(':treasury-account-grants="treasury_account_grants"')
        ->toContain(':treasury-institution-funds="treasury_institution_funds"')
        ->toContain(':treasury-reconciliation="treasury_reconciliation"');
});
