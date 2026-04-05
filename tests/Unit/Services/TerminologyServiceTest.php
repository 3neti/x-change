<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use LBHurtado\XChange\Services\TerminologyService;

it('returns configured terminology for a known term', function () {
    config()->set('x-change.terminology.voucher', 'Pay Code');

    $translator = Mockery::mock(Translator::class);

    $service = new TerminologyService($translator);

    expect($service->term('voucher'))->toBe('Pay Code');
});

it('falls back to the provided default when terminology is not configured', function () {
    config()->set('x-change.terminology.unknown_term', null);

    $translator = Mockery::mock(Translator::class);

    $service = new TerminologyService($translator);

    expect($service->term('unknown_term', 'Fallback Term'))->toBe('Fallback Term');
});

it('falls back to the key when terminology is not configured and no default is given', function () {
    config()->set('x-change.terminology.unknown_term', null);

    $translator = Mockery::mock(Translator::class);

    $service = new TerminologyService($translator);

    expect($service->term('unknown_term'))->toBe('unknown_term');
});

it('returns a translated message when available', function () {
    config()->set('x-change.terminology.voucher', 'Pay Code');
    config()->set('x-change.terminology.wallet', 'Wallet');

    $translator = Mockery::mock(Translator::class);
    $translator->shouldReceive('get')
        ->once()
        ->with('x-change.messages.voucher_redeemed', Mockery::type('array'))
        ->andReturn('This Pay Code has already been claimed.');

    $service = new TerminologyService($translator);

    expect($service->message('voucher_redeemed'))
        ->toBe('This Pay Code has already been claimed.');
});

it('falls back to the provided default message when translation is missing', function () {
    config()->set('x-change.terminology.voucher', 'Pay Code');

    $translator = Mockery::mock(Translator::class);
    $translator->shouldReceive('get')
        ->once()
        ->with('x-change.messages.voucher_not_found', Mockery::type('array'))
        ->andReturn('x-change.messages.voucher_not_found');

    $service = new TerminologyService($translator);

    expect(
        $service->message('voucher_not_found', [], 'We could not find that :voucher.')
    )->toBe('We could not find that Pay Code.');
});

it('replaces custom placeholders in fallback messages', function () {
    config()->set('x-change.terminology.voucher', 'Pay Code');

    $translator = Mockery::mock(Translator::class);
    $translator->shouldReceive('get')
        ->once()
        ->with('x-change.messages.custom', Mockery::type('array'))
        ->andReturn('x-change.messages.custom');

    $service = new TerminologyService($translator);

    expect(
        $service->message('custom', ['code' => 'ABCD-1234'], ':voucher :code is ready.')
    )->toBe('Pay Code ABCD-1234 is ready.');
});

afterEach(function () {
    Mockery::close();
});
