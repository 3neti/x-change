<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\Claim\ClaimExperienceCompiler;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;

function fakeClaimVoucher(array $instructionOverrides = []): Voucher
{
    $instructions = array_replace_recursive([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'country' => 'PH',
            ],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [],
        'rider' => [],
    ], $instructionOverrides);

    return (new Voucher())->forceFill([
        'code' => 'CLAIM1234',
        'metadata' => [
            'instructions' => $instructions,
        ],
    ]);
}

it('compiles a rider-first claim experience when rider splash exists', function () {
    $voucher = fakeClaimVoucher([
        'rider' => [
            'splash' => '<h1>Welcome</h1>',
            'message' => 'SUCCESS DEMO: Thank you for claiming.',
            'url' => 'https://example.com/after-claim',
        ],
    ]);

    $experience = app(ClaimExperienceCompiler::class)
        ->compile($voucher)
        ->toArray();

    $phases = collect($experience['phases']);

    expect($experience['version'])->toBe(1)
        ->and($experience['entry']['mode'])->toBe('rider_first')
        ->and($experience['entry']['initial_phase'])->toBe('rider_intro')
        ->and(ClaimExperiencePayload::isXRiderSplash($experience))->toBeTrue()
        ->and(ClaimExperiencePayload::shouldSkipConsumedSplash($experience))->toBeTrue()
        ->and(ClaimExperiencePayload::isClaimWidgetRedirect($experience))->toBeTrue()
        ->and($experience['diagnostics']['duplicate_splash_prevented'])->toBeTrue()
        ->and($experience['diagnostics']['form_flow_splash_policy'])->toBe('skip_consumed')
        ->and($phases->pluck('key')->all())->toContain(
            'rider_intro',
            'pre_claim',
            'form_flow',
            'confirmation',
            'success_rider',
            'redirect',
        );

    $formFlow = $phases->firstWhere('key', 'form_flow');

    expect($formFlow['owner'])->toBe('form-flow')
        ->and($formFlow['source'])->toBe('voucher-redemption.yaml')
        ->and($formFlow['skip_stages'])->toContain('splash');
});

it('keeps form-flow splash available when rider intro splash does not exist', function () {
    $voucher = fakeClaimVoucher([
        'rider' => [
            'message' => 'Thank you for claiming.',
        ],
    ]);

    $experience = app(ClaimExperienceCompiler::class)
        ->compile($voucher)
        ->toArray();

    $phases = collect($experience['phases']);
    $formFlow = $phases->firstWhere('key', 'form_flow');

    expect($experience['entry']['mode'])->toBe('form_first')
        ->and($experience['entry']['initial_phase'])->toBe('pre_claim')
        ->and(ClaimExperiencePayload::shouldSkipConsumedSplash($experience))->toBeFalse()
        ->and(ClaimExperiencePayload::isClaimWidgetRedirect($experience))->toBeFalse()
        ->and(ClaimExperiencePayload::isFormFlowSplash($experience))->toBeTrue()
        ->and($experience['diagnostics']['duplicate_splash_prevented'])->toBeFalse()
        ->and($experience['diagnostics']['form_flow_splash_policy'])->toBe('allow')
        ->and($phases->pluck('key')->all())->not->toContain('rider_intro')
        ->and($formFlow['skip_stages'])->toBe([]);
});

it('assigns exactly one redirect owner when rider url exists', function () {
    $voucher = fakeClaimVoucher([
        'rider' => [
            'url' => 'https://example.com/after-claim',
        ],
    ]);

    $experience = app(ClaimExperienceCompiler::class)
        ->compile($voucher)
        ->toArray();

    $redirectPhases = collect($experience['phases'])
        ->where('key', 'redirect')
        ->values();

    expect($redirectPhases)->toHaveCount(1)
        ->and($redirectPhases[0]['owner'])->toBe('claim-widget')
        ->and($redirectPhases[0]['url'])->toBe('https://example.com/after-claim')
        ->and($redirectPhases[0]['delay_seconds'])->toBe(5)
        ->and($redirectPhases[0]['show_countdown'])->toBeTrue()
        ->and($experience['options']['show_redirect_countdown'])->toBeTrue()
        ->and(ClaimExperiencePayload::isClaimWidgetRedirect($experience))->toBeTrue();
});

it('emits no anonymous phases', function () {
    $voucher = fakeClaimVoucher([
        'rider' => [
            'splash' => '<h1>Welcome</h1>',
            'message' => 'Done.',
            'url' => 'https://example.com',
        ],
    ]);

    $experience = app(ClaimExperienceCompiler::class)
        ->compile($voucher)
        ->toArray();

    collect($experience['phases'])->each(function (array $phase) {
        expect($phase['key'] ?? null)->toBeString()->not->toBe('')
            ->and($phase['owner'] ?? null)->toBeString()->not->toBe('')
            ->and($phase['source'] ?? null)->toBeString()->not->toBe('')
            ->and($phase['status'] ?? null)->toBeString()->not->toBe('');
    });
});
