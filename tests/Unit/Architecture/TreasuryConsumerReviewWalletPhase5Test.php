<?php

declare(strict_types=1);

it('documents wallet treasury phase 0 through 5 consumer mapping without runtime wiring', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/455-treasury-consumer-review-wallet-phase-0-5.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $composer = json_decode(file_get_contents($packageRoot.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($report)->toContain('x-change Treasury Consumer Review — Wallet Phase 0–5')
        ->and($report)->toContain('Phase 0 — Treasury architecture documentation')
        ->and($report)->toContain('Phase 5 — Allocation and Slice read-model planning scaffold')
        ->and($report)->toContain('Outstanding Pay Codes')
        ->and($report)->toContain('Usable Balance')
        ->and($report)->toContain('Money Movement Model')
        ->and($report)->toContain('Money Movement Triggers')
        ->and($report)->toContain('hasTreasuryFacts = false')
        ->and($report)->toContain('treasury_facts = absent')
        ->and($report)->toContain('NullTreasuryPlanningRuntime')
        ->and($report)->toContain('No wallet Treasury runtime dependency was added to x-change')
        ->and($settlementCompass)->toContain('x-change Treasury Consumer Review — Wallet Phase 0–5')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/455-treasury-consumer-review-wallet-phase-0-5.md')
        ->and($settlementCompass)->toContain('x-change bridge values remain authoritative for Cockpit display until wallet Treasury reports real persisted facts')
        ->and($settlementCompass)->toContain('x-change must not treat `NullTreasuryPlanningRuntime` output as an executed allocation')
        ->and($composer['require'] ?? [])->not->toHaveKey('3neti/wallet');
});

it('keeps wallet treasury implementation classes out of x-change production code for the consumer review slice', function () {
    $packageRoot = dirname(__DIR__, 3);

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($packageRoot.'/src', FilesystemIterator::SKIP_DOTS)
    );

    $violations = collect(iterator_to_array($files))
        ->filter(fn (SplFileInfo $file): bool => $file->isFile() && str_ends_with($file->getFilename(), '.php'))
        ->filter(function (SplFileInfo $file): bool {
            $contents = file_get_contents($file->getPathname());

            return str_contains($contents, 'TreasuryPlanningContract')
                || str_contains($contents, 'NullTreasuryPlanningRuntime')
                || str_contains($contents, 'TreasuryInventoryReadModelContract')
                || str_contains($contents, 'TreasuryAllocationReadModelContract')
                || str_contains($contents, 'TreasurySliceSemantics');
        })
        ->map(fn (SplFileInfo $file): string => str_replace($packageRoot.'/', '', $file->getPathname()))
        ->values()
        ->all();

    expect($violations)->toBe([]);
});
