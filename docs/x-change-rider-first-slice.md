# x-change Rider Experience — First Implementation Slice

## Scope

This slice adds the server-side rider redirect boundary to `3neti/x-change`.

It intentionally does **not** implement SSR, Vue rendering, or full splash wiring yet.

This slice implements:

- `RiderExperienceData`
- `RiderExperienceResolverContract`
- `SuccessRedirectResolverContract`
- `DefaultRiderExperienceResolver`
- `DefaultSuccessRedirectResolver`
- `ClaimSuccessRedirectController`
- package service-provider bindings
- package config entries
- redirect feature/unit tests

---

## 1. Add config

### `config/x-change.php`

Add:

```php
'rider' => [
    'default_splash_timeout' => env('X_CHANGE_RIDER_SPLASH_TIMEOUT', 3),
    'default_redirect_timeout' => env('X_CHANGE_RIDER_REDIRECT_TIMEOUT', 5),

    'fallback_url' => env('X_CHANGE_RIDER_FALLBACK_URL', '/'),

    'allowed_redirect_schemes' => [
        'http',
        'https',
        'intent',
    ],

    // Empty means no host allowlist is enforced for http/https URLs.
    // Banks may set this to their own trusted domains.
    'allowed_redirect_hosts' => [
        // 'example.com',
    ],
],
```

---

## 2. Add DTO

### `src/Data/RiderExperienceData.php`

```php
<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

final class RiderExperienceData extends Data
{
    public function __construct(
        public readonly ?string $splash,
        public readonly ?string $splashType,
        public readonly int $splashTimeout,
        public readonly ?string $message,
        public readonly ?string $messageType,
        public readonly ?string $redirectUrl,
        public readonly int $redirectTimeout,
        public readonly ?string $fallbackUrl,
        public readonly bool $hasRedirect,
    ) {}
}
```

---

## 3. Add contracts

### `src/Contracts/RiderExperienceResolverContract.php`

```php
<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\RiderExperienceData;

interface RiderExperienceResolverContract
{
    public function forVoucher(Voucher $voucher): RiderExperienceData;
}
```

### `src/Contracts/SuccessRedirectResolverContract.php`

```php
<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface SuccessRedirectResolverContract
{
    public function redirectUrlFor(Voucher $voucher): string;
}
```

---

## 4. Add default rider resolver

### `src/Services/DefaultRiderExperienceResolver.php`

```php
<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RiderExperienceResolverContract;
use LBHurtado\XChange\Data\RiderExperienceData;

final class DefaultRiderExperienceResolver implements RiderExperienceResolverContract
{
    public function forVoucher(Voucher $voucher): RiderExperienceData
    {
        $rider = $voucher->instructions?->rider;

        $redirectUrl = $rider?->url;
        $fallbackUrl = config('x-change.rider.fallback_url', '/');

        return new RiderExperienceData(
            splash: $rider?->splash,
            splashType: $this->detectContentType($rider?->splash),
            splashTimeout: (int) ($rider?->splash_timeout ?? config('x-change.rider.default_splash_timeout', 3)),
            message: $rider?->message,
            messageType: $this->detectContentType($rider?->message),
            redirectUrl: $redirectUrl,
            redirectTimeout: (int) ($rider?->redirect_timeout ?? config('x-change.rider.default_redirect_timeout', 5)),
            fallbackUrl: $fallbackUrl,
            hasRedirect: filled($redirectUrl),
        );
    }

    private function detectContentType(?string $content): ?string
    {
        if (! filled($content)) {
            return null;
        }

        $trimmed = trim($content);

        return match (true) {
            str_starts_with($trimmed, '<svg') => 'svg',
            (bool) preg_match('/<[a-z][\s\S]*>/i', $trimmed) => 'html',
            (bool) preg_match('/^https?:\/\//i', $trimmed) => 'url',
            (bool) preg_match('/^#+\s|^\*\*|\*\s/m', $trimmed) => 'markdown',
            default => 'text',
        };
    }
}
```

---

## 5. Add safe redirect resolver

### `src/Services/DefaultSuccessRedirectResolver.php`

