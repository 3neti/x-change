<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use LBHurtado\XChange\Http\Requests\EstimatePayCodeRequest;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;

function cockpitPayeePayload(string $kind, array $overrides = []): array
{
    return array_replace_recursive([
        'cash' => [
            'amount' => 25,
            'currency' => 'PHP',
            'validation' => [],
        ],
        'inputs' => [
            'fields' => [],
            'requirements' => [],
        ],
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
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'source' => 'cockpit.quick-generate',
                    'payee' => [
                        'kind' => $kind,
                        'explicit_secret' => false,
                    ],
                ],
            ],
        ],
    ], $overrides);
}

/**
 * @param  class-string<FormRequest>  $requestClass
 */
function validateCockpitPayeePayload(string $requestClass, array $payload): Illuminate\Contracts\Validation\Validator
{
    $request = $requestClass::create('/x/cockpit/quick-generate', 'POST', $payload);
    $request->setContainer(app());
    $validator = Validator::make($payload, $request->rules());

    foreach ($request->after() as $callback) {
        $validator->after($callback);
    }

    $validator->passes();

    return $validator;
}

it('accepts the complete mobile and OTP invariant', function (string $requestClass): void {
    $validator = validateCockpitPayeePayload($requestClass, cockpitPayeePayload('mobile', [
        'cash' => [
            'validation' => [
                'mobile' => '+639173011987',
                'mobile_verification' => [],
            ],
        ],
        'inputs' => [
            'fields' => ['mobile', 'otp'],
            'requirements' => ['otp'],
        ],
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    expect($validator->errors()->all())->toBeEmpty();
})->with([
    'generate' => [GeneratePayCodeRequest::class],
    'estimate' => [EstimatePayCodeRequest::class],
]);

it('rejects attempts to remove OTP from a mobile-bound Pay Code', function (): void {
    $validator = validateCockpitPayeePayload(
        GeneratePayCodeRequest::class,
        cockpitPayeePayload('mobile', [
            'cash' => [
                'validation' => ['mobile' => '+639173011987'],
            ],
            'inputs' => ['fields' => ['mobile']],
        ]),
    );

    expect($validator->errors()->has('inputs.fields'))->toBeTrue()
        ->and($validator->errors()->has('inputs.requirements'))->toBeTrue()
        ->and($validator->errors()->has('validation.otp'))->toBeTrue();
});

it('rejects email payees until email OTP is implemented', function (): void {
    $validator = validateCockpitPayeePayload(
        GeneratePayCodeRequest::class,
        cockpitPayeePayload('email', [
            'inputs' => ['fields' => ['email']],
        ]),
    );

    expect($validator->errors()->first('metadata.custom.cockpit.payee.kind'))
        ->toContain('email OTP capability');
});

it('requires the actual secret for issuance but permits redaction for estimates', function (): void {
    $payload = cockpitPayeePayload('secret', [
        'cash' => [
            'validation' => ['secret' => '[redacted secret]'],
        ],
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'payee' => ['explicit_secret' => true],
                ],
            ],
        ],
    ]);

    $generate = validateCockpitPayeePayload(GeneratePayCodeRequest::class, $payload);
    $estimate = validateCockpitPayeePayload(EstimatePayCodeRequest::class, $payload);

    expect($generate->errors()->first('cash.validation.secret'))
        ->toContain('actual release secret')
        ->and($estimate->errors()->all())->toBeEmpty();
});
