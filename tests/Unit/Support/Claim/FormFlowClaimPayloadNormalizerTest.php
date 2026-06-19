<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\FormFlowClaimPayloadNormalizer;

beforeEach(function (): void {
    $this->normalizer = new FormFlowClaimPayloadNormalizer;
});

it('preserves wallet fields and builds mobile country bank account payload', function (): void {
    $payload = $this->normalizer->normalize([
        'wallet_info' => [
            'mobile' => '+639173011987',
            'recipient_country' => 'PH',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
            'amount' => '100',
            'slice_ids' => ['slice_1'],
            'settlement_rail' => 'INSTAPAY',
        ],
    ]);

    expect($payload)
        ->toHaveKey('mobile', '+639173011987')
        ->toHaveKey('country', 'PH')
        ->toHaveKey('bank_code', 'GXCHPHM2XXX')
        ->toHaveKey('account_number', '09173011987')
        ->toHaveKey('amount', '100')
        ->toHaveKey('slice_ids', ['slice_1'])
        ->toHaveKey('settlement_rail', 'INSTAPAY')
        ->and($payload['inputs'])
        ->toHaveKey('mobile', '+639173011987')
        ->toHaveKey('bank_code', 'GXCHPHM2XXX')
        ->toHaveKey('account_number', '09173011987')
        ->not->toHaveKey('recipient_country')
        ->not->toHaveKey('amount')
        ->not->toHaveKey('slice_ids')
        ->not->toHaveKey('settlement_rail');
});

it('defaults country to PH when recipient country is missing', function (): void {
    $payload = $this->normalizer->normalize([
        'wallet_info' => [
            'mobile' => '+639173011987',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
    ]);

    expect($payload['country'])->toBe('PH');
});

it('preserves selfie signature and location flat fields', function (): void {
    $payload = $this->normalizer->normalize([
        'wallet_info' => [
            'mobile' => '+639173011987',
            'recipient_country' => 'PH',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
        'selfie_capture' => [
            'selfie' => 'data:image/jpeg;base64,selfie',
            'image' => 'data:image/jpeg;base64,selfie',
            'width' => 640,
            'height' => 480,
            'format' => 'image/jpeg',
        ],
        'signature_capture' => [
            'signature' => 'data:image/png;base64,signature',
            'image' => 'data:image/png;base64,signature',
            'format' => 'image/png',
        ],
        'location_capture' => [
            'latitude' => 14.5995,
            'longitude' => 121.0288,
            'accuracy' => 20,
            'formatted_address' => 'Makati City',
        ],
    ]);

    expect($payload['inputs'])
        ->toHaveKey('selfie', 'data:image/jpeg;base64,selfie')
        ->toHaveKey('signature', 'data:image/png;base64,signature')
        ->toHaveKey('latitude', 14.5995)
        ->toHaveKey('longitude', 121.0288)
        ->toHaveKey('accuracy', 20)
        ->toHaveKey('formatted_address', 'Makati City');
});

it('nests kyc verification into inputs kyc', function (): void {
    $payload = $this->normalizer->normalize([
        'wallet_info' => [
            'mobile' => '+639173011987',
            'recipient_country' => 'PH',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
        'kyc_verification' => [
            'transaction_id' => 'formflow-abc123',
            'status' => 'approved',
            'completed_at' => '2026-05-11T13:17:46+08:00',
            'id_number' => 'ABC123456',
            'id_type' => 'national_id',
        ],
    ]);

    expect($payload['inputs'])
        ->toHaveKey('kyc')
        ->and($payload['inputs']['kyc'])
        ->toHaveKey('transaction_id', 'formflow-abc123')
        ->toHaveKey('status', 'approved')
        ->toHaveKey('completed_at', '2026-05-11T13:17:46+08:00')
        ->toHaveKey('id_number', 'ABC123456')
        ->toHaveKey('id_type', 'national_id');
});

it('normalizes auto approved kyc status to approved', function (): void {
    $payload = $this->normalizer->normalize([
        'wallet_info' => [
            'mobile' => '+639173011987',
            'recipient_country' => 'PH',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
        'kyc_verification' => [
            'transaction_id' => 'formflow-abc123',
            'status' => 'auto_approved',
        ],
    ]);

    expect($payload['inputs']['kyc']['status'])->toBe('approved');
});

it('normalizes success kyc status to approved', function (): void {
    $payload = $this->normalizer->normalize([
        'kyc_verification' => [
            'transaction_id' => 'formflow-abc123',
            'status' => 'success',
        ],
    ]);

    expect($payload['inputs']['kyc']['status'])->toBe('approved');
});

it('preserves flat kyc compatibility fields', function (): void {
    $payload = $this->normalizer->normalize([
        'wallet_info' => [
            'mobile' => '+639173011987',
            'recipient_country' => 'PH',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
        'kyc_verification' => [
            'transaction_id' => 'formflow-abc123',
            'status' => 'approved',
            'name' => 'Juan Dela Cruz',
            'date_of_birth' => '1990-01-01',
            'address' => 'Makati City',
            'id_number' => 'ABC123456',
            'id_type' => 'national_id',
            'nationality' => 'PH',
            'id_card_full' => 'https://example.test/id-full.jpg',
            'id_card_cropped' => 'https://example.test/id-cropped.jpg',
            'selfie' => 'https://example.test/selfie.jpg',
        ],
    ]);

    expect($payload['inputs'])
        ->toHaveKey('kyc')
        ->toHaveKey('transaction_id', 'formflow-abc123')
        ->toHaveKey('status', 'approved')
        ->toHaveKey('name', 'Juan Dela Cruz')
        ->toHaveKey('date_of_birth', '1990-01-01')
        ->toHaveKey('address', 'Makati City')
        ->toHaveKey('id_number', 'ABC123456')
        ->toHaveKey('id_type', 'national_id')
        ->toHaveKey('nationality', 'PH')
        ->toHaveKey('id_card_full', 'https://example.test/id-full.jpg')
        ->toHaveKey('id_card_cropped', 'https://example.test/id-cropped.jpg')
        ->toHaveKey('selfie', 'https://example.test/selfie.jpg')
        ->and($payload['inputs']['kyc'])
        ->toHaveKey('transaction_id', 'formflow-abc123')
        ->toHaveKey('status', 'approved');
});

it('supports transactionId alias and normalizes it to transaction_id', function (): void {
    $payload = $this->normalizer->normalize([
        'kyc_verification' => [
            'transactionId' => 'legacy-transaction-id',
            'status' => 'approved',
        ],
    ]);

    expect($payload['inputs']['kyc'])
        ->toHaveKey('transaction_id', 'legacy-transaction-id');
});

it('aliases full name to name for voucher input validation', function (): void {
    $payload = app(FormFlowClaimPayloadNormalizer::class)->normalize([
        [
            'mobile' => '+639171234567',
            'full_name' => 'Juan Dela Cruz',
        ],
    ]);

    expect($payload['inputs']['full_name'])->toBe('Juan Dela Cruz')
        ->and($payload['inputs']['name'])->toBe('Juan Dela Cruz');
});

it('aliases date of birth to birth date for voucher input validation', function (): void {
    $payload = app(FormFlowClaimPayloadNormalizer::class)->normalize([
        [
            'mobile' => '+639171234567',
            'date_of_birth' => '1990-01-01',
        ],
    ]);

    expect($payload['inputs']['date_of_birth'])->toBe('1990-01-01')
        ->and($payload['inputs']['birth_date'])->toBe('1990-01-01');
});