```php
<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SuccessRedirectResolverContract;

final class DefaultSuccessRedirectResolver implements SuccessRedirectResolverContract
{
    public function redirectUrlFor(Voucher $voucher): string
    {
        $fallbackUrl = (string) config('x-change.rider.fallback_url', '/');
        $rawUrl = $voucher->instructions?->rider?->url;

        if (! filled($rawUrl)) {
            return $fallbackUrl;
        }

        $url = trim((string) $rawUrl);

        if (str_starts_with($url, 'intent://')) {
            return $this->resolveIntentFallback($url) ?? $fallbackUrl;
        }

        if (! $this->hasAllowedScheme($url)) {
            return $fallbackUrl;
        }

        if (! $this->hasAllowedHost($url)) {
            return $fallbackUrl;
        }

        return $url;
    }

    private function resolveIntentFallback(string $url): ?string
    {
        if (! preg_match('/S\.browser_fallback_url=([^;]+)/', $url, $matches)) {
            return null;
        }

        $fallback = urldecode($matches[1]);

        if (! $this->hasAllowedScheme($fallback)) {
            return null;
        }

        if (! $this->hasAllowedHost($fallback)) {
            return null;
        }

        return $fallback;
    }

    private function hasAllowedScheme(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            return false;
        }

        return in_array(strtolower($scheme), config('x-change.rider.allowed_redirect_schemes', ['http', 'https']), true);
    }

    private function hasAllowedHost(string $url): bool
    {
        $allowedHosts = config('x-change.rider.allowed_redirect_hosts', []);

        if (! is_array($allowedHosts) || $allowedHosts === []) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host)) {
            return false;
        }

        $host = strtolower($host);

        return collect($allowedHosts)
            ->map(fn (string $allowedHost): string => strtolower($allowedHost))
            ->contains(fn (string $allowedHost): bool => $host === $allowedHost || str_ends_with($host, '.'.$allowedHost));
    }
}
```

---

## 6. Add redirect controller

### `src/Http/Controllers/ClaimSuccessRedirectController.php`

```php
<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SuccessRedirectResolverContract;

final class ClaimSuccessRedirectController extends Controller
{
    public function __construct(
        private readonly SuccessRedirectResolverContract $redirectResolver,
    ) {}

    public function __invoke(string $code): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher) {
            return redirect(config('x-change.rider.fallback_url', '/'));
        }

        $url = $this->redirectResolver->redirectUrlFor($voucher);

        return Inertia::location($url);
    }
}
```

---

## 7. Register bindings

In the x-change service provider, add:

```php
use LBHurtado\XChange\Contracts\RiderExperienceResolverContract;
use LBHurtado\XChange\Contracts\SuccessRedirectResolverContract;
use LBHurtado\XChange\Services\DefaultRiderExperienceResolver;
use LBHurtado\XChange\Services\DefaultSuccessRedirectResolver;

public function register(): void
{
    // existing bindings...

    $this->app->bind(
        RiderExperienceResolverContract::class,
        DefaultRiderExperienceResolver::class,
    );

    $this->app->bind(
        SuccessRedirectResolverContract::class,
        DefaultSuccessRedirectResolver::class,
    );
}
```

---

## 8. Register package route

Where x-change registers public web routes, add:

```php
use LBHurtado\XChange\Http\Controllers\ClaimSuccessRedirectController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('x-change.routes.web_prefix', 'x'))
    ->name('x-change.')
    ->group(function (): void {
        Route::match(['get', 'post'], '/pay-codes/{code}/claim/redirect', ClaimSuccessRedirectController::class)
            ->name('pay-codes.claim.redirect');
    });
```

If the package already uses `/api/x/v1`, keep this as a web route because browser redirects are not API responses.

---

## 9. Unit tests

### `tests/Unit/Services/DefaultSuccessRedirectResolverTest.php`

