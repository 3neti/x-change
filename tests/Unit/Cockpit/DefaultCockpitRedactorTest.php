<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitRedactorContract;
use LBHurtado\XChange\Support\Cockpit\DefaultCockpitRedactor;

it('redacts sensitive cockpit payload keys recursively', function () {
    $redactor = new DefaultCockpitRedactor;

    $payload = [
        'code' => 'PC-READY-001',
        'mobile' => '09171234567',
        'email' => 'recipient@example.test',
        'provider_payload' => [
            'reference_id' => 'PROVIDER-REF-001',
            'status' => 'pending',
        ],
        'journal' => [
            'event_type' => 'execution.succeeded',
            'metadata' => [
                'account_number' => '1234567890',
            ],
        ],
    ];

    $redacted = $redactor->redact($payload);

    expect($redacted)->toMatchArray([
        'code' => 'PC-READY-001',
        'mobile' => '[redacted]',
        'email' => '[redacted]',
        'provider_payload' => '[redacted]',
        'journal' => [
            'event_type' => 'execution.succeeded',
            'metadata' => [
                'account_number' => '[redacted]',
            ],
        ],
    ]);
});

it('does not mutate the original payload while redacting', function () {
    $redactor = new DefaultCockpitRedactor;

    $payload = [
        'mobile' => '09171234567',
        'nested' => [
            'account_number' => '1234567890',
        ],
    ];

    $redactor->redact($payload);

    expect($payload)->toBe([
        'mobile' => '09171234567',
        'nested' => [
            'account_number' => '1234567890',
        ],
    ]);
});

it('allows package consumers to add sensitive keys per call', function () {
    $redactor = new DefaultCockpitRedactor;

    $redacted = $redactor->redact([
        'custom_secret_field' => 'do-not-leak',
        'safe_label' => 'Visible',
    ], ['custom_secret_field']);

    expect($redacted)->toBe([
        'custom_secret_field' => '[redacted]',
        'safe_label' => 'Visible',
    ]);
});

it('binds the cockpit redactor contract', function () {
    expect(app(CockpitRedactorContract::class))
        ->toBeInstanceOf(DefaultCockpitRedactor::class);
});
