<?php

declare(strict_types=1);

use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\Claim\ClaimEvidenceRequirements;
use LBHurtado\XChange\Services\Claim\VoucherClaimFlowCompiler;
use Symfony\Component\Yaml\Yaml;

function claimRequirementContractVoucher(array $fields): Voucher
{
    $instructions = validVoucherInstructions(overrides: [
        'inputs' => ['fields' => $fields],
    ])->toArray();

    return (new Voucher)->forceFill([
        'code' => 'AA317',
        'metadata' => ['instructions' => $instructions],
    ]);
}

beforeEach(function (): void {
    app()->instance(DriverService::class, new class extends DriverService
    {
        public function __construct()
        {
            $this->config = Yaml::parseFile(__DIR__.'/../../../config/form-flow-drivers/voucher-redemption.yaml');
        }
    });
});

it('compiles every authored requirement into the actual claim-flow DTO', function (): void {
    $contract = json_decode(file_get_contents(__DIR__.'/../../Fixtures/claim-requirement-contract.json'), true, flags: JSON_THROW_ON_ERROR);
    $requirements = array_column($contract, 'requirement');
    $voucher = claimRequirementContractVoucher($requirements);
    $instructions = app(VoucherClaimFlowCompiler::class)->compile($voucher)->instructions->toArray();
    $steps = collect($instructions['steps']);

    expect($voucher->instructions->inputs->fields)->toHaveCount(count($contract));

    foreach ($contract as $entry) {
        $step = $steps->first(fn (array $candidate): bool => data_get($candidate, 'config.step_name') === $entry['step']);

        expect($step)->not->toBeNull();

        if (isset($entry['field'])) {
            expect(collect(data_get($step, 'config.fields', []))->pluck('name')->all())->toContain($entry['field']);
        }
    }

    $snapshotted = app(ClaimEvidenceRequirements::class)->snapshot($voucher->metadata['instructions']);
    expect(data_get($snapshotted, 'metadata.custom.claim_evidence.requirements'))->toEqualCanonicalizing($requirements);
});

it('only compiles optional claim steps when their instruction is present', function (): void {
    $voucher = claimRequirementContractVoucher(['mobile']);
    $instructions = app(VoucherClaimFlowCompiler::class)->compile($voucher)->instructions->toArray();
    $stepNames = collect($instructions['steps'])->pluck('config.step_name')->all();

    expect($stepNames)->toContain('wallet_info')
        ->not->toContain('kyc_verification', 'bio_fields', 'otp_verification', 'location_capture', 'selfie_capture', 'signature_capture');
});

it('adds only the claim step or field requested by each individual requirement', function (array $entry): void {
    $voucher = claimRequirementContractVoucher([$entry['requirement']]);
    $instructions = app(VoucherClaimFlowCompiler::class)->compile($voucher)->instructions->toArray();
    $steps = collect($instructions['steps']);
    $stepNames = $steps->pluck('config.step_name')->all();

    expect($stepNames)->toContain($entry['step']);

    if (isset($entry['field'])) {
        $step = $steps->first(fn (array $candidate): bool => data_get($candidate, 'config.step_name') === $entry['step']);
        expect(collect(data_get($step, 'config.fields', []))->pluck('name')->all())->toContain($entry['field']);
    }

    if ($entry['step'] !== 'bio_fields') {
        expect($stepNames)->not->toContain('bio_fields');
    }

    foreach (['kyc_verification', 'otp_verification', 'location_capture', 'selfie_capture', 'signature_capture'] as $optionalStep) {
        if ($optionalStep !== $entry['step']) {
            expect($stepNames)->not->toContain($optionalStep);
        }
    }
})->with(array_map(
    static fn (array $entry): array => [$entry],
    json_decode(file_get_contents(__DIR__.'/../../Fixtures/claim-requirement-contract.json'), true, flags: JSON_THROW_ON_ERROR),
));
