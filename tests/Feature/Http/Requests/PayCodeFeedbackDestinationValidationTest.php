<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use LBHurtado\XChange\Http\Requests\EstimatePayCodeRequest;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;

it('accepts normalized feedback destinations for issuance and estimation', function (string $requestClass): void {
    $rules = Arr::only((new $requestClass)->rules(), [
        'feedback',
        'feedback.email',
        'feedback.mobile',
        'feedback.webhook',
    ]);

    $validator = Validator::make([
        'feedback' => [
            'email' => 'issuer@example.com',
            'mobile' => '+639173011987',
            'webhook' => 'https://example.test/redemption-feedback',
        ],
    ], $rules);

    expect($validator->fails())->toBeFalse();
})->with([
    'generate' => [GeneratePayCodeRequest::class],
    'estimate' => [EstimatePayCodeRequest::class],
]);

it('rejects malformed or unsafe feedback destinations', function (string $requestClass, string $field, string $value): void {
    $rules = Arr::only((new $requestClass)->rules(), [
        'feedback',
        'feedback.email',
        'feedback.mobile',
        'feedback.webhook',
    ]);

    $validator = Validator::make([
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
            $field => $value,
        ],
    ], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has("feedback.{$field}"))->toBeTrue();
})->with([
    'generate malformed email' => [GeneratePayCodeRequest::class, 'email', 'issuer-at-example'],
    'generate malformed mobile' => [GeneratePayCodeRequest::class, 'mobile', '12345'],
    'generate unsafe webhook' => [GeneratePayCodeRequest::class, 'webhook', 'ftp://example.test/file'],
    'estimate malformed email' => [EstimatePayCodeRequest::class, 'email', 'issuer-at-example'],
    'estimate malformed mobile' => [EstimatePayCodeRequest::class, 'mobile', '12345'],
    'estimate unsafe webhook' => [EstimatePayCodeRequest::class, 'webhook', 'javascript:alert(1)'],
]);
