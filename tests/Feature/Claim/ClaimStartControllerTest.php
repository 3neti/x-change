<?php

declare(strict_types=1);

use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;

function claimVoucherWithRiderSplash(): Voucher
{
    return issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'splash' => '<h1>Welcome</h1>',
                'message' => 'SUCCESS DEMO: Thank you for claiming.',
                'url' => 'https://example.com/after-claim',
            ],
        ],
    ));
}

function claimVoucherWithoutRiderSplash(): Voucher
{
    return issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'message' => 'SUCCESS DEMO: Thank you for claiming.',
                'url' => 'https://example.com/after-claim',
            ],
        ],
    ));
}

function splashStep(): array
{
    return [
        [
            'handler' => 'splash',
            'config' => [
                'title' => 'Welcome',
                'content' => '<h1>Welcome</h1>',
                'timeout' => 0,
            ],
        ],
    ];
}

function mockDriverForClaimVoucher($test, Voucher $voucher, array $steps = []): void
{
    $driver = Mockery::mock(DriverService::class);

    $driver->shouldReceive('transform')
        ->once()
        ->with(Mockery::on(fn ($actual) => $actual instanceof Voucher && $actual->is($voucher)))
        ->andReturn(FormFlowInstructionsData::from([
            'reference_id' => 'claim-'.$voucher->code.'-test',
            'steps' => $steps,
            'callbacks' => [
                'on_complete' => '/x/claim/'.$voucher->code.'/complete',
            ],
            'metadata' => [
                'voucher_code' => $voucher->code,
            ],
        ]));

    app()->instance(DriverService::class, $driver);
}

function assertClaimExperienceStartFlow($test, callable $assertions, string $flowId = 'flow-claim-test'): void
{
    $formFlow = Mockery::mock(FormFlowService::class);

    $formFlow->shouldReceive('startFlow')
        ->once()
        ->with(Mockery::on(function (FormFlowInstructionsData $instructions) use ($assertions) {
            $payload = $instructions->toArray();
            $experience = data_get($payload, 'metadata.claim_experience');

            expect($experience)->toBeArray();

            $assertions($experience, $payload);

            return true;
        }))
        ->andReturn([
            'flow_id' => $flowId,
        ]);

    app()->instance(FormFlowService::class, $formFlow);
}

it('attaches claim experience shadow payload before starting form flow', function () {
    $this->withoutMiddleware();

    $voucher = claimVoucherWithRiderSplash();

    mockDriverForClaimVoucher($this, $voucher);

    assertClaimExperienceStartFlow($this, function (array $experience) {
        expect(data_get($experience, 'version'))->toBe(1)
            ->and(data_get($experience, 'entry.mode'))->toBe('rider_first')
            ->and(data_get($experience, 'consumed.splash'))->toBeTrue()
            ->and(data_get($experience, 'diagnostics.duplicate_splash_prevented'))->toBeTrue()
            ->and(collect(data_get($experience, 'phases'))->pluck('key')->all())
            ->toContain('rider_intro', 'form_flow', 'success_rider', 'redirect');
    }, 'flow-shadow-test');

    $this->get('/x/claim?code='.$voucher->code)
        ->assertRedirect('/form-flow/flow-shadow-test');
});

it('persists claim experience inside started form flow state', function () {
    $this->withoutMiddleware();

    $voucher = claimVoucherWithRiderSplash();

    mockDriverForClaimVoucher($this, $voucher);

    $response = $this->get('/x/claim?code='.$voucher->code);

    $response->assertRedirect();

    $location = $response->headers->get('Location');

    expect($location)->toStartWith('http://localhost/form-flow/');

    $flowId = str($location)->afterLast('/')->toString();

    $state = session("form_flow.{$flowId}");

    expect($state)->toBeArray();

    $experience = ClaimExperiencePayload::fromState($state);

    expect($experience)->toBeArray()
        ->and(data_get($experience, 'version'))->toBe(1)
        ->and(data_get($experience, 'entry.mode'))->toBe('rider_first')
        ->and(data_get($experience, 'consumed.splash'))->toBeTrue()
        ->and(data_get($experience, 'options.skip_consumed_splash'))->toBeTrue()
        ->and(data_get($experience, 'diagnostics.duplicate_splash_prevented'))->toBeTrue()
        ->and(collect(data_get($experience, 'phases'))->pluck('key')->all())
        ->toContain('rider_intro', 'form_flow', 'success_rider', 'redirect');
});

it('emits claim experience option to skip consumed splash for rider splash vouchers', function () {
    $this->withoutMiddleware();

    $voucher = claimVoucherWithRiderSplash();

    mockDriverForClaimVoucher($this, $voucher, splashStep());

    assertClaimExperienceStartFlow($this, function (array $experience) {
        expect(ClaimExperiencePayload::isXRiderSplash($experience))->toBeTrue()
            ->and(ClaimExperiencePayload::shouldSkipConsumedSplash($experience))->toBeTrue()
            ->and(data_get($experience, 'diagnostics.form_flow_splash_policy'))->toBe('skip_consumed');
    }, 'flow-skip-option-test');

    $this->get('/x/claim?code='.$voucher->code)
        ->assertRedirect('/form-flow/flow-skip-option-test');
});

it('does not emit consumed splash skip option when voucher has no rider splash', function () {
    $this->withoutMiddleware();

    $voucher = claimVoucherWithoutRiderSplash();

    mockDriverForClaimVoucher($this, $voucher, splashStep());

    assertClaimExperienceStartFlow($this, function (array $experience) {
        expect(ClaimExperiencePayload::shouldSkipConsumedSplash($experience))->toBeFalse()
            ->and(ClaimExperiencePayload::isFormFlowSplash($experience))->toBeTrue()
            ->and(data_get($experience, 'entry.mode'))->toBe('form_first')
            ->and(data_get($experience, 'diagnostics.form_flow_splash_policy'))->toBe('allow');
    }, 'flow-no-skip-option-test');

    $this->get('/x/claim?code='.$voucher->code)
        ->assertRedirect('/form-flow/flow-no-skip-option-test');
});

it('prepares valid compiled form claim submissions', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $response = $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $response->assertSessionHas('compiled_claim_submission', [
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $response->assertSessionHas('compiled_claim_prepared', [
        'code' => $voucher->code,
        'voucher_id' => $voucher->getKey(),
    ]);
});

it('requires a code for compiled form claim submissions', function () {
    $this->withoutMiddleware();

    $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ])->assertSessionHasErrors('code');
});

it('requires compiled form inputs to be an array', function () {
    $this->withoutMiddleware();

    $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'code' => 'TEST123',
        'inputs' => 'not-an-array',
    ])->assertSessionHasErrors('inputs');
});

it('requires inputs for compiled form claim submissions', function () {
    $this->withoutMiddleware();

    $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'code' => 'TEST123',
    ])->assertSessionHasErrors('inputs');
});

it('rejects compiled form claim submissions for missing voucher', function () {
    $this->withoutMiddleware();

    $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'code' => 'MISSING123',
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ])->assertSessionHasErrors([
        'code' => 'Invalid Pay Code.',
    ]);
});

it('keeps empty legacy claim entry rendering through get request', function () {
    $this->withoutMiddleware();

    $this->get('/x/claim')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('x-change/claim/Entry')
            ->where('initial_code', null)
            ->where('claim_experience', null)
        );
})->skip('Pending UI-agnostic ClaimStartController response boundary.');