```php
<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\DefaultSuccessRedirectResolver;

function voucherWithRiderUrl(?string $url): Voucher
{
    $voucher = new Voucher;
    $voucher->forceFill([
        'code' => 'ABC123',
        'metadata' => [],
    ]);

    // If your voucher instructions are cast to DTOs, prefer the package factory/helper here.
    $voucher->setRelation('instructions', null);
    $voucher->instructions = (object) [
        'rider' => (object) [
            'url' => $url,
        ],
    ];

    return $voucher;
}

it('returns fallback when rider url is missing', function () {
    config()->set('x-change.rider.fallback_url', '/fallback');

    $url = app(DefaultSuccessRedirectResolver::class)
        ->redirectUrlFor(voucherWithRiderUrl(null));

    expect($url)->toBe('/fallback');
});

it('allows https rider urls', function () {
    config()->set('x-change.rider.fallback_url', '/fallback');

    $url = app(DefaultSuccessRedirectResolver::class)
        ->redirectUrlFor(voucherWithRiderUrl('https://example.com/thank-you'));

    expect($url)->toBe('https://example.com/thank-you');
});

it('rejects unsupported schemes', function () {
    config()->set('x-change.rider.fallback_url', '/fallback');

    $url = app(DefaultSuccessRedirectResolver::class)
        ->redirectUrlFor(voucherWithRiderUrl('javascript:alert(1)'));

    expect($url)->toBe('/fallback');
});

it('extracts safe browser fallback from intent urls', function () {
    config()->set('x-change.rider.fallback_url', '/fallback');

    $intent = 'intent://scan/#Intent;scheme=gcash;S.browser_fallback_url=https%3A%2F%2Fexample.com%2Ffallback;end';

    $url = app(DefaultSuccessRedirectResolver::class)
        ->redirectUrlFor(voucherWithRiderUrl($intent));

    expect($url)->toBe('https://example.com/fallback');
});

it('enforces allowed hosts when configured', function () {
    config()->set('x-change.rider.fallback_url', '/fallback');
    config()->set('x-change.rider.allowed_redirect_hosts', ['trusted.example']);

    $resolver = app(DefaultSuccessRedirectResolver::class);

    expect($resolver->redirectUrlFor(voucherWithRiderUrl('https://trusted.example/path')))
        ->toBe('https://trusted.example/path');

    expect($resolver->redirectUrlFor(voucherWithRiderUrl('https://evil.example/path')))
        ->toBe('/fallback');
});
```

> Note: adjust the `voucherWithRiderUrl()` helper to match the actual voucher instruction casting in the package testbench. The important assertions are the URL decisions.

---

## 10. Feature tests

### `tests/Feature/Http/ClaimSuccessRedirectControllerTest.php`

```php
<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;

it('redirects through inertia location to the resolved rider url', function () {
    config()->set('x-change.rider.fallback_url', '/fallback');

    $voucher = Voucher::factory()->create([
        'code' => 'RIDER123',
        'instructions' => [
            'rider' => [
                'url' => 'https://example.com/thank-you',
            ],
        ],
    ]);

    $this->get('/x/pay-codes/'.$voucher->code.'/claim/redirect')
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://example.com/thank-you');
});

it('falls back when the voucher does not exist', function () {
    config()->set('x-change.rider.fallback_url', '/fallback');

    $this->get('/x/pay-codes/MISSING/claim/redirect')
        ->assertRedirect('/fallback');
});

it('falls back when rider url is unsafe', function () {
    config()->set('x-change.rider.fallback_url', '/fallback');

    $voucher = Voucher::factory()->create([
        'code' => 'BADURL123',
        'instructions' => [
            'rider' => [
                'url' => 'javascript:alert(1)',
            ],
        ],
    ]);

    $this->get('/x/pay-codes/'.$voucher->code.'/claim/redirect')
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', '/fallback');
});
```

---

## 11. Host app adapter note

After the package route exists, update redeem-x `Success.vue` to redirect to the package/controller endpoint instead of directly to `props.rider.url`.

Preferred prop shape:

```php
'redirect_endpoint' => route('x-change.pay-codes.claim.redirect', ['code' => $voucher->code]),
```

Vue change:

```ts
const hasRiderUrl = computed(() => !!props.redirect_endpoint);

const handleRedirect = () => {
    if (!props.redirect_endpoint) return;
    isRedirecting.value = true;
    window.location.href = props.redirect_endpoint;
};
```

This keeps all redirect safety server-side.

---

## 12. Commit message

```text
Add rider experience redirect contracts
```

Suggested body:

```text
Introduce first-class rider experience wiring for claim success redirects.

Adds RiderExperienceData, rider/redirect resolver contracts, default resolver
implementations, and a package-owned claim redirect controller. The default
redirect resolver safely handles missing URLs, unsupported schemes, optional
host allowlists, and Android intent browser fallbacks.

This prepares x-change to own rider UX contracts while allowing host apps to
override branding and redirect behavior.
```
