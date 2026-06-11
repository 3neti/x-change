<?php

declare(strict_types=1);

use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Support\Claim\ClaimEvidenceSynchronizer;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;
use LBHurtado\XChange\Support\Claim\CompiledClaimResultSession;
use LBHurtado\XChange\Support\Claim\CompiledClaimSessionKeys;

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

it('passes skip consumed splash policy to form flow when rider splash is already consumed', function () {
    $this->withoutMiddleware();

    $voucher = claimVoucherWithRiderSplash();

    mockDriverForClaimVoucher($this, $voucher, splashStep());

    assertClaimExperienceStartFlow($this, function (array $experience, array $payload) {
        expect(data_get($experience, 'consumed.splash'))->toBeTrue()
            ->and(data_get($experience, 'options.skip_consumed_splash'))->toBeTrue()
            ->and(data_get($experience, 'diagnostics.form_flow_splash_policy'))->toBe('skip_consumed')
            ->and(data_get($payload, 'steps.0.handler'))->toBe('splash');
    }, 'flow-skip-consumed-splash-test');

    $this->get('/x/claim?code='.$voucher->code)
        ->assertRedirect('/form-flow/flow-skip-consumed-splash-test');
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

it('executes valid compiled form claim submissions through the redemption bridge', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $evidence = Mockery::mock(ClaimEvidenceSynchronizer::class);
    $evidence->shouldReceive('sync')->once();

    $submitPayCodeClaim = Mockery::mock(SubmitPayCodeClaim::class);
    $submitPayCodeClaim
        ->shouldReceive('handle')
        ->once()
        ->andReturn(new SubmitPayCodeClaimResultData(
            voucher_code: $voucher->code,
            claim_type: 'withdraw',
            claimed: true,
            status: 'success',
            requested_amount: null,
            disbursed_amount: null,
            currency: null,
            remaining_balance: null,
            fully_claimed: true,
            disbursement: null,
            messages: [],
        ));

    $this->app->instance(ClaimEvidenceSynchronizer::class, $evidence);
    $this->app->instance(SubmitPayCodeClaim::class, $submitPayCodeClaim);

    $response = $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    expect(session()->has('compiled_claim_prepared'))->toBeFalse();

    expect(session()->has('compiled_claim_submission'))->toBeFalse();

    $response->assertRedirect(route('x-change.claim.success', [
        'code' => $voucher->code,
    ]));

    expect(session()->has('compiled_claim_completion_payload'))->toBeFalse();
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

it('does not enter the redemption bridge when voucher is missing', function () {
    $this->withoutMiddleware();

    $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'code' => 'MISSING-CODE',
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ])->assertSessionHasErrors('code');

    expect(session()->has(CompiledClaimSessionKeys::PREPARED))->toBeFalse()
        ->and(session()->has('compiled_claim_completion_submitted'))->toBeFalse();
});

it('does not enter the redemption bridge when voucher is already redeemed', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();
    $voucher->forceFill([
        'redeemed_at' => now(),
    ])->save();

    $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ])->assertSessionHasErrors('code');

    expect(session()->has(CompiledClaimSessionKeys::PREPARED))->toBeFalse()
        ->and(session()->has('compiled_claim_completion_submitted'))->toBeFalse();
});

it('does not enter the redemption bridge when voucher is expired', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();
    $voucher->forceFill([
        'expires_at' => now()->subMinute(),
    ])->save();

    $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ])->assertSessionHasErrors('code');

    expect(session()->has(CompiledClaimSessionKeys::PREPARED))->toBeFalse()
        ->and(session()->has('compiled_claim_completion_submitted'))->toBeFalse();
});

it('passes compiled form payload to the canonical redemption action', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $evidence = Mockery::mock(ClaimEvidenceSynchronizer::class);
    $evidence
        ->shouldReceive('sync')
        ->once()
        ->with(Mockery::on(fn (array $payload): bool => ($payload['source'] ?? null) === 'compiled_form'
            && ($payload['code'] ?? null) === $voucher->code
            && ($payload['voucher_id'] ?? null) === $voucher->getKey()
            && ($payload['inputs']['first_name'] ?? null) === 'Lester'
        ));

    $submitPayCodeClaim = Mockery::mock(SubmitPayCodeClaim::class);
    $submitPayCodeClaim
        ->shouldReceive('handle')
        ->once()
        ->withArgs(fn ($receivedVoucher, array $payload): bool => $receivedVoucher->is($voucher)
            && ($payload['source'] ?? null) === 'compiled_form'
            && ($payload['code'] ?? null) === $voucher->code
            && ($payload['voucher_id'] ?? null) === $voucher->getKey()
            && ($payload['inputs']['first_name'] ?? null) === 'Lester'
        )
        ->andReturn(new SubmitPayCodeClaimResultData(
            voucher_code: $voucher->code,
            claim_type: 'withdraw',
            claimed: true,
            status: 'success',
            requested_amount: null,
            disbursed_amount: null,
            currency: null,
            remaining_balance: null,
            fully_claimed: true,
            disbursement: null,
            messages: [],
        ));

    $this->app->instance(ClaimEvidenceSynchronizer::class, $evidence);
    $this->app->instance(SubmitPayCodeClaim::class, $submitPayCodeClaim);

    $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ])->assertRedirect(route('x-change.claim.success', [
        'code' => $voucher->code,
    ]));

    expect(session()->has(CompiledClaimSessionKeys::SUBMISSION))->toBeFalse()
        ->and(session()->has(CompiledClaimSessionKeys::PREPARED))->toBeFalse();
});

