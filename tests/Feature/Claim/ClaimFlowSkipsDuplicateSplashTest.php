<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Http\Controllers\FormFlowController;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\Voucher\Models\Voucher;

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

it('skips the form-flow splash when claim experience marks rider splash consumed', function () {
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
        ->assertRedirect();

    $flowId = str($flowPath)->afterLast('/')->toString();

    $state = session("form_flow.{$flowId}");

    expect($state)->toBeArray()
        ->and(data_get($state, 'current_step'))->toBe(1)
        ->and(data_get($state, 'collected_data.0._skipped'))->toBeTrue()
        ->and(data_get($state, 'collected_data.0._skip_reason'))->toBe('duplicate_splash_candidate')
        ->and(data_get($state, 'instructions.metadata.claim_experience.options.skip_consumed_splash'))->toBeTrue()
        ->and(data_get($state, 'instructions.metadata.claim_experience.consumed.splash'))->toBeTrue()
        ->and(data_get($state, 'instructions.metadata.claim_experience.diagnostics.splash_owner'))->toBe('x-rider')
        ->and(data_get($state, 'instructions.metadata.claim_experience.diagnostics.form_flow_splash_policy'))->toBe('skip_consumed');
});
