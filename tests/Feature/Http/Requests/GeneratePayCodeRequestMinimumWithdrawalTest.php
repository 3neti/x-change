<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use LBHurtado\XChange\Http\Requests\EstimatePayCodeRequest;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;

function validMinimumWithdrawalPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'provider' => 'netbank',
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'slice_mode' => 'fixed',
            'slices' => 4,
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
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
    ], $overrides);
}

function validateMinimumWithdrawalRequest(string $requestClass, array $payload): Illuminate\Contracts\Validation\Validator
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

it('adds policy errors to generate pay code requests', function (): void {
    config()->set('x-change.minimum_withdrawal.default', 25.00);

    $validator = validateMinimumWithdrawalRequest(GeneratePayCodeRequest::class, validMinimumWithdrawalPayload([
        'cash' => [
            'slices' => 5,
        ],
    ]));

    expect($validator->errors()->first('cash.slices'))
        ->toContain('below the PHP 25.00 minimum withdrawal');
});

it('adds policy errors to estimate pay code requests', function (): void {
    config()->set('x-change.minimum_withdrawal.default', 25.00);
    config()->set('x-change.minimum_withdrawal.providers.paynamics.PHP', 50.00);

    $validator = validateMinimumWithdrawalRequest(EstimatePayCodeRequest::class, validMinimumWithdrawalPayload([
        'provider' => 'paynamics',
        'cash' => [
            'slice_mode' => 'open',
            'slices' => null,
            'max_slices' => 2,
            'min_withdrawal' => 25,
        ],
    ]));

    expect($validator->errors()->first('cash.min_withdrawal'))
        ->toBe('Minimum withdrawal must be at least PHP 50.00.');
});

it('reports a clear collection amount error for a zero-value payable Pay Code', function (): void {
    $validator = validateMinimumWithdrawalRequest(GeneratePayCodeRequest::class, validMinimumWithdrawalPayload([
        'voucher_type' => 'payable',
        'cash' => [
            'amount' => 0,
        ],
    ]));

    expect($validator->errors()->first('cash.amount'))
        ->toBe('The amount to collect must be greater than zero for a payable Pay Code.');
});

it('keeps the redeemable minimum amount validation unchanged', function (): void {
    $validator = validateMinimumWithdrawalRequest(GeneratePayCodeRequest::class, validMinimumWithdrawalPayload([
        'voucher_type' => 'redeemable',
        'cash' => [
            'amount' => 0,
        ],
    ]));

    expect($validator->errors()->first('cash.amount'))
        ->toContain('must be at least 0.01');
});

it('validates the settlement target independently from its redeemable cash amount', function (): void {
    $validator = validateMinimumWithdrawalRequest(GeneratePayCodeRequest::class, validMinimumWithdrawalPayload([
        'voucher_type' => 'settlement',
        'target_amount' => 0,
        'cash' => [
            'amount' => 100,
        ],
    ]));

    expect($validator->errors()->first('target_amount'))
        ->toBe('The target value must be greater than zero for a settlement Pay Code.')
        ->and($validator->errors()->has('cash.amount'))->toBeFalse();
});
