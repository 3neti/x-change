<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;

function formFlowOwnershipVoucher(): Voucher
{
    return issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'message' => 'Thank you for claiming.',
                'url' => 'https://example.com/success',
            ],
        ],
    ));
}

function resolveFormFlowOwnershipExperience(Voucher $voucher): array
{
    return ResolveClaimExperience::run($voucher)->toArray();
}

function formFlowOwnershipPhase(array $experience): ?array
{
    $phase = collect(data_get($experience, 'phases', []))
        ->firstWhere('key', 'form_flow');

    return is_array($phase) ? $phase : null;
}

it('emits form flow phase as claim widget owned', function () {
    $experience = resolveFormFlowOwnershipExperience(
        formFlowOwnershipVoucher(),
    );

    expect(formFlowOwnershipPhase($experience))->toMatchArray([
        'key' => 'form_flow',
        'owner' => 'claim-widget',
        'status' => 'active',
    ]);
});

it('emits form flow ownership diagnostics', function () {
    $experience = resolveFormFlowOwnershipExperience(
        formFlowOwnershipVoucher(),
    );

    expect(data_get($experience, 'diagnostics.form_flow_owner'))
        ->toBe('claim-widget');
});

it('keeps form flow phase active alongside rider success and redirect phases', function () {
    $experience = resolveFormFlowOwnershipExperience(
        formFlowOwnershipVoucher(),
    );

    $phaseKeys = collect(data_get($experience, 'phases', []))
        ->pluck('key')
        ->all();

    expect($phaseKeys)->toContain('form_flow')
        ->and($phaseKeys)->toContain('success_rider')
        ->and($phaseKeys)->toContain('redirect');
});
