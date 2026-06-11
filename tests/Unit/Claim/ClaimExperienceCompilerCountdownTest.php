<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;

function countdownVoucherWithRedirect(array $riderOverrides = []): Voucher
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

function countdownVoucherWithoutRedirect(): Voucher
{
    return issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'message' => 'Thank you for claiming.',
            ],
        ],
    ));
}

function resolveCountdownExperience(Voucher $voucher): array
{
    return ResolveClaimExperience::run($voucher)->toArray();
}

function countdownRedirectPhase(array $experience): ?array
{
    $phase = collect(data_get($experience, 'phases', []))
        ->firstWhere('key', 'redirect');

    return is_array($phase) ? $phase : null;
}

it('emits redirect phase countdown metadata when rider redirect url exists', function () {
    $experience = resolveCountdownExperience(
        countdownVoucherWithRedirect(),
    );

    $redirectPhase = countdownRedirectPhase($experience);

    expect($redirectPhase)->toMatchArray([
        'key' => 'redirect',
        'owner' => ClaimExperiencePayload::REDIRECT_OWNER_CLAIM_WIDGET,
        'source' => 'voucher.instructions.rider.redirect_url',
        'status' => 'active',
        'url' => 'https://example.com/success',
        'delay_seconds' => 5,
        'show_countdown' => true,
    ]);
});

it('enables redirect countdown option when rider redirect url exists', function () {
    $experience = resolveCountdownExperience(
        countdownVoucherWithRedirect(),
    );

    expect(data_get($experience, 'options.show_redirect_countdown'))->toBeTrue()
        ->and(data_get($experience, 'diagnostics.redirect_owner'))
        ->toBe(ClaimExperiencePayload::REDIRECT_OWNER_CLAIM_WIDGET);
});

it('derives countdown redirect payload from redirect phase', function () {
    $experience = resolveCountdownExperience(
        countdownVoucherWithRedirect(),
    );

    expect(ClaimExperiencePayload::redirect($experience))->toBe([
        'show_countdown' => true,
        'owner' => ClaimExperiencePayload::REDIRECT_OWNER_CLAIM_WIDGET,
        'delay_seconds' => 5,
    ]);
});

it('does not enable countdown when rider redirect url is missing', function () {
    $experience = resolveCountdownExperience(
        countdownVoucherWithoutRedirect(),
    );

    expect(data_get($experience, 'options.show_redirect_countdown'))->toBeFalse()
        ->and(data_get($experience, 'diagnostics.redirect_owner'))->toBeNull()
        ->and(countdownRedirectPhase($experience))->toBeNull()
        ->and(ClaimExperiencePayload::redirect($experience))->toBe([
            'show_countdown' => false,
            'owner' => null,
            'delay_seconds' => null,
        ]);
});

it('keeps countdown gated by claim widget redirect ownership', function () {
    $experience = [
        'options' => [
            'show_redirect_countdown' => true,
        ],
        'diagnostics' => [
            'redirect_owner' => 'x-rider',
        ],
        'phases' => [
            [
                'key' => 'redirect',
                'owner' => 'x-rider',
                'delay_seconds' => 9,
                'show_countdown' => true,
            ],
        ],
    ];

    expect(ClaimExperiencePayload::redirect($experience))->toBe([
        'show_countdown' => false,
        'owner' => 'x-rider',
        'delay_seconds' => 9,
    ]);
});
