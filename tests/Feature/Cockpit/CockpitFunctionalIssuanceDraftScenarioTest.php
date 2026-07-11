<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use LBHurtado\XChange\Contracts\CockpitCampaignIssuanceDraftAdapterContract;
use LBHurtado\XChange\Contracts\CockpitIssuanceDraftCompilerContract;
use LBHurtado\XChange\Contracts\CockpitIssuanceDraftValidatorContract;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;

it('characterizes campaign draft to GeneratePayCodeRequest compatible payload without issuing', function () {
    $draft = app(CockpitCampaignIssuanceDraftAdapterContract::class)->fromCampaignContext([
        'planning_key' => 'plan-wave-9i',
        'execution_id' => 'exec-wave-9i',
        'campaign_id' => 'campaign-wave-9i',
        'audience_id' => 'audience-wave-9i',
        'recipient_id' => 'recipient-wave-9i',
        'template_key' => 'ofw-remittance',
        'amount' => '250.00',
        'currency' => 'PHP',
        'recipient' => [
            'mobile' => '09173011987',
            'email' => 'recipient@example.test',
        ],
        'purpose' => 'Wave 9 functional characterization',
        'idempotency_key' => 'idem-wave-9i',
        'correlation_id' => 'corr-wave-9i',
    ]);

    $validation = app(CockpitIssuanceDraftValidatorContract::class)->validate($draft);
    $payload = app(CockpitIssuanceDraftCompilerContract::class)->compile($draft);
    $request = new GeneratePayCodeRequest;
    $validator = Validator::make($payload, $request->rules());

    expect($validation->valid)->toBeTrue()
        ->and($validator->passes())->toBeTrue()
        ->and(data_get($payload, 'cash.amount'))->toBe('250.00')
        ->and(data_get($payload, 'metadata.campaign.campaign_id'))->toBe('campaign-wave-9i')
        ->and(data_get($payload, 'metadata.custom.cockpit.template_key'))->toBe('ofw-remittance')
        ->and(data_get($payload, '_meta.idempotency_key'))->toBe('idem-wave-9i')
        ->and($payload)->not->toHaveKey('provider_payload')
        ->and($payload)->not->toHaveKey('wallet')
        ->and($payload)->not->toHaveKey('raw_payload');
});
