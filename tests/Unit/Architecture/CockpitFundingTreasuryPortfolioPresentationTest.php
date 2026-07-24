<?php

declare(strict_types=1);

it('presents the package-owned funding treasury portfolio without duplicate legacy inventory', function () {
    $packageRoot = dirname(__DIR__, 3);
    $fundingPage = file_get_contents(
        $packageRoot.'/resources/js/cockpit/pages/Funding.vue',
    );
    $types = file_get_contents($packageRoot.'/resources/js/cockpit/types.ts');

    expect($fundingPage)
        ->toContain('data-testid="funding-treasury-portfolio"')
        ->toContain('data-testid="funding-treasury-provider-breakdown"')
        ->toContain('Client Funds')
        ->toContain('Reserved for Pay Codes')
        ->toContain('Provider Inventory')
        ->toContain('Issuance Capacity')
        ->toContain('funding_read_model.treasury_portfolio.connections')
        ->toContain('Cached projections only')
        ->toContain('Self Top-Up')
        ->not->toContain('funding_read_model.treasury_positions.length')
        ->not->toContain('3neti/wallet grammar')
        ->and($types)
        ->toContain('export type CockpitFundingTreasuryPortfolio')
        ->toContain('export type CockpitFundingTreasuryConnection')
        ->toContain('treasury_portfolio: CockpitFundingTreasuryPortfolio;');
});
