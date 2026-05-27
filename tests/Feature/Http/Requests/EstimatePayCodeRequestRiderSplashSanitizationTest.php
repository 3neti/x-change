<?php

use LBHurtado\XChange\Http\Requests\EstimatePayCodeRequest;

function invokeEstimatePayCodePrepareForValidation(EstimatePayCodeRequest $request): void
{
    $method = new ReflectionMethod($request, 'prepareForValidation');
    $method->invoke($request);
}

it('sanitizes rider splash html before estimate validation', function (): void {
    $request = EstimatePayCodeRequest::create(
        uri: '/x-change/pay-codes/estimate',
        method: 'POST',
        parameters: [
            'amount' => 100,
            'currency' => 'PHP',
            'recipient' => [
                'mobile' => '09171234567',
            ],
            'rider' => [
                'message' => null,
                'url' => null,
                'redirect_timeout' => null,
                'splash' => <<<HTML
<div class="text-center">
    <img src="https://example.com/cat.jpg" onerror="alert(1)">
    <script>alert('xss')</script>
    <a href="javascript:alert(1)">Click me</a>
    <strong>Hello</strong>
</div>
HTML,
                'splash_timeout' => 3,
                'og_source' => null,
            ],
        ],
    );

    $request->setContainer(app());

    invokeEstimatePayCodePrepareForValidation($request);

    $splash = $request->input('rider.splash');

    expect($splash)
        ->toContain('<strong>Hello</strong>')
        ->not->toContain('<script>')
        ->not->toContain('onerror')
        ->not->toContain('javascript:');

    expect($request->input('rider.splash_meta'))
        ->toMatchArray([
            'sanitized' => true,
            'html_profile' => 'rider_splash',
        ]);
});

it('does not mutate empty estimate rider splash values', function (): void {
    $request = EstimatePayCodeRequest::create(
        uri: '/x-change/pay-codes/estimate',
        method: 'POST',
        parameters: [
            'amount' => 100,
            'currency' => 'PHP',
            'recipient' => [
                'mobile' => '09171234567',
            ],
            'rider' => [
                'message' => null,
                'url' => null,
                'redirect_timeout' => null,
                'splash' => null,
                'splash_timeout' => null,
                'og_source' => null,
            ],
        ],
    );

    $request->setContainer(app());

    invokeEstimatePayCodePrepareForValidation($request);

    expect($request->input('rider.splash'))->toBeNull()
        ->and($request->input('rider.splash_meta'))->toBeNull();
});

it('preserves existing estimate rider splash metadata while marking sanitization', function (): void {
    $request = EstimatePayCodeRequest::create(
        uri: '/x-change/pay-codes/estimate',
        method: 'POST',
        parameters: [
            'amount' => 100,
            'currency' => 'PHP',
            'recipient' => [
                'mobile' => '09171234567',
            ],
            'rider' => [
                'message' => null,
                'url' => null,
                'redirect_timeout' => null,
                'splash' => '<strong>Hello</strong>',
                'splash_timeout' => 3,
                'og_source' => null,
                'splash_meta' => [
                    'source' => 'depositor',
                ],
            ],
        ],
    );

    $request->setContainer(app());

    invokeEstimatePayCodePrepareForValidation($request);

    expect($request->input('rider.splash_meta'))
        ->toMatchArray([
            'source' => 'depositor',
            'sanitized' => true,
            'html_profile' => 'rider_splash',
        ]);
});
