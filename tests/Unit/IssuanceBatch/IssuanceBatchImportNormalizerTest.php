<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchImportNormalizer;

it('normalizes valid rows and calculates the exact batch total', function (): void {
    $normalizer = new IssuanceBatchImportNormalizer;
    $mapping = $normalizer->detectMapping(['Full Name', 'Phone Number', 'Amount', 'Reference']);

    $result = $normalizer->normalize([
        ['Full Name' => 'Ana Santos', 'Phone Number' => '09170000001', 'Amount' => '100.00', 'Reference' => 'A-1'],
        ['Full Name' => 'Ben Reyes', 'Phone Number' => '09170000002', 'Amount' => '250.50', 'Reference' => 'B-1'],
    ], $mapping);

    expect($result['errors'])->toBe([])
        ->and($result['total_minor'])->toBe(35050)
        ->and($result['items'][0]['status'])->toBe('valid');
});

it('rejects incomplete rows without adding them to the payable total', function (): void {
    $normalizer = new IssuanceBatchImportNormalizer;
    $mapping = $normalizer->detectMapping(['Name', 'Amount']);

    $result = $normalizer->normalize([
        ['Name' => 'Missing contact', 'Amount' => '25.00'],
    ], $mapping);

    expect($result['errors'])->toHaveCount(1)
        ->and($result['total_minor'])->toBe(0)
        ->and($result['items'][0]['status'])->toBe('invalid');
});
