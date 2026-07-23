<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\ProviderPayerIdentityData;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Services\Funding\MatchFundingPayerIdentity;

it('matches only the complete provider-verified mobile of a verified Funding Intent owner', function () {
    config()->set('x-change.funding.providers.qrph_simulator.enabled', true);
    config()->set('x-change.funding.payer_identity_hash_key', 'payer-identity-key');
    $owner = actingAsTestUser();
    $owner->forceFill([
        'mobile' => '639171234567',
        'mobile_verified_at' => now(),
    ])->save();
    $intent = app(CreateFundingIntent::class)->handle(new CreateFundingIntentData(
        accountReference: 'wallet:123',
        provider: 'qrph_simulator',
        expectedAmountMinor: 2_500,
        currency: 'PHP',
        idempotencyKey: 'payer-identity-match',
        actorType: $owner::class,
        actorId: (string) $owner->getKey(),
    ));
    $observation = app(MatchFundingPayerIdentity::class)->handle(
        $intent,
        payerObservation('0917 123 4567'),
    );

    expect($observation->payerIdentity)->toBeNull()
        ->and($observation->metadata['payer_identity_required'])->toBeTrue()
        ->and($observation->metadata['payer_identity_matched'])->toBeTrue()
        ->and($observation->metadata['payer_mobile_masked'])->toBe('63••••••4567')
        ->and($observation->metadata['payer_mobile_hash'])->toHaveLength(64)
        ->and(json_encode($observation->metadata))->not->toContain('639171234567');
});

it('fails closed for a mismatched or unverified owner mobile', function (?string $ownerVerifiedAt) {
    config()->set('x-change.funding.providers.qrph_simulator.enabled', true);
    $owner = actingAsTestUser();
    $owner->forceFill([
        'mobile' => '639171234568',
        'mobile_verified_at' => $ownerVerifiedAt,
    ])->save();
    $intent = app(CreateFundingIntent::class)->handle(new CreateFundingIntentData(
        accountReference: 'wallet:123',
        provider: 'qrph_simulator',
        expectedAmountMinor: 2_500,
        currency: 'PHP',
        idempotencyKey: 'payer-identity-mismatch-'.$owner->getKey(),
        actorType: $owner::class,
        actorId: (string) $owner->getKey(),
    ));
    $observation = app(MatchFundingPayerIdentity::class)->handle(
        $intent,
        payerObservation('0917 123 4567'),
    );

    expect($observation->metadata['payer_identity_matched'])->toBeFalse();
})->with([
    'different verified mobile' => now()->toDateTimeString(),
    'unverified mobile' => null,
]);

function payerObservation(string $mobile): ProviderFundingObservationData
{
    return new ProviderFundingObservationData(
        provider: 'qrph_simulator',
        providerTransactionId: 'QRSIM-TXN-123',
        grossAmountMinor: 2_500,
        feeAmountMinor: 0,
        netAmountMinor: 2_500,
        currency: 'PHP',
        providerStatus: 'settled',
        verificationSource: 'qrph-simulated-provider-ledger',
        payloadHash: hash('sha256', 'payer-observation'),
        metadata: ['destination_verified' => true],
        payerIdentity: new ProviderPayerIdentityData(
            mobile: $mobile,
            verificationSource: 'qrph-simulated-payer-profile',
            providerVerified: true,
        ),
    );
}
