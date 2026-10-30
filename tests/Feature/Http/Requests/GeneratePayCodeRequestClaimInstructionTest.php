<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use LBHurtado\XChange\Http\Requests\EstimatePayCodeRequest;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;

function validClaimInstructionPayload(array $claim): array
{
    return [
        'cash' => [
            'amount' => 125,
            'currency' => 'PHP',
            'validation' => [],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => null,
            'url' => null,
            'splash' => null,
        ],
        'claim' => $claim,
    ];
}

/**
 * @param  class-string<FormRequest>  $requestClass
 */
function validateClaimInstructionRequest(string $requestClass, array $payload): Illuminate\Contracts\Validation\Validator
{
    $request = $requestClass::create(
        uri: '/x-change/pay-codes',
        method: 'POST',
        parameters: $payload,
    );
    $request->setContainer(app());
    $validator = Validator::make($payload, $request->rules());

    foreach ($request->after() as $callback) {
        $validator->after($callback);
    }

    $validator->passes();

    return $validator;
}

it('retains v2 PhilHealth execution during pricing and accepts its zero disbursement', function (): void {
    $payload = validClaimInstructionPayload([]);
    unset($payload['claim']);
    $payload['cash']['amount'] = 0;
    $payload['voucher_type'] = 'settlement';
    $payload['target_amount'] = 20000;
    $payload['execution'] = [
        'schema' => 'voucher.execution.v2', 'driver' => 'settlement_envelope',
        'settlement' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'settleable'],
        'value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999'],
        'events' => ['attest' => ['actor' => 'beneficiary'], 'settle' => ['actor' => 'payer', 'requires' => ['settlement_ready', 'confirmed_payment']]],
    ];
    $validator = validateClaimInstructionRequest(EstimatePayCodeRequest::class, $payload);
    expect($validator->errors()->all())->toBeEmpty()
        ->and(data_get($validator->validated(), 'execution'))->toBe($payload['execution']);
    unset($payload['execution']);
    expect(validateClaimInstructionRequest(EstimatePayCodeRequest::class, $payload)->errors()->has('cash.amount'))->toBeTrue();
});

it('preserves v2 execution and webhook subscriptions through issuance validation', function (): void {
    $payload = validClaimInstructionPayload([]);
    unset($payload['claim']);
    $payload['execution'] = [
        'schema' => 'voucher.execution.v2', 'driver' => 'settlement_envelope',
        'settlement' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'settleable'],
        'value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999'],
        'events' => ['attest' => ['actor' => 'beneficiary'], 'settle' => ['actor' => 'payer', 'requires' => ['settlement_ready', 'confirmed_payment']]],
    ];
    $payload['feedback']['webhooks'] = [['url' => 'https://example.test/hook', 'events' => ['settlement.payment_collected']]];
    $validator = validateClaimInstructionRequest(GeneratePayCodeRequest::class, $payload);
    expect($validator->errors()->all())->toBeEmpty()
        ->and(data_get($validator->validated(), 'execution'))->toBe($payload['execution'])
        ->and(data_get($validator->validated(), 'feedback.webhooks'))->toBe($payload['feedback']['webhooks']);
});

it('rejects unsupported v2 fields before request filtering can discard them', function (string $field, string $requestClass): void {
    $payload = validClaimInstructionPayload([]);
    unset($payload['claim']);
    $payload['execution'] = ['schema' => 'voucher.execution.v2'];
    $payload[$field] = ['value' => 'not-yet-supported'];

    $validator = validateClaimInstructionRequest($requestClass, $payload);
    expect($validator->errors()->has($field))->toBeTrue();
})->with(['pricing', 'campaign'])->with([[GeneratePayCodeRequest::class], [EstimatePayCodeRequest::class]]);

it('accepts typed Account Funding claim instructions for generation and estimation', function (string $requestClass): void {
    $validator = validateClaimInstructionRequest($requestClass, validClaimInstructionPayload([
        'outcomes' => [[
            'key' => 'account_funding',
            'pricing_profile' => 'account-funding-v1',
        ]],
        'selection' => 'server',
        'consumption' => 'one_of',
        'default_outcome' => 'account_funding',
        'onboarding' => ['mode' => 'if_required'],
        'claimant' => ['mode' => 'unbound'],
        'profile' => 'voucher.claim.v1',
    ]));

    expect($validator->errors()->all())->toBeEmpty();
})->with([
    [GeneratePayCodeRequest::class],
    [EstimatePayCodeRequest::class],
]);

