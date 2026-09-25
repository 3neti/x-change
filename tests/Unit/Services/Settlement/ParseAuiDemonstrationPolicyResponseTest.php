<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Data\Settlement\AuiDemonstrationPolicyResponseData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;
use LBHurtado\XChange\Services\Settlement\ParseAuiDemonstrationPolicyResponse;

it('accepts an exact demo response including equivalent timezone offsets', function (): void {
    $payload = strictAuiResponse();
    $payload['effective_at'] = '2026-09-25T08:00:00+08:00';
    $result = app(ParseAuiDemonstrationPolicyResponse::class)->handle($payload, strictAuiPreparation());
    expect($result->policyReference)->toBe('AUI-DEMO-0123456789ABCDEF')
        ->and($result->effectiveAt->equalTo(strictAuiPreparation()->coverageEffectiveAt))->toBeTrue();
});

it('rejects unknown response keys and missing required keys', function (): void {
    $parser = app(ParseAuiDemonstrationPolicyResponse::class);
    $payload = strictAuiResponse();
    expect(fn () => $parser->handle([...$payload, 'private_data' => 'must-not-log'], strictAuiPreparation()))->toThrow(DomainException::class);
    foreach (array_keys($payload) as $key) {
        $missing = $payload;
        unset($missing[$key]);
        expect(fn () => $parser->handle($missing, strictAuiPreparation()))->toThrow(DomainException::class);
    }
});

it('rejects ambiguous, malformed or normalized coverage timestamps', function (mixed $timestamp): void {
    $payload = strictAuiResponse();
    $payload['effective_at'] = $timestamp;
    expect(fn () => app(ParseAuiDemonstrationPolicyResponse::class)->handle($payload, strictAuiPreparation()))->toThrow(DomainException::class);
})->with([
    'missing value' => [null], 'blank' => [''], 'relative' => ['today'], 'relative clock' => ['now'],
    'no zone' => ['2026-09-25T00:00:00'], 'date only' => ['2026-09-25'], 'numeric' => [1790294400],
    'object' => [['value' => '2026-09-25T00:00:00Z']], 'space separator' => ['2026-09-25 00:00:00Z'],
    'bad date' => ['2026-02-30T00:00:00Z'], 'hour rollover' => ['2026-09-24T24:00:00Z'],
    'leap second' => ['2026-09-24T23:59:60Z'], 'bad offset' => ['2026-09-25T00:00:00+99:00'],
    'extra suffix' => ['2026-09-25T00:00:00Z tomorrow'], 'whitespace' => [' 2026-09-25T00:00:00Z'],
]);

it('requires the demo constants and exact reference format', function (string $key, mixed $value): void {
    $payload = strictAuiResponse();
    $payload[$key] = $value;
    expect(fn () => app(ParseAuiDemonstrationPolicyResponse::class)->handle($payload, strictAuiPreparation()))->toThrow(DomainException::class);
})->with([
    ['demonstration_only', 'true'], ['demonstration_only', false], ['document_ready', true],
    ['document_ready', 0], ['status', 'issued'], ['schema', 'unknown'], ['product_code', 'PA5000'],
    ['policy_reference', 'AUI-DEMO-short'], ['policy_reference', 'AUI-DEMO-0123456789ABCDEG'],
    ['policy_reference', 'AUI-DEMO-0123456789ABCDEF\n'],
]);

it('accepts null expiry only when the authoritative preparation also has no expiry', function (): void {
    $payload = strictAuiResponse();
    $payload['expires_at'] = null;
    $parser = app(ParseAuiDemonstrationPolicyResponse::class);
    expect($parser->handle($payload, strictAuiPreparation(false))->expiresAt)->toBeNull()
        ->and(fn () => $parser->handle($payload, strictAuiPreparation()))->toThrow(DomainException::class)
        ->and(fn () => $parser->handle(strictAuiResponse(), strictAuiPreparation(false)))->toThrow(DomainException::class);
});

function strictAuiResponse(): array
{
    return [
        'schema' => AuiDemonstrationPolicyResponseData::SCHEMA, 'status' => 'issued_demo',
        'policy_reference' => 'AUI-DEMO-0123456789ABCDEF', 'product_code' => 'AUI-PA-DEMO',
        'effective_at' => '2026-09-25T00:00:00Z', 'expires_at' => '2026-09-26T00:00:00Z',
        'document_ready' => false, 'demonstration_only' => true,
    ];
}

function strictAuiPreparation(bool $expires = true): PolicyCompletionPreparationData
{
    return new PolicyCompletionPreparationData(
        driverId: 'aui.personal-accident.provisional-cover', driverVersion: '1.0.0',
        idempotencyKey: 'strict-parser:demo', fingerprint: str_repeat('a', 64),
        projectionReference: 'projection', envelopeReference: 'envelope', envelopePayloadVersion: 1,
        coverageReference: 'coverage', coverageType: 'provisional_personal_accident', coverageAmountMinor: 500000,
        currency: 'PHP', coverageEffectiveAt: CarbonImmutable::parse('2026-09-25T00:00:00Z'),
        coverageExpiresAt: $expires ? CarbonImmutable::parse('2026-09-26T00:00:00Z') : null,
        paymentRecognitionReference: 'recognition', paymentAmountMinor: 5000,
        paymentSettledAt: CarbonImmutable::parse('2026-09-25T00:00:00Z'),
        completionPayCodeReference: 'completion', claimNumber: 1, applicantEvidence: [],
    );
}
