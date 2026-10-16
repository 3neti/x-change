<?php

declare(strict_types=1);

use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Services\Commercial\ConfiguredPublicIssuanceEscrowPrincipalResolver;

it('fails closed before escrow cutover', function (): void {
    config()->set('x-change.public_issuance_escrow.enabled', false);

    expect(fn () => (new ConfiguredPublicIssuanceEscrowPrincipalResolver)->resolve())
        ->toThrow(TreasuryConfigurationException::class);
});

it('resolves the dedicated escrow principal after cutover', function (): void {
    config()->set('x-change.public_issuance_escrow.enabled', true);
    config()->set('x-change.public_issuance_escrow.principal.reference', 'public-issuance-escrow');
    config()->set('x-change.public_issuance_escrow.principal.legal_name', 'Public Issuance Escrow');
    config()->set('x-change.public_issuance_escrow.principal.authorization_reference', 'commissioning:public-issuance-escrow:v1');

    $principal = CommercialPrincipal::query()->create([
        'reference' => 'public-issuance-escrow',
        'legal_name' => 'Public Issuance Escrow',
        'authorization_reference' => 'commissioning:public-issuance-escrow:v1',
        'active' => true,
    ]);

    expect((new ConfiguredPublicIssuanceEscrowPrincipalResolver)->resolve()->getKey())
        ->toBe($principal->getKey());
});

it('rejects an escrow principal with mismatched commissioning metadata', function (): void {
    config()->set('x-change.public_issuance_escrow.enabled', true);
    config()->set('x-change.public_issuance_escrow.principal.reference', 'public-issuance-escrow');
    config()->set('x-change.public_issuance_escrow.principal.legal_name', 'Public Issuance Escrow');
    config()->set('x-change.public_issuance_escrow.principal.authorization_reference', 'commissioning:public-issuance-escrow:v1');

    CommercialPrincipal::query()->create([
        'reference' => 'public-issuance-escrow',
        'legal_name' => 'Wrong Principal',
        'authorization_reference' => 'wrong',
        'active' => true,
    ]);

    expect(fn () => (new ConfiguredPublicIssuanceEscrowPrincipalResolver)->resolve())
        ->toThrow(TreasuryConfigurationException::class);
});
