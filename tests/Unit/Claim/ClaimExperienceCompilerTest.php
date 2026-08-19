<?php

declare(strict_types=1);

use LBHurtado\Voucher\Enums\VoucherSliceSelectionPolicy;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\VoucherSlicePlanFactory;
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

    return (new Voucher)->forceFill([
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

it('renders format-aware Rider content into sanitized claim HTML', function () {
    $voucher = fakeClaimVoucher([
        'rider' => [
            'message' => '**Claim complete** <script>alert(1)</script>',
            'message_format' => 'markdown',
            'splash' => "# Welcome\n\n[Unsafe](javascript:alert(1))",
            'splash_format' => 'markdown',
        ],
    ]);

    $experience = app(ClaimExperienceCompiler::class)
        ->compile($voucher)
        ->toArray();

    $phases = collect($experience['phases']);
    $splash = $phases->firstWhere('key', 'rider_intro');
    $message = $phases->firstWhere('key', 'success_rider');

    expect(data_get($splash, 'stages.0.content_type'))->toBe('html')
        ->and(data_get($splash, 'stages.0.content'))->toContain('<h1>Welcome</h1>')
        ->and(data_get($splash, 'stages.0.content'))->not->toContain('javascript:')
        ->and(data_get($message, 'stages.0.content_type'))->toBe('html')
        ->and(data_get($message, 'stages.0.content'))->toContain('<strong>Claim complete</strong>')
        ->and(data_get($message, 'stages.0.content'))->not->toContain('<script>');
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

it('emits a slice selector field for named slice vouchers', function () {
    $plan = app(VoucherSlicePlanFactory::class)->scheduled(
        totalMinor: 1_000_000,
        currency: 'PHP',
        slices: [
            ['id' => 'slice_1', 'label' => 'Buy Product 1', 'amount_minor' => 600_000],
            ['id' => 'slice_2', 'label' => 'Pay for Service 1', 'amount_minor' => 400_000],
        ],
        selection: VoucherSliceSelectionPolicy::OneOrMany,
    );
    $voucher = fakeClaimVoucher([
        'slice_plan' => $plan->canonicalArray(),
    ]);

    $experience = app(ClaimExperienceCompiler::class)
        ->compile($voucher)
        ->toArray();

    $formFlow = collect($experience['phases'])->firstWhere('key', 'form_flow');
    $sliceField = collect($formFlow['fields'])->firstWhere('key', 'slice_ids');

    expect($formFlow['fields'])->toHaveCount(1)
        ->and(collect($formFlow['fields'])->pluck('key')->all())->toBe(['slice_ids'])
        ->and($sliceField['type'])->toBe('slice_selector')
        ->and($sliceField['options'])->toHaveCount(2);
});

it('emits a passcode gate before payout details when issuer secret validation is required', function () {
    $voucher = fakeClaimVoucher([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'country' => 'PH',
                'secret' => 'ABC123',
            ],
        ],
    ]);

    $experience = app(ClaimExperienceCompiler::class)
        ->compile($voucher)
        ->toArray();

    $formFlow = collect($experience['phases'])->firstWhere('key', 'form_flow');
    $secretField = collect($formFlow['fields'])->firstWhere('key', 'secret');

    expect($formFlow['owner'])->toBe('claim-widget')
        ->and($formFlow['fields'])->toHaveCount(1)
        ->and($secretField['type'])->toBe('password')
        ->and($secretField['label'])->toBe('Pay Code Passcode')
        ->and($secretField['required'])->toBeTrue()
        ->and($secretField['presentation'])->toBe('claim_secret_gate')
        ->and($experience['diagnostics']['form_flow_owner'])->toBe('claim-widget');
});

it('emits bounded amount input for canonical flexible slice plans', function () {
    $plan = app(VoucherSlicePlanFactory::class)->flexible(
        totalMinor: 10_000,
        currency: 'PHP',
        maxSlices: 4,
        minAmountMinor: 500,
    );
    $voucher = fakeClaimVoucher([
        'slice_plan' => $plan->canonicalArray(),
    ]);

    $experience = app(ClaimExperienceCompiler::class)
        ->compile($voucher)
        ->toArray();

    $formFlow = collect($experience['phases'])->firstWhere('key', 'form_flow');
    $amount = collect($formFlow['fields'])->firstWhere('key', 'amount');

    expect($formFlow['owner'])->toBe('claim-widget')
        ->and($amount['type'])->toBe('number')
        ->and($amount['required'])->toBeTrue()
        ->and($amount['min'])->toBe(5)
        ->and($amount['max'])->toBe(100)
        ->and($amount['step'])->toBe(0.01);
});
