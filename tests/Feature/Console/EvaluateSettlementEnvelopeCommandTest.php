<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function persistSettlementEnvelopeEvidence($voucher): object
{
    $metadata = is_array($voucher->metadata ?? null)
        ? $voucher->metadata
        : [];

    $voucher->forceFill([
        'metadata' => [
            ...$metadata,
            'flow_type' => 'settlement',
            'settlement_driver' => 'philhealth-bst',
            'settlement_payload' => [
                'patient_name' => 'Juan Dela Cruz',
                'patient_mobile' => '09171234567',
            ],
            'settlement_checklist' => [
                'amount_verified' => true,
            ],
        ],
    ])->save();

    return $voucher->refresh();
}

it('evaluates philhealth bst envelope readiness through console as not ready', function () {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
    config()->set('x-change.settlement.drivers_path', settlementEnvelopeDriversPath());

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $exitCode = Artisan::call('xchange:settlement-envelope:evaluate', [
        'voucher_code' => $voucher->code,
        '--driver' => 'philhealth-bst',
        '--gate' => 'settleable',
        '--json' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('"ready": false')
        ->and($output)->toContain('"missing": [')
        ->and($output)->toContain('"payload_present"')
        ->and($output)->toContain('"amount_verified"');
});

it('evaluates philhealth bst envelope readiness through console as ready', function () {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
    config()->set('x-change.settlement.drivers_path', settlementEnvelopeDriversPath());

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $voucher = persistSettlementEnvelopeEvidence($voucher);

    $exitCode = Artisan::call('xchange:settlement-envelope:evaluate', [
        'voucher_code' => $voucher->code,
        '--driver' => 'philhealth-bst',
        '--gate' => 'settleable',
        '--json' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"ready": true')
        ->and($output)->toContain('"satisfied": [')
        ->and($output)->toContain('"payload_present"')
        ->and($output)->toContain('"amount_verified"')
        ->and($output)->toContain('"missing": []');
});

it('returns failure for unknown voucher code', function () {
    $exitCode = Artisan::call('xchange:settlement-envelope:evaluate', [
        'voucher_code' => 'MISSING-CODE',
        '--driver' => 'philhealth-bst',
        '--gate' => 'settleable',
        '--json' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Voucher [MISSING-CODE] was not found.');
});

it('uses philhealth bst as the default driver option', function () {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
    config()->set('x-change.settlement.drivers_path', settlementEnvelopeDriversPath());

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $voucher = persistSettlementEnvelopeEvidence($voucher);

    $exitCode = Artisan::call('xchange:settlement-envelope:evaluate', [
        'voucher_code' => $voucher->code,
        '--json' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"driver": "philhealth-bst"')
        ->and($output)->toContain('"gate": "settleable"')
        ->and($output)->toContain('"ready": true');
});
