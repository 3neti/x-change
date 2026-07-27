<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Treasury\AdvisoryTreasuryVocabularyReadModel;

it('provides package-owned Treasury vocabulary when x-legal is unavailable', function () {
    $terms = app(AdvisoryTreasuryVocabularyReadModel::class)->terms([
        'internal_balance',
        'issuance_capacity',
        'provider_account',
        'funding_qr_ph',
        'funding_bank_transfer',
        'funding_pay_code',
        'funding_reviewed_value',
        'funding_activity',
    ]);

    expect($terms)
        ->toHaveKeys([
            'internal_balance',
            'issuance_capacity',
            'provider_account',
            'funding_qr_ph',
            'funding_bank_transfer',
            'funding_pay_code',
            'funding_reviewed_value',
            'funding_activity',
        ])
        ->and($terms['internal_balance']['label'])->toBe('Client Funds')
        ->and($terms['internal_balance']['source'])->toBe('x-change')
        ->and($terms['internal_balance']['approved_for_public_display'])->toBeFalse()
        ->and($terms['issuance_capacity']['description'])->not->toBeEmpty()
        ->and($terms['funding_qr_ph']['label'])->toBe('QR Ph')
        ->and($terms['funding_bank_transfer']['label'])->toBe('Bank Transfer')
        ->and($terms['funding_pay_code']['label'])->toBe('Pay Code')
        ->and($terms['funding_reviewed_value']['label'])->toBe('Reviewed Value')
        ->and($terms['funding_activity']['label'])->toBe('Funding Activity');
});

it('uses an installed x-legal vocabulary resolver without making it an execution authority', function () {
    $contextResolver = 'LBHurtado\\XLegal\\Contracts\\JurisdictionContextResolverContract';
    $vocabularyResolver = 'LBHurtado\\XLegal\\Contracts\\LegalVocabularyResolverContract';

    app()->instance($contextResolver, new class
    {
        public function resolve(array $overrides = []): object
        {
            expect($overrides['legal_entity_reference'])->toBe('entity-ph-001');

            return (object) ['profile' => 'treasury-settlement-ph-v1'];
        }
    });
    app()->instance($vocabularyResolver, new class
    {
        public function resolve(string $term, object $context, string $audience = 'operator'): object
        {
            expect($context->profile)->toBe('treasury-settlement-ph-v1')
                ->and($audience)->toBe('operator');

            return (object) [
                'key' => $term,
                'label' => 'Counsel Working Label',
                'description' => 'Advisory wording only.',
                'profile' => $context->profile,
                'profileVersion' => '2026-07-24.2',
                'approvedForPublicDisplay' => false,
            ];
        }
    });
    config(['x-change.treasury.legal_entity_reference' => 'entity-ph-001']);

    $term = app(AdvisoryTreasuryVocabularyReadModel::class)
        ->terms(['internal_balance'])['internal_balance'];

    expect($term['label'])->toBe('Counsel Working Label')
        ->and($term['source'])->toBe('x-legal')
        ->and($term['profile_version'])->toBe('2026-07-24.2')
        ->and($term['approved_for_public_display'])->toBeFalse();
});
