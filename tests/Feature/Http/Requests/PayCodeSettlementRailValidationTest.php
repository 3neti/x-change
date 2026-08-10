<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use LBHurtado\XChange\Http\Requests\EstimatePayCodeRequest;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;

/**
 * @param  class-string<FormRequest>  $requestClass
 */
function validateSettlementRail(string $requestClass, mixed $rail): Illuminate\Contracts\Validation\Validator
{
    $request = $requestClass::create('/x-change/pay-codes', 'POST', [
        'cash' => ['settlement_rail' => $rail],
    ]);
    $request->setContainer(app());

    $method = new ReflectionMethod($request, 'prepareForValidation');
    $method->invoke($request);

    return Validator::make(
        Arr::only($request->all(), ['cash']),
        Arr::only($request->rules(), ['cash', 'cash.settlement_rail']),
    );
}

it('accepts canonical and automatic settlement rails at both HTTP boundaries', function (string $requestClass, mixed $rail): void {
    expect(validateSettlementRail($requestClass, $rail)->fails())->toBeFalse();
})->with([
    'generate automatic null' => [GeneratePayCodeRequest::class, null],
    'generate automatic omitted-equivalent' => [GeneratePayCodeRequest::class, ''],
    'generate instapay' => [GeneratePayCodeRequest::class, 'INSTAPAY'],
    'generate pesonet' => [GeneratePayCodeRequest::class, 'PESONET'],
    'estimate automatic null' => [EstimatePayCodeRequest::class, null],
    'estimate instapay' => [EstimatePayCodeRequest::class, 'INSTAPAY'],
    'estimate pesonet' => [EstimatePayCodeRequest::class, 'PESONET'],
]);

it('normalizes settlement rail casing before enum validation', function (string $requestClass): void {
    expect(validateSettlementRail($requestClass, ' pesonet ')->fails())->toBeFalse();
})->with([
    'generate' => [GeneratePayCodeRequest::class],
    'estimate' => [EstimatePayCodeRequest::class],
]);

it('rejects unknown settlement rails at both HTTP boundaries', function (string $requestClass): void {
    $validator = validateSettlementRail($requestClass, 'NETBANK');

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('cash.settlement_rail'))->toBeTrue();
})->with([
    'generate' => [GeneratePayCodeRequest::class],
    'estimate' => [EstimatePayCodeRequest::class],
]);

it('persists an explicit pesonet instruction on the voucher', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 750,
        settlementRail: 'PESONET',
    ));

    expect(data_get($voucher->instructions, 'cash.settlement_rail')->value)->toBe('PESONET');
});
