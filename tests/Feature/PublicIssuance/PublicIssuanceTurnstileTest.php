<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceTurnstileVerifier;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    config()->set('x-change.public_auto_generate.turnstile', [
        'enabled' => true,
        'site_key' => 'test-site-key',
        'secret_key' => 'test-secret-key',
        'hostname' => 'payout.disburse.cash',
    ]);
});

it('accepts a matching one-time Turnstile verification', function (): void {
    Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
        'success' => true,
        'hostname' => 'payout.disburse.cash',
        'action' => 'public_auto_generate',
    ])]);

    $request = Request::create('https://payout.disburse.cash/x/auto-generate', 'POST');
    $request->headers->set('X-Turnstile-Token', 'visitor-token');

    app(PublicIssuanceTurnstileVerifier::class)->verify($request);

    Http::assertSent(fn ($outbound): bool => $outbound->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        && $outbound['secret'] === 'test-secret-key'
        && $outbound['response'] === 'visitor-token');
});

it('rejects missing, failed, and mismatched Turnstile responses', function (array $response, bool $sendToken): void {
    Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response($response)]);

    $request = Request::create('https://payout.disburse.cash/x/auto-generate', 'POST');

    if ($sendToken) {
        $request->headers->set('X-Turnstile-Token', 'visitor-token');
    }

    expect(fn () => app(PublicIssuanceTurnstileVerifier::class)->verify($request))
        ->toThrow(ValidationException::class);
})->with([
    'missing token' => [[], false],
    'failed' => [['success' => false], true],
    'wrong hostname' => [['success' => true, 'hostname' => 'elsewhere.test', 'action' => 'public_auto_generate'], true],
    'wrong action' => [['success' => true, 'hostname' => 'payout.disburse.cash', 'action' => 'other'], true],
]);

it('fails closed when the Turnstile secret or service is unavailable', function (): void {
    $request = Request::create('https://payout.disburse.cash/x/auto-generate', 'POST');
    $request->headers->set('X-Turnstile-Token', 'visitor-token');
    config()->set('x-change.public_auto_generate.turnstile.secret_key', null);

    expect(fn () => app(PublicIssuanceTurnstileVerifier::class)->verify($request))
        ->toThrow(HttpException::class);

    config()->set('x-change.public_auto_generate.turnstile.secret_key', 'test-secret-key');
    Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([], 503)]);

    expect(fn () => app(PublicIssuanceTurnstileVerifier::class)->verify($request))
        ->toThrow(HttpException::class);
});

it('blocks public order creation without a challenge before opening a funding order', function (): void {
    config()->set('x-change.public_auto_generate.enabled', true);
    config()->set('x-change.commercial.principal.reference', 'commercial-public');
    config()->set('x-change.commercial.principal.legal_name', '3neti R&D OPC');
    config()->set('x-change.commercial.principal.authorization_reference', 'commissioning:commercial-public:v1');
    CommercialPrincipal::query()->create([
        'reference' => 'commercial-public',
        'legal_name' => '3neti R&D OPC',
        'authorization_reference' => 'commissioning:commercial-public:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);

    $this->withHeaders([
        'X-Inertia' => 'true',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get(route('x-change.public-auto-generate.show'))
        ->assertOk()
        ->assertJsonPath('props.turnstile.enabled', true)
        ->assertJsonPath('props.turnstile.site_key', 'test-site-key')
        ->assertDontSee('test-secret-key');

    $this->postJson(route('x-change.public-auto-generate.store'), [
        'cash' => ['amount' => 25, 'currency' => 'PHP'],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null],
        'rider' => ['message' => null],
    ])->assertUnprocessable()->assertJsonValidationErrors('turnstile');

    expect(PayCodeIssuanceFundingOrder::query()->count())->toBe(0);
    Http::assertNothingSent();
});
