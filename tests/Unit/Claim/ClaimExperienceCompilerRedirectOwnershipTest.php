<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;

function redirectOwnershipVoucherWithRiderRedirect(array $riderOverrides = []): Voucher
{
    return issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => array_merge([
                'message' => 'Thank you for claiming.',
                'url' => 'https://example.com/success',
            ], $riderOverrides),
        ],
    ));
}

function resolveRedirectOwnershipExperience(Voucher $voucher): array
{
    return ResolveClaimExperience::run($voucher)->toArray();
}

function redirectOwnershipPhase(array $experience): ?array
{
    $phase = collect(data_get($experience, 'phases', []))
        ->firstWhere('key', 'redirect');

    return is_array($phase) ? $phase : null;
}

it('assigns explicit claim widget redirect owner when voucher has rider redirect url', function () {
    $experience = resolveRedirectOwnershipExperience(
        redirectOwnershipVoucherWithRiderRedirect(),
    );

    expect(data_get($experience, 'diagnostics.redirect_owner'))
        ->toBe(ClaimExperiencePayload::REDIRECT_OWNER_CLAIM_WIDGET);
});

it('emits redirect phase with owner source status and url', function () {
    $experience = resolveRedirectOwnershipExperience(
        redirectOwnershipVoucherWithRiderRedirect(),
    );

    $redirectPhase = redirectOwnershipPhase($experience);

    expect($redirectPhase)->toMatchArray([
        'key' => 'redirect',
        'owner' => ClaimExperiencePayload::REDIRECT_OWNER_CLAIM_WIDGET,
        'source' => 'voucher.instructions.rider.redirect_url',
        'status' => 'active',
        'url' => 'https://example.com/success',
    ]);
});

it('derives redirect payload from claim experience', function () {
    $experience = resolveRedirectOwnershipExperience(
        redirectOwnershipVoucherWithRiderRedirect(),
    );

    expect(ClaimExperiencePayload::redirect($experience))->toMatchArray([
        'owner' => ClaimExperiencePayload::REDIRECT_OWNER_CLAIM_WIDGET,
    ]);
});

it('shows countdown when redirect is owned by claim widget', function () {
    $experience = resolveRedirectOwnershipExperience(
        redirectOwnershipVoucherWithRiderRedirect(),
    );

    expect(ClaimExperiencePayload::redirect($experience))
        ->toMatchArray([
            'owner' => ClaimExperiencePayload::REDIRECT_OWNER_CLAIM_WIDGET,
            'show_countdown' => true,
        ]);
});

it('does not show countdown when redirect owner is not claim widget', function () {
    $experience = [
        'diagnostics' => [
            'redirect_owner' => 'x-rider',
        ],
        'options' => [
            'show_redirect_countdown' => true,
        ],
        'phases' => [
            [
                'key' => 'redirect',
                'owner' => 'x-rider',
                'delay_seconds' => 5,
            ],
        ],
    ];

    expect(ClaimExperiencePayload::redirect($experience))->toMatchArray([
        'owner' => 'x-rider',
        'show_countdown' => false,
        'delay_seconds' => 5,
    ]);
});
