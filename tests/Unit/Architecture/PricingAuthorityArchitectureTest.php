<?php

declare(strict_types=1);

it('keeps active Commercial Offerings as the only runtime instruction pricing authority', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $pricelist = file_get_contents($packageRoot.'/src/Services/PricelistService.php');
    $lifecycle = file_get_contents($packageRoot.'/src/Console/Commands/Lifecycle/PrepareLifecycleEnvironmentCommand.php');
    $provider = file_get_contents($packageRoot.'/src/Providers/XChangeServiceProvider.php');

    expect($pricelist)
        ->toContain('CommercialOfferingResolverContract')
        ->not->toContain("config('x-change.pricing")
        ->not->toContain("config('x-change.pricelist")
        ->and($lifecycle)
        ->toContain('CommercialOfferingResolverContract')
        ->not->toContain("config('x-change.pricelist")
        ->not->toContain("config('x-commerce.catalogs")
        ->and($provider)
        ->toContain('PricingServiceContract::class, InstructionBackedPricingService::class');
});

it('locks the accepted Offering identity across estimate and issuance', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $generate = file_get_contents($packageRoot.'/src/Actions/PayCode/GeneratePayCode.php');
    $sale = file_get_contents($packageRoot.'/src/Services/Commercial/PayCodeCommercialSaleService.php');
    $quickGenerate = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue');

    expect($generate)
        ->toContain('assertAcceptedPricing($input, $estimate)')
        ->and($sale)
        ->toContain('assertQuote($acceptedEstimate, $quote)')
        ->and($quickGenerate)
        ->toContain('payload._pricing')
        ->toContain('commercial_offering_snapshot_hash');
});
