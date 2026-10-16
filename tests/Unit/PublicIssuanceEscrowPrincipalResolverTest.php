<?php

declare(strict_types=1);

use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Services\Commercial\ConfiguredCommercialPrincipalResolver;
use LBHurtado\XChange\Services\Commercial\ConfiguredPublicIssuanceEscrowPrincipalResolver;

it('falls back to the commissioned commercial principal before escrow cutover', function (): void {
    config()->set('x-change.public_issuance_escrow.enabled', false);

    config()->set('x-change.commercial.principal.reference', 'commercial-primary');

    $principal = CommercialPrincipal::query()->create([
        'reference' => 'commercial-primary',
        'legal_name' => 'Commercial',
        'authorization_reference' => 'test',
        'active' => true,
    ]);

    expect((new ConfiguredPublicIssuanceEscrowPrincipalResolver(new ConfiguredCommercialPrincipalResolver))->resolve()->getKey())
        ->toBe($principal->getKey());
});

it('resolves the dedicated escrow principal after cutover', function (): void {
    config()->set('x-change.public_issuance_escrow.enabled', true);
    config()->set('x-change.public_issuance_escrow.principal.reference', 'public-issuance-escrow');

    $principal = CommercialPrincipal::query()->create([
        'reference' => 'public-issuance-escrow',
        'legal_name' => 'Public Issuance Escrow',
        'authorization_reference' => 'test',
        'active' => true,
    ]);

    expect((new ConfiguredPublicIssuanceEscrowPrincipalResolver(new ConfiguredCommercialPrincipalResolver))->resolve()->getKey())
        ->toBe($principal->getKey());
});
