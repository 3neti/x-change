<?php

declare(strict_types=1);

it('presents the package-owned funding treasury portfolio without duplicate legacy inventory', function () {
    $packageRoot = dirname(__DIR__, 3);
    $fundingPage = file_get_contents(
        $packageRoot.'/resources/js/cockpit/pages/Funding.vue',
    );
    $types = file_get_contents($packageRoot.'/resources/js/cockpit/types.ts');
    $uiUxGuide = file_get_contents(
        $packageRoot.'/docs/ui-cockpit/FUNDING_WORKSPACE_UI_UX.md',
    );
    $fundingArchitecture = file_get_contents(
        $packageRoot.'/docs/architecture/FUNDING_ACCOUNT_MANAGEMENT.md',
    );
    $cockpitCompass = file_get_contents(
        $packageRoot.'/docs/ui-cockpit/COMPASS.md',
    );

    expect($fundingPage)
        ->toContain('data-testid="funding-treasury-portfolio"')
        ->toContain('data-testid="funding-treasury-provider-breakdown"')
        ->toContain('Treasury oversight')
        ->toContain('Liquidity &amp; reconciliation')
        ->toContain('Provider Inventory')
        ->toContain('Position control')
        ->toContain('Internal positions reconciled')
        ->toContain('Provider controls')
        ->toContain('Refresh liquidity')
        ->toContain('refreshFundingLiquidityRoute')
        ->toContain('treasuryPortfolio.value?.connections')
        ->toContain('canViewTreasuryControls && treasuryPortfolio')
        ->toContain('Cached projections only')
        ->toContain("label: 'QR Ph'")
        ->not->toContain('treasuryPortfolioCards')
        ->not->toContain('funding_read_model.treasury_positions.length')
        ->not->toContain('3neti/wallet grammar')
        ->and($types)
        ->toContain('export type CockpitFundingTreasuryPortfolio')
        ->toContain('export type CockpitFundingTreasuryConnection')
        ->toContain('treasury_portfolio?: CockpitFundingTreasuryPortfolio;')
        ->and($uiUxGuide)
        ->toContain('Account Funding Workspace UI/UX Guide')
        ->toContain('For the Account Holder')
        ->toContain('For Developers')
        ->toContain('For AI Agents')
        ->toContain('Funding Activity is the one durable history surface')
        ->toContain('`can_view_treasury_controls`')
        ->toContain('Treasury controls → Provider diagnostics')
        ->toContain('Automated tests and AI agents never initiate a real-money transfer')
        ->and($fundingArchitecture)
        ->toContain('../ui-cockpit/FUNDING_WORKSPACE_UI_UX.md')
        ->and($cockpitCompass)
        ->toContain('Account Funding Workspace UI/UX Contract')
        ->toContain('FUNDING_WORKSPACE_UI_UX.md');
});
