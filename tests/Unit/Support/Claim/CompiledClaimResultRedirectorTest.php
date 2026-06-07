<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\CompiledClaimResultRedirector;

function compiledClaimResultWithStatus(string $status): object
{
    return new class($status)
    {
        public function __construct(
            public string $status,
        ) {}
    };
}

it('redirects successful compiled claim result to claim success page', function () {
    $voucher = issueVoucher();

    $response = app(CompiledClaimResultRedirector::class)->redirect(
        voucher: $voucher,
        result: compiledClaimResultWithStatus('success'),
    );

    expect($response->getTargetUrl())->toBe(route('x-change.claim.success', [
        'code' => $voucher->code,
    ]));
});

it('redirects completed compiled claim result to claim success page', function () {
    $voucher = issueVoucher();

    $response = app(CompiledClaimResultRedirector::class)->redirect(
        voucher: $voucher,
        result: compiledClaimResultWithStatus('completed'),
    );

    expect($response->getTargetUrl())->toBe(route('x-change.claim.success', [
        'code' => $voucher->code,
    ]));
});

it('redirects pending compiled claim result to approval placeholder when route is not registered', function () {
    $voucher = issueVoucher();

    $response = app(CompiledClaimResultRedirector::class)->redirect(
        voucher: $voucher,
        result: compiledClaimResultWithStatus('pending'),
    );

    expect($response->getTargetUrl())->toBe(url("/x/claim/{$voucher->code}/approval"));
});

it('rejects unsupported compiled claim result status', function () {
    $voucher = issueVoucher();

    app(CompiledClaimResultRedirector::class)->redirect(
        voucher: $voucher,
        result: compiledClaimResultWithStatus('failed'),
    );
})->throws(RuntimeException::class, 'Unsupported compiled claim result status [failed].');
