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
