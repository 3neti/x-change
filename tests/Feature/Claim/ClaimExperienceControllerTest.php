<?php

declare(strict_types=1);

use LBHurtado\Voucher\Enums\VoucherSliceSelectionPolicy;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\VoucherSlicePlanFactory;

function issuedNamedSliceVoucherForExperience(): Voucher
{
    $plan = app(VoucherSlicePlanFactory::class)->scheduled(
        totalMinor: 15_500,
        currency: 'PHP',
        slices: [
            ['id' => 'slice_1', 'label' => 'Buy coffee', 'amount_minor' => 8_000],
            ['id' => 'slice_2', 'label' => 'Buy doughnut', 'amount_minor' => 7_500],
        ],
        selection: VoucherSliceSelectionPolicy::OneOrMany,
    );
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 155,
        overrides: [
            'cash' => [
                'amount' => 155,
                'currency' => 'PHP',
                'validation' => [
                    'country' => 'PH',
                ],
            ],
            'slice_plan' => $plan->canonicalArray(),
        ],
    ));

    return $voucher;
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