it('rejects duplicate or undeclared claim outcomes at the request boundary', function (): void {
    $validator = validateClaimInstructionRequest(
        GeneratePayCodeRequest::class,
        validClaimInstructionPayload([
            'outcomes' => [
                ['key' => 'account_funding'],
                ['key' => 'account_funding'],
            ],
            'default_outcome' => 'provider_disbursement',
        ]),
    );

    expect($validator->errors()->has('claim.outcomes.1.key'))->toBeTrue()
        ->and($validator->errors()->has('claim.default_outcome'))->toBeTrue();
});

it('requires opaque claimant references only for recipient-bound claims', function (): void {
    $recipientBound = validateClaimInstructionRequest(
        GeneratePayCodeRequest::class,
        validClaimInstructionPayload([
            'outcomes' => [['key' => 'account_funding']],
            'claimant' => ['mode' => 'recipient'],
        ]),
    );
    $unbound = validateClaimInstructionRequest(
        GeneratePayCodeRequest::class,
        validClaimInstructionPayload([
            'outcomes' => [['key' => 'account_funding']],
            'claimant' => [
                'mode' => 'unbound',
                'reference' => 'must-not-be-accepted',
            ],
        ]),
    );

    expect($recipientBound->errors()->has('claim.claimant.reference'))->toBeTrue()
        ->and($unbound->errors()->has('claim.claimant.reference'))->toBeTrue();
});

it('accepts only the typed Reusable Balance policy at the request boundary', function (): void {
    $payload = validClaimInstructionPayload([
        'outcomes' => [['key' => 'provider_disbursement']],
        'default_outcome' => 'provider_disbursement',
    ]);
    $payload['stored_value'] = [
        'enabled' => true,
        'replenishable' => false,
        'maximum_balance' => 125,
        'otp_required_above' => null,
    ];

    $valid = validateClaimInstructionRequest(
        GeneratePayCodeRequest::class,
        $payload,
    );
    $payload['stored_value']['unexpected'] = 'not-allowed';
    $unknown = validateClaimInstructionRequest(
        GeneratePayCodeRequest::class,
        $payload,
    );

    expect($valid->errors()->all())->toBeEmpty()
        ->and($unknown->errors()->has('stored_value'))->toBeTrue();
});

it('preserves the Reusable Balance policy when validating an estimate', function (): void {
    $payload = validClaimInstructionPayload([
        'outcomes' => [['key' => 'provider_disbursement']],
        'default_outcome' => 'provider_disbursement',
    ]);
    $payload['stored_value'] = [
        'enabled' => true,
        'replenishable' => false,
        'maximum_balance' => 125,
        'otp_required_above' => null,
    ];

    $validator = validateClaimInstructionRequest(EstimatePayCodeRequest::class, $payload);

    expect($validator->errors()->all())->toBeEmpty()
        ->and(data_get($validator->validated(), 'stored_value.enabled'))->toBeTrue();
});

it('preserves the destination for estimation and issuance and rejects unknown flow controls', function (string $requestClass): void {
    $payload = validClaimInstructionPayload([
        'outcomes' => [['key' => 'provider_disbursement']], 'default_outcome' => 'provider_disbursement',
    ]);
    $payload['value_flow'] = ['destination' => 'instapay.gcash.mobile:09179999999'];
    $validator = validateClaimInstructionRequest($requestClass, $payload);
    expect($validator->errors()->all())->toBeEmpty()
        ->and(data_get($validator->validated(), 'value_flow.destination'))->toBe($payload['value_flow']['destination']);
    $payload['value_flow']['collector_override'] = true;
    expect(validateClaimInstructionRequest($requestClass, $payload)->errors()->has('value_flow'))->toBeTrue();
})->with([[GeneratePayCodeRequest::class], [EstimatePayCodeRequest::class]]);
