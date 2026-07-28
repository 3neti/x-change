<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use LBHurtado\XChange\Http\Requests\EstimatePayCodeRequest;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;

function validRiderStampRequestPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => '**Thank you**',
            'message_format' => 'markdown',
            'url' => null,
            'redirect_timeout' => null,
            'splash' => '# Welcome',
            'splash_format' => 'markdown',
            'splash_timeout' => null,
            'og_source' => 'message',
            'stamp' => [
                'source' => 'splash',
                'title' => 'A gift for you',
                'description' => 'Open this Pay Code to claim.',
                'fit' => 'contain',
                'position' => 'top',
                'scrim' => 32,
                'theme' => 'dark',
                'version' => 1,
            ],
        ],
    ], $overrides);
}

it('accepts typed Rider content and Stamp fields', function (string $requestClass): void {
    $request = new $requestClass;
    $validator = Validator::make(validRiderStampRequestPayload(), $request->rules());

    expect($validator->passes())->toBeTrue()
        ->and($validator->errors()->isEmpty())->toBeTrue();
})->with([
    'generate' => [GeneratePayCodeRequest::class],
    'estimate' => [EstimatePayCodeRequest::class],
]);

it('rejects authoritative or malformed Rider Stamp values', function (
    string $field,
    mixed $value,
): void {
    $request = new GeneratePayCodeRequest;
    $validator = Validator::make(
        validRiderStampRequestPayload([
            'rider' => [
                'stamp' => [
                    $field => $value,
                ],
            ],
        ]),
        $request->rules(),
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has("rider.stamp.{$field}"))->toBeTrue();
})->with([
    'signature-like source' => ['source', 'signature'],
    'unknown artwork fit' => ['fit', 'stretch'],
    'invalid scrim' => ['scrim', 101],
    'unsupported contract version' => ['version', 2],
]);
