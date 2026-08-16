<?php

declare(strict_types=1);

use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Contracts\CommercialSettlementAccountResolverContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

it('resolves an exact wallet owner and compatible Client Funds Position', function (): void {
    enableNetbankTreasuryForTests();
    $recipient = actingAsTestUser(0);
    $connection = app(TreasuryProviderConnectionCatalog::class)
        ->active(['netbank-primary'])[0];
    $principalReference = app(TreasuryPrincipalReferenceResolverContract::class)
        ->resolve($recipient);

    $positionReference = app(CommercialSettlementAccountResolverContract::class)
        ->resolveClientFundsPosition(
            'wallet:'.$recipient->wallet->uuid,
            $principalReference,
            $connection,
        );
    $position = TreasuryPosition::query()
        ->where('position_reference', $positionReference)
        ->sole();

    expect($position->purpose)->toBe(TreasuryPositionPurpose::ClientFunds)
        ->and($position->provider)->toBe($connection->provider)
        ->and($position->connection_reference)->toBe($connection->reference)
        ->and($position->currency)->toBe($connection->currency);
});

it('rejects an Account whose holder does not match the governed principal', function (): void {
    enableNetbankTreasuryForTests();
    $recipient = actingAsTestUser(0);
    $other = actingAsTestUser(0);
    $connection = app(TreasuryProviderConnectionCatalog::class)
        ->active(['netbank-primary'])[0];
    $wrongPrincipal = app(TreasuryPrincipalReferenceResolverContract::class)
        ->resolve($other);

    expect(fn () => app(CommercialSettlementAccountResolverContract::class)
        ->resolveClientFundsPosition(
            'wallet:'.$recipient->wallet->uuid,
            $wrongPrincipal,
            $connection,
        ))->toThrow(CommercialSaleConflict::class, 'does not belong');
});

it('rejects an unresolvable governed Account reference', function (): void {
    enableNetbankTreasuryForTests();
    $connection = app(TreasuryProviderConnectionCatalog::class)
        ->active(['netbank-primary'])[0];

    expect(fn () => app(CommercialSettlementAccountResolverContract::class)
        ->resolveClientFundsPosition(
            'wallet:missing-commercial-recipient',
            'principal:missing-commercial-recipient',
            $connection,
        ))->toThrow(FundingSettlementDenied::class, 'could not be resolved');
});
