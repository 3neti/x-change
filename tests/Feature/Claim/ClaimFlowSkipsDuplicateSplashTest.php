<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Http\Controllers\FormFlowController;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;

beforeEach(function () {
    $viewsPath = __DIR__.'/../../Fixtures/views';

    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0777, true);
    }

    file_put_contents($viewsPath.'/app.blade.php', <<<'BLADE'
<div id="app" data-page="{{ json_encode($page) }}"></div>
BLADE);

    app('view')->addLocation($viewsPath);

    config()->set('inertia.testing.ensure_pages_exist', false);

    Route::get('/form-flow/{flow_id}', [FormFlowController::class, 'show'])
        ->name('form-flow.show');
});

it('marks form-flow splash as consumable when rider splash was already consumed', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'splash' => '<h1>Welcome</h1>',
                'message' => 'SUCCESS DEMO: Thank you for claiming.',
                'url' => 'https://example.com/after-claim',
            ],
        ],
    ));

    $driver = Mockery::mock(DriverService::class);

    $driver->shouldReceive('transform')
        ->once()
        ->with(Mockery::on(fn ($actual) => $actual instanceof Voucher && $actual->is($voucher)))
        ->andReturn(FormFlowInstructionsData::from([
            'reference_id' => 'claim-'.$voucher->code.'-test',
            'steps' => [
                [
                    'handler' => 'splash',
                    'config' => [
                        'title' => 'Welcome',
                        'content' => '<h1>Welcome</h1>',
                        'timeout' => 0,
                        'step_name' => 'intro_splash',
                    ],
                ],
                [
                    'handler' => 'form',
                    'config' => [
                        'title' => 'Wallet Information',
                        'fields' => [
                            [
                                'name' => 'mobile',
                                'type' => 'text',
                                'label' => 'Mobile Number',
                                'required' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'callbacks' => [
                'on_complete' => 'https://example.com/callback',
            ],
            'metadata' => [
                'voucher_code' => $voucher->code,
            ],
        ]));

    app()->instance(DriverService::class, $driver);

    $startResponse = $this->get('/x/claim?code='.$voucher->code);

    $startResponse->assertRedirect();

    $flowUrl = $startResponse->headers->get('Location');
    $flowPath = parse_url($flowUrl, PHP_URL_PATH);

    $this->get($flowPath)
        ->assertOk();

    $flowId = str($flowPath)->afterLast('/')->toString();

    $state = session("form_flow.{$flowId}");

    expect($state)->toBeArray()
        ->and(data_get($state, 'current_step'))->toBe(0);

    $experience = ClaimExperiencePayload::fromState($state);

    expect($experience)->toBeArray()
        ->and(ClaimExperiencePayload::shouldSkipConsumedSplash($experience))->toBeTrue()
        ->and(ClaimExperiencePayload::isXRiderSplash($experience))->toBeTrue()
        ->and(data_get($experience, 'diagnostics.form_flow_splash_policy'))->toBe('skip_consumed');
});

it('does not skip the form-flow splash when rider splash was not consumed', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'message' => 'SUCCESS DEMO: Thank you for claiming.',
                'url' => 'https://example.com/after-claim',
            ],
        ],
    ));

    $driver = Mockery::mock(DriverService::class);

    $driver->shouldReceive('transform')
        ->once()
        ->with(Mockery::on(fn ($actual) => $actual instanceof Voucher && $actual->is($voucher)))
        ->andReturn(FormFlowInstructionsData::from([
            'reference_id' => 'claim-'.$voucher->code.'-test',
            'steps' => [
                [
                    'handler' => 'splash',
                    'config' => [
                        'title' => 'Welcome',
                        'content' => '<h1>Welcome</h1>',
                        'timeout' => 0,
                        'step_name' => 'intro_splash',
                    ],
                ],
                [
                    'handler' => 'form',
                    'config' => [
                        'title' => 'Wallet Information',
                        'fields' => [
                            [
                                'name' => 'mobile',
                                'type' => 'text',
                                'label' => 'Mobile Number',
                                'required' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'callbacks' => [
                'on_complete' => 'https://example.com/callback',
            ],
            'metadata' => [
                'voucher_code' => $voucher->code,
            ],
        ]));

    app()->instance(DriverService::class, $driver);

    $startResponse = $this->get('/x/claim?code='.$voucher->code);

    $startResponse->assertRedirect();

    $flowUrl = $startResponse->headers->get('Location');
    $flowPath = parse_url($flowUrl, PHP_URL_PATH);

    $flowId = str($flowPath)->afterLast('/')->toString();

    $state = session("form_flow.{$flowId}");

    expect($state)->toBeArray()
        ->and(data_get($state, 'current_step'))->toBe(0)
        ->and(data_get($state, 'collected_data.0._skipped'))->toBeNull();

    $experience = ClaimExperiencePayload::fromState($state);

    expect($experience)->toBeArray()
        ->and(ClaimExperiencePayload::shouldSkipConsumedSplash($experience))->toBeFalse()
        ->and(ClaimExperiencePayload::isFormFlowSplash($experience))->toBeTrue()
        ->and(data_get($experience, 'diagnostics.form_flow_splash_policy'))->toBe('allow');
});


