<?php

declare(strict_types=1);

use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\Voucher\Models\Voucher;

it('attaches claim experience shadow payload before starting form flow', function () {
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
            'steps' => [],
            'callbacks' => [
                'on_complete' => '/x/claim/'.$voucher->code.'/complete',
            ],
            'metadata' => [
                'voucher_code' => $voucher->code,
            ],
        ]));

    $this->app->instance(DriverService::class, $driver);

    $formFlow = Mockery::mock(FormFlowService::class);

    $formFlow->shouldReceive('startFlow')
        ->once()
        ->with(Mockery::on(function (FormFlowInstructionsData $instructions) {
            $payload = $instructions->toArray();

            $experience = data_get($payload, 'metadata.claim_experience');

            expect($experience)->toBeArray()
                ->and(data_get($experience, 'version'))->toBe(1)
                ->and(data_get($experience, 'entry.mode'))->toBe('rider_first')
                ->and(data_get($experience, 'consumed.splash'))->toBeTrue()
                ->and(data_get($experience, 'diagnostics.duplicate_splash_prevented'))->toBeTrue()
                ->and(collect(data_get($experience, 'phases'))->pluck('key')->all())
                ->toContain('rider_intro', 'form_flow', 'success_rider', 'redirect');

            return true;
        }))
        ->andReturn([
            'flow_id' => 'flow-shadow-test',
        ]);

    $this->app->instance(FormFlowService::class, $formFlow);

    $this->get('/x/claim?code='.$voucher->code)
        ->assertRedirect('/form-flow/flow-shadow-test');
});

it('persists claim experience inside started form flow state', function () {
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
            'steps' => [],
            'callbacks' => [
                'on_complete' => '/x/claim/'.$voucher->code.'/complete',
            ],
            'metadata' => [
                'voucher_code' => $voucher->code,
            ],
        ]));

    $this->app->instance(DriverService::class, $driver);

    $response = $this->get('/x/claim?code='.$voucher->code);

    $response->assertRedirect();

    $location = $response->headers->get('Location');

    expect($location)->toStartWith('http://localhost/form-flow/');

    $flowId = str($location)->afterLast('/')->toString();

    $state = session("form_flow.{$flowId}");

    expect($state)->toBeArray();

    $experience = data_get($state, 'instructions.metadata.claim_experience')
        ?? data_get($state, 'metadata.claim_experience')
        ?? data_get($state, 'claim_experience');

    expect($experience)->toBeArray()
        ->and(data_get($experience, 'version'))->toBe(1)
        ->and(data_get($experience, 'entry.mode'))->toBe('rider_first')
        ->and(data_get($experience, 'consumed.splash'))->toBeTrue()
        ->and(data_get($experience, 'diagnostics.duplicate_splash_prevented'))->toBeTrue()
        ->and(collect(data_get($experience, 'phases'))->pluck('key')->all())
        ->toContain('rider_intro', 'form_flow', 'success_rider', 'redirect');
});
