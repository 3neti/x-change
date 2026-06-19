<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;

function issuedNamedSliceVoucherForExperience(): Voucher
{
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 155,
        overrides: [
            'cash' => [
                'amount' => 155,
                'currency' => 'PHP',
                'slice_mode' => 'open',
                'max_slices' => 2,
                'min_withdrawal' => 75,
                'validation' => [
                    'country' => 'PH',
                ],
            ],
        ],
    ));

    $metadata = $voucher->metadata ?? [];
    data_set($metadata, 'instructions.metadata.custom.named_slices', [
        [
            'id' => 'slice_1',
            'amount' => 80,
            'description' => 'Buy coffee',
        ],
        [
            'id' => 'slice_2',
            'amount' => 75,
            'description' => 'Buy doughnut',
        ],
    ]);
    data_set($metadata, 'instructions.metadata.custom.named_slice_policy', [
        'mode' => 'named',
        'selection' => 'one_or_many',
        'enforced' => true,
    ]);

    $voucher->forceFill(['metadata' => $metadata])->save();

    return $voucher->fresh();
}

it('returns claim experience for a claimable named slice voucher', function () {
    $voucher = issuedNamedSliceVoucherForExperience();

    $this->getJson("/x/claim/{$voucher->code}/experience")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('code', $voucher->code)
        ->assertJsonPath('claim_experience.phases.1.key', 'form_flow')
        ->assertJsonPath('claim_experience.phases.1.fields.0.type', 'slice_selector')
        ->assertJsonPath('claim_experience.phases.1.fields.0.options.0.description', 'Buy coffee');
});

it('rejects missing voucher claim experience lookup', function () {
    $this->getJson('/x/claim/MISSING/experience')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Invalid Pay Code.');
});