it('routes pending compiled form claim results to approval placeholder', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $evidence = Mockery::mock(ClaimEvidenceSynchronizer::class);
    $evidence->shouldReceive('sync')->once();

    $submitPayCodeClaim = Mockery::mock(SubmitPayCodeClaim::class);
    $submitPayCodeClaim
        ->shouldReceive('handle')
        ->once()
        ->andReturn(new SubmitPayCodeClaimResultData(
            voucher_code: $voucher->code,
            claim_type: 'withdraw',
            claimed: false,
            status: 'pending',
            requested_amount: null,
            disbursed_amount: null,
            currency: null,
            remaining_balance: null,
            fully_claimed: false,
            disbursement: null,
            messages: [],
        ));

    $this->app->instance(ClaimEvidenceSynchronizer::class, $evidence);
    $this->app->instance(SubmitPayCodeClaim::class, $submitPayCodeClaim);

    $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ])->assertRedirect(url("/x/claim/{$voucher->code}/approval"));

    expect(session()->has(CompiledClaimSessionKeys::SUBMISSION))->toBeFalse()
        ->and(session()->has(CompiledClaimSessionKeys::PREPARED))->toBeFalse();
});

it('returns to claim form when compiled form redemption fails', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $evidence = Mockery::mock(ClaimEvidenceSynchronizer::class);
    $evidence->shouldReceive('sync')->once();

    $submitPayCodeClaim = Mockery::mock(SubmitPayCodeClaim::class);
    $submitPayCodeClaim
        ->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Compiled redemption failed.'));

    $this->app->instance(ClaimEvidenceSynchronizer::class, $evidence);
    $this->app->instance(SubmitPayCodeClaim::class, $submitPayCodeClaim);

    $response = $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $response->assertSessionHasErrors(['code']);

    expect(session('errors')?->getBag('default')->first('code'))
        ->toBe('Compiled redemption failed.');
});

it('hydrates success page with compiled claim result after compiled form submission', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $evidence = Mockery::mock(ClaimEvidenceSynchronizer::class);
    $evidence->shouldReceive('sync')->once();

    $submitPayCodeClaim = Mockery::mock(SubmitPayCodeClaim::class);
    $submitPayCodeClaim
        ->shouldReceive('handle')
        ->once()
        ->andReturn(new SubmitPayCodeClaimResultData(
            voucher_code: $voucher->code,
            claim_type: 'withdraw',
            claimed: true,
            status: 'success',
            requested_amount: null,
            disbursed_amount: null,
            currency: null,
            remaining_balance: null,
            fully_claimed: true,
            disbursement: null,
            messages: ['Claim successful.'],
        ));

    $this->app->instance(ClaimEvidenceSynchronizer::class, $evidence);
    $this->app->instance(SubmitPayCodeClaim::class, $submitPayCodeClaim);

    $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ])->assertRedirect(route('x-change.claim.success', [
        'code' => $voucher->code,
    ]));

    expect(session()->has(CompiledClaimResultSession::KEY))->toBeTrue();

    $this->getJson(route('x-change.claim.success', [
        'code' => $voucher->code,
    ]))
        ->assertOk()
        ->assertJsonPath('compiled_claim_result.voucher_code', $voucher->code)
        ->assertJsonPath('compiled_claim_result.status', 'success')
        ->assertJsonPath('compiled_claim_result.messages.0', 'Claim successful.');

    expect(session()->has(CompiledClaimResultSession::KEY))->toBeFalse();
});

it('hydrates approval page with pending compiled claim result after compiled form submission', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $evidence = Mockery::mock(ClaimEvidenceSynchronizer::class);
    $evidence->shouldReceive('sync')->once();

    $submitPayCodeClaim = Mockery::mock(SubmitPayCodeClaim::class);
    $submitPayCodeClaim
        ->shouldReceive('handle')
        ->once()
        ->andReturn(new SubmitPayCodeClaimResultData(
            voucher_code: $voucher->code,
            claim_type: 'withdraw',
            claimed: false,
            status: 'pending',
            requested_amount: null,
            disbursed_amount: null,
            currency: null,
            remaining_balance: null,
            fully_claimed: false,
            disbursement: null,
            messages: ['Approval required.'],
        ));

    $this->app->instance(ClaimEvidenceSynchronizer::class, $evidence);
    $this->app->instance(SubmitPayCodeClaim::class, $submitPayCodeClaim);

    $this->post('/x/claim', [
        'mode' => 'compiled_form',
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ])->assertRedirect(route('x-change.claim.approval', [
        'code' => $voucher->code,
    ]));

    expect(session()->has(CompiledClaimResultSession::KEY))->toBeTrue();

    $this->getJson(route('x-change.claim.approval', [
        'code' => $voucher->code,
    ]))
        ->assertOk()
        ->assertJsonPath('compiled_claim_result.voucher_code', $voucher->code)
        ->assertJsonPath('compiled_claim_result.status', 'pending')
        ->assertJsonPath('compiled_claim_result.messages.0', 'Approval required.');

    expect(session()->has(CompiledClaimResultSession::KEY))->toBeFalse();
});

