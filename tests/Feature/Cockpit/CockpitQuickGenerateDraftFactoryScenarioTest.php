<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use LBHurtado\XChange\Contracts\CockpitIssuanceDraftCompilerContract;
use LBHurtado\XChange\Contracts\CockpitIssuanceDraftValidatorContract;
use LBHurtado\XChange\Contracts\CockpitQuickGenerateDraftFactoryContract;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;

it('characterizes quick generate payload to draft to GeneratePayCodeRequest compatible payload without issuing', function () {
    $draft = app(CockpitQuickGenerateDraftFactoryContract::class)->fromPayload([
        'cash' => [
            'amount' => '25.00',
            'currency' => 'PHP',
            'validation' => [],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'count' => 1,
        'feedback' => [
            'mobile' => '09173011987',
        ],
        'rider' => [
            'message' => 'Quick Generate Wave 10A characterization',
            'message_format' => 'markdown',
            'stamp' => [
                'source' => 'message',
                'fit' => 'contain',
                'position' => 'top',
                'scrim' => 18,
                'theme' => 'dark',
                'version' => 1,
            ],
        ],
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'template_key' => 'money-changer',
                    'source' => 'cockpit.quick-generate',
                ],
            ],
        ],
    ], idempotencyKey: 'idem-wave-10a', correlationId: 'corr-wave-10a');

    $validation = app(CockpitIssuanceDraftValidatorContract::class)->validate($draft);
    $payload = app(CockpitIssuanceDraftCompilerContract::class)->compile($draft);
    $request = new GeneratePayCodeRequest;
    $validator = Validator::make($payload, $request->rules());

    expect($validation->valid)->toBeTrue()
        ->and($validator->passes())->toBeTrue()
        ->and(data_get($payload, 'cash.amount'))->toBe('25.00')
        ->and(data_get($payload, 'cash.validation'))->toBe([])
        ->and(data_get($payload, 'inputs.fields'))->toBe([])
        ->and(data_get($payload, 'count'))->toBe(1)
        ->and(data_get($payload, 'feedback.mobile'))->toBe('09173011987')
        ->and(data_get($payload, 'rider.message'))->toBe('Quick Generate Wave 10A characterization')
        ->and(data_get($payload, 'rider.message_format'))->toBe('markdown')
        ->and(data_get($payload, 'rider.stamp.source'))->toBe('message')
        ->and(data_get($payload, 'rider.stamp.fit'))->toBe('contain')
        ->and(data_get($payload, 'metadata.custom.cockpit.template_key'))->toBe('money-changer')
        ->and(data_get($payload, '_meta.idempotency_key'))->toBe('idem-wave-10a')
        ->and(data_get($payload, '_meta.correlation_id'))->toBe('corr-wave-10a')
        ->and($payload)->not->toHaveKey('provider_payload')
        ->and($payload)->not->toHaveKey('wallet')
        ->and($payload)->not->toHaveKey('raw_payload');
});
