<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Services\Configuration\ApplicationJourneyInstructionGuard;

function journeyIssuancePayload(array $journey): array
{
    return [
        'cash' => ['amount' => 25, 'currency' => 'PHP', 'validation' => ['country' => 'PH']],
        'count' => 1,
        'voucher_type' => 'redeemable',
        'claim' => [
            'outcomes' => [['key' => 'provider_disbursement']],
            'selection' => 'server',
            'consumption' => 'one_of',
            'default_outcome' => 'provider_disbursement',
        ],
        'rider' => ['message' => null],
        'metadata' => ['custom' => ['app_journey' => $journey]],
    ];
}

it('binds each specialized issuance purpose to a configured journey and voucher instruction', function (
    string $profile,
    array $journey,
    string $expectedMessage,
): void {
    config()->set('x-change.app_profile.primary', $profile);

    $payload = app(ApplicationJourneyInstructionGuard::class)->validateAndCompile(journeyIssuancePayload($journey), false);

    expect(data_get($payload, 'metadata.custom.app_journey'))->toBe($journey)
        ->and(data_get($payload, 'rider.message'))->toBe($expectedMessage);
})->with([
    'loan' => ['lending-center', ['key' => 'loan_disbursement', 'reference' => 'LOAN-42'], 'Loan disbursement LOAN-42'],
    'exchange' => ['money-changer', ['key' => 'exchange_payout', 'reference' => 'QUOTE-42', 'source_currency' => 'USD', 'source_amount' => '1.00'], 'Exchange payout QUOTE-42'],
    'local remittance' => ['local-remittance', ['key' => 'send_local_remittance', 'reference' => 'LOCAL-42'], 'Local remittance LOCAL-42'],
    'offshore remittance' => ['offshore-remittance', ['key' => 'send_offshore_remittance', 'reference' => 'ORIGIN-42', 'origin_country' => 'US'], 'Offshore remittance ORIGIN-42'],
]);

it('rejects a journey unavailable in the active profile', function (): void {
    config()->set('x-change.app_profile.primary', 'bank');

    expect(fn () => app(ApplicationJourneyInstructionGuard::class)->validateAndCompile(
        journeyIssuancePayload(['key' => 'loan_disbursement', 'reference' => 'LOAN-42']),
        false,
    ))->toThrow(ValidationException::class);
});

it('requires purpose evidence and one PHP disbursement', function (array $journey, array $override): void {
    config()->set('x-change.app_profile.primary', 'money-changer');
    $payload = array_replace_recursive(journeyIssuancePayload($journey), $override);

    expect(fn () => app(ApplicationJourneyInstructionGuard::class)->validateAndCompile($payload, false))
        ->toThrow(ValidationException::class);
})->with([
    'missing quote amount' => [['key' => 'exchange_payout', 'reference' => 'QUOTE-42', 'source_currency' => 'USD'], []],
    'multiple vouchers' => [['key' => 'exchange_payout', 'reference' => 'QUOTE-42', 'source_currency' => 'USD', 'source_amount' => '1.00'], ['count' => 2]],
    'other currency' => [['key' => 'exchange_payout', 'reference' => 'QUOTE-42', 'source_currency' => 'USD', 'source_amount' => '1.00'], ['cash' => ['currency' => 'USD']]],
]);

it('rejects specialized journey metadata in public issuance', function (): void {
    config()->set('x-change.app_profile.primary', 'lending-center');

    expect(fn () => app(ApplicationJourneyInstructionGuard::class)->validateAndCompile(
        journeyIssuancePayload(['key' => 'loan_disbursement', 'reference' => 'LOAN-42']),
        true,
    ))->toThrow(ValidationException::class);
});

it('rejects an unavailable journey at the Quick Generate HTTP boundary before issuance', function (): void {
    config()->set('x-change.app_profile.primary', 'bank');
    actingAsTestUser();
    $payload = journeyIssuancePayload(['key' => 'loan_disbursement', 'reference' => 'LOAN-42']);
    $payload['inputs'] = ['fields' => []];
    $payload['feedback'] = ['mobile' => null];

    $this->withHeader('Idempotency-Key', 'journey-unavailable-42')
        ->postJson(route('x-change.cockpit.quick-generate.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('metadata.custom.app_journey.key');
});
