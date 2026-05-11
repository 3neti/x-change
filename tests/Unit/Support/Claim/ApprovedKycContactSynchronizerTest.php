<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\XChange\Support\Claim\Synchronizers\ApprovedKycContactSynchronizer;

beforeEach(function (): void {
    $this->synchronizer = new ApprovedKycContactSynchronizer;
});

it('does nothing when no kyc payload is present', function (): void {
    $this->synchronizer->sync([
        'mobile' => '+639173011987',
        'country' => 'PH',
        'inputs' => [],
    ]);

    expect(Contact::query()->count())->toBe(0);
});

it('does nothing when kyc status is not approved', function (): void {
    $this->synchronizer->sync([
        'mobile' => '+639173011987',
        'country' => 'PH',
        'inputs' => [
            'kyc' => [
                'transaction_id' => 'formflow-pending',
                'status' => 'processing',
            ],
        ],
    ]);

    expect(Contact::query()->count())->toBe(0);
});

it('does nothing when mobile is missing', function (): void {
    $this->synchronizer->sync([
        'country' => 'PH',
        'inputs' => [
            'kyc' => [
                'transaction_id' => 'formflow-approved',
                'status' => 'approved',
            ],
        ],
    ]);

    expect(Contact::query()->count())->toBe(0);
});

it('creates or resolves contact from mobile and country', function (): void {
    $this->synchronizer->sync([
        'mobile' => '+639173011987',
        'country' => 'PH',
        'inputs' => [
            'kyc' => [
                'transaction_id' => 'formflow-approved',
                'status' => 'approved',
                'completed_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    $contact = Contact::query()->first();

    expect($contact)
        ->not->toBeNull()
        ->and($contact->mobile)->not->toBeNull();
});

it('updates kyc status to approved', function (): void {
    $this->synchronizer->sync([
        'mobile' => '+639173011987',
        'country' => 'PH',
        'inputs' => [
            'kyc' => [
                'transaction_id' => 'formflow-approved',
                'status' => 'approved',
                'completed_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    $contact = Contact::query()->firstOrFail();

    expect($contact->kyc_status)->toBe('approved');
});

it('stores transaction id', function (): void {
    $this->synchronizer->sync([
        'mobile' => '+639173011987',
        'country' => 'PH',
        'inputs' => [
            'kyc' => [
                'transaction_id' => 'formflow-flow-abc-123',
                'status' => 'approved',
                'completed_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    $contact = Contact::query()->firstOrFail();

    expect($contact->kyc_transaction_id)->toBe('formflow-flow-abc-123');
});

it('uses provided country when resolving contact', function (): void {
    $this->synchronizer->sync([
        'mobile' => '+639173011987',
        'country' => 'PH',
        'inputs' => [
            'kyc' => [
                'transaction_id' => 'formflow-country',
                'status' => 'approved',
                'completed_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    $contact = Contact::query()->firstOrFail();

    expect($contact->kyc_status)->toBe('approved')
        ->and($contact->kyc_transaction_id)->toBe('formflow-country');
});

it('logs warning instead of throwing when contact sync fails', function (): void {
    Log::spy();

    $this->synchronizer->sync([
        'mobile' => 'not-a-phone-number',
        'country' => 'PH',
        'inputs' => [
            'kyc' => [
                'transaction_id' => 'bad-phone',
                'status' => 'approved',
            ],
        ],
    ]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === '[ApprovedKycContactSynchronizer] Failed to sync KYC to contact'
            && ($context['mobile'] ?? null) === 'not-a-phone-number'
            && ($context['country'] ?? null) === 'PH'
        );
});
