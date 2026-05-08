# AI Agent Instruction — Make x-change Config Publishing Optional

## Objective

Refactor the `3neti/x-change` package so that:

- the package works immediately after installation
- `vendor:publish` of `config/x-change.php` is NOT required
- host applications may still override configuration when needed
- defaults remain package-owned
- environment variables become the primary override mechanism for common integrations

This aligns with the architectural direction of x-change as a self-contained orchestration package with optional host-app customization.

---

# Core Principle

## Current Problem

The package currently assumes that:
- the host app will publish `config/x-change.php`
- the host app will manually edit config values

This creates:
- unnecessary installation friction
- duplicated config files
- upgrade drift between package and host app
- larger maintenance burden for licensees

---

# Desired Behavior

After installation:

```bash
composer require 3neti/x-change
php artisan x-change:install
```

the package should already function using:
- package defaults
- `.env` overrides
- Laravel conventional defaults

without requiring:

```bash
php artisan vendor:publish
```

for normal operation.

---

# Architectural Rule

## Config Ownership Model

### Package owns:
- defaults
- structure
- internal service wiring
- default implementations

### Host app owns:
- environment values
- optional overrides
- advanced customization

---

# Required Refactor

## 1. Ensure Service Provider Uses `mergeConfigFrom()`

Inside the package service provider:

```php
$this->mergeConfigFrom(
    __DIR__.'/../config/x-change.php',
    'x-change'
);
```

This is mandatory.

Reason:
- Laravel automatically merges package defaults
- host app config overrides still work if published later
- config becomes optional to publish

---

# 2. Convert Host-App-Specific Values to ENV-Based Resolution

The package config should avoid assuming concrete application classes whenever possible.

Instead of:

```php
'issuer_model' => App\Models\User::class,
```

prefer:

```php
'issuer_model' => env(
    'XCHANGE_ONBOARDING_DEFAULT_ISSUER_MODEL',
    config('auth.providers.users.model', \App\Models\User::class)
),
```

Likewise:

```php
'destination' => [
    'model' => env(
        'XCHANGE_REVENUE_DESTINATION_MODEL',
        config('auth.providers.users.model', \App\Models\User::class)
    ),
],
```

Reason:
- Laravel apps already define the canonical user model
- reduces package assumptions
- reduces required setup
- works immediately in standard Laravel installs

---

# 3. Minimize Required Published Artifacts

## Config publishing must become optional

Do NOT require publishing:
- `config/x-change.php`

for standard operation.

---

# 4. Reclassify Publishable Resources

## Always Required
These may still be published or installed automatically:
- migrations
- frontend assets
- routes (if package strategy requires it)
- stubs

## Optional / Advanced
These should be optional:
- config/x-change.php
- pricing overrides
- terminology overrides
- advanced service bindings

---

# 5. Update Install Command Philosophy

`php artisan x-change:install`

should:
- install migrations
- optionally install assets/UI
- optionally append `.env` keys
- NOT require config publishing

---

# 6. Add Optional Advanced Publish Command

Support:

```bash
php artisan vendor:publish --tag=x-change-config
```

for advanced adopters and licensees.

This should be:
- optional
- customization-oriented
- not part of normal setup

---

# 7. Preserve Override Capability

The host app must still be able to:
- publish config later
- override services
- replace models
- customize pricing
- customize terminology

The refactor is about:
- reducing mandatory setup
- not removing extensibility

---

# 8. Avoid Breaking Existing Installs

Maintain backward compatibility.

If a host app already published:
- `config/x-change.php`

the package must continue working unchanged.

Do NOT:
- rename config keys unnecessarily
- remove existing env keys
- break existing integrations

---

# 9. Preferred Configuration Strategy Going Forward

## Use ENV for:
- model classes
- provider credentials
- feature toggles
- service endpoints
- operational settings

## Use package config defaults for:
- internal architecture
- service bindings
- orchestration defaults
- terminology defaults
- pricing defaults

## Use published config only for:
- advanced customization
- white-label deployments
- heavily customized licensees

---

# 10. Acceptance Criteria

The implementation is complete when:

- [ ] Fresh Laravel install works without publishing config
- [ ] `x-change.php` is merged automatically
- [ ] ENV overrides function correctly
- [ ] Existing published configs still work
- [ ] Install command no longer assumes config publishing
- [ ] Package tests pass without published config
- [ ] Documentation reflects optional publishing model

---

# Guiding Principle

> Package defaults should be operational.  
> Publishing config should customize behavior, not enable behavior.
