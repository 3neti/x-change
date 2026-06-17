# X-Change Install And Onboarding Manual

This manual describes the turnkey host-app scaffold installed by:

```bash
php artisan x-change:install
```

The goal is to make a fresh Laravel host app behave like a bootable x-change OS: mobile-first auth, x-change UI, onboarding readiness, provider topology diagnostics, and executable host tests.

## Fresh Host Flow

Use this flow for a new Laravel application:

```bash
composer require 3neti/x-change
php artisan x-change:install
npm install
npm run build
php artisan x-change:doctor --json
php artisan test
```

During development, `npm run dev` can replace `npm run build`.

## What `x-change:install` Publishes

The installer is intentionally broad. It publishes package-owned source into the host because Inertia, Fortify, routes, and the authenticated `User` model are host-owned in Laravel.

### Core X-Change UI

Published by `x-change-ui`.

This includes x-change pages, components, layouts, composables, shared phone/financial controls, form-flow helpers, and supporting static documents.

These files are compiled by the host Vite build, so they must exist in the host `resources/js` tree.

### Form Flow Drivers

Published by `x-change-form-flow-drivers`.

This installs the voucher redemption form-flow driver config into the host config tree.

### Onboarding Assets

If `3neti/onboarding` is installed, the installer publishes:

- `onboarding-config`
- `onboarding-migrations`

If the onboarding package is not available, the installer warns and continues.

### Mobile-First Auth Scaffold

Published by `x-change-auth`.

Source stubs:

- `stubs/migrations/2026_06_17_000000_prepare_users_for_mobile_first_xchange.php.stub`
- `stubs/app/Models/User.php.stub`
- `stubs/database/factories/UserFactory.php.stub`
- `stubs/resources/js/pages/auth/Login.vue.stub`
- `stubs/resources/js/pages/auth/Register.vue.stub`

Host targets:

- `database/migrations/2026_06_17_000000_prepare_users_for_mobile_first_xchange.php`
- `app/Models/User.php`
- `database/factories/UserFactory.php`
- `resources/js/pages/auth/Login.vue`
- `resources/js/pages/auth/Register.vue`

This scaffold makes Fortify use mobile as the account identity while preserving Fortify sessions, password hashing, remember-me behavior, throttling, and two-factor flow.

### Mobile-First Auth Tests

Published by `x-change-auth-tests`.

Source stubs:

- `stubs/tests/Feature/Auth/AuthenticationTest.php.stub`
- `stubs/tests/Feature/Auth/RegistrationTest.php.stub`

Host targets:

- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/Auth/RegistrationTest.php`

These tests prove the installed host can register, log in, log out, throttle login attempts, and route two-factor login using the mobile-first scaffold.

### Mobile-First Settings Scaffold

Published by `x-change-settings`.

Source stubs:

- `stubs/app/Concerns/ProfileValidationRules.php.stub`
- `stubs/app/Http/Requests/Settings/ProfileUpdateRequest.php.stub`
- `stubs/app/Http/Controllers/Settings/ProfileController.php.stub`
- `stubs/app/Http/Controllers/Settings/SecurityController.php.stub`
- `stubs/routes/settings.php.stub`
- `stubs/resources/js/pages/settings/Profile.vue.stub`
- `stubs/resources/js/pages/settings/SecurityConfirm.vue.stub`

Host targets:

- `app/Concerns/ProfileValidationRules.php`
- `app/Http/Requests/Settings/ProfileUpdateRequest.php`
- `app/Http/Controllers/Settings/ProfileController.php`
- `app/Http/Controllers/Settings/SecurityController.php`
- `routes/settings.php`
- `resources/js/pages/settings/Profile.vue`
- `resources/js/pages/settings/SecurityConfirm.vue`

This scaffold makes the settings area mobile-first:

- Profile includes a required mobile number.
- Email is optional.
- Mobile is normalized to the `63...` format.
- Profile updates refresh `mobile_verified_at`.
- The model-channel mobile value is synchronized when available.
- Security confirmation stays inside `/settings/security/confirm` instead of redirecting to Fortify's standalone `/user/confirm-password` page.
- The confirmation page asks for the current PIN inside the settings layout.

### Mobile-First Settings Tests

Published by `x-change-settings-tests`.

Source stubs:

- `stubs/tests/Feature/Settings/ProfileUpdateTest.php.stub`
- `stubs/tests/Feature/Settings/SecurityTest.php.stub`

Host targets:

- `tests/Feature/Settings/ProfileUpdateTest.php`
- `tests/Feature/Settings/SecurityTest.php`

These tests prove profile mobile updates, optional email behavior, password/PIN update behavior, and the settings-scoped confirmation route.

### Branding Assets

Published by `x-change-assets`.

This copies x-change images and favicon assets into `public/vendor/x-change`.

### Form-Flow Handler Assets

Unless skipped, the installer detects installed form-flow handler providers and publishes their assets:

- KYC
- Location
- OTP
- Selfie
- Signature

Only installed providers are published.

### X-Rider Assets

If `3neti/x-rider` is installed, the installer publishes:

- `x-rider-ui`
- `x-rider-drivers`

### Migrations

Unless skipped, the installer runs:

```bash
php artisan migrate --force
```

## Installer Options

Use these options to narrow or defer installation work:

```bash
php artisan x-change:install --force
```

Overwrite previously published files.

```bash
php artisan x-change:install --no-auth
```

Skip mobile-first auth scaffold.

```bash
php artisan x-change:install --no-auth-tests
```

Skip mobile-first auth test scaffold while still publishing auth runtime files.

```bash
php artisan x-change:install --no-settings
```

Skip mobile-first settings scaffold.

```bash
php artisan x-change:install --no-settings-tests
```

Skip mobile-first settings tests while still publishing settings runtime files.

```bash
php artisan x-change:install --no-assets
```

Skip branding assets.

```bash
php artisan x-change:install --no-handlers
```

Skip form-flow handler asset publishing.

```bash
php artisan x-change:install --no-rider
```

Skip x-rider asset publishing.

```bash
php artisan x-change:install --no-migrate
```

Publish files without running migrations.

## Runtime Auth Behavior

When mobile-first auth is enabled, x-change configures Fortify at runtime:

- `fortify.username = mobile`
- `fortify.lowercase_usernames = false`
- mobile-first login authenticator
- mobile-first registration creator
- mobile/PIN-oriented Fortify login/register views

The package deliberately keeps Fortify in charge of sessions, password hashing, throttling, two-factor login, and password confirmation.

## Mobile Number Format

The mobile-first scaffold normalizes Philippine mobile numbers:

- `09171234567` becomes `639171234567`
- `9171234567` becomes `639171234567`
- `639171234567` stays `639171234567`

The raw `users.mobile` column is the Fortify identity source.

Some hosts may also use `3neti/model-channel`. When available, profile registration/update code attempts to sync the model-channel mobile value as a convenience, but Fortify continues to authenticate against `users.mobile`.

## Provider Topology

The install/onboarding scaffold separates provider topology from auth.

Current topology keys:

- `manual`
- `ledger_pooled`
- `provider_customer_wallet`

Aliases:

- `netbank` maps to `ledger_pooled`
- `paynamics` maps to `provider_customer_wallet`

Use:

```bash
php artisan x-change:doctor --json
```

to confirm the active topology resolves.

## Doctor Checks

`x-change:doctor` verifies the installed host surface:

- x-change config is loaded
- onboarding package is installed
- onboarding config is loaded
- onboarding sessions table exists
- `users.mobile` exists
- `users.mobile_verified_at` exists
- `users.identity_level` exists
- Fortify username is `mobile`
- provider topology resolves

Run it after installing or republishing scaffolds:

```bash
php artisan x-change:doctor --json
```

## Customization Rules

Treat `packages/x-change/stubs/**` as the source of truth for host scaffolding.

If a host file was installed by x-change and the desired behavior should apply to future hosts, update the corresponding stub first, then republish and verify.

Examples:

- Change login UX in `stubs/resources/js/pages/auth/Login.vue.stub`
- Change registration UX in `stubs/resources/js/pages/auth/Register.vue.stub`
- Change profile settings UX in `stubs/resources/js/pages/settings/Profile.vue.stub`
- Change security confirmation UX in `stubs/resources/js/pages/settings/SecurityConfirm.vue.stub`
- Change host auth model behavior in `stubs/app/Models/User.php.stub`
- Change host auth tests in `stubs/tests/Feature/Auth/*.php.stub`
- Change host settings tests in `stubs/tests/Feature/Settings/*.php.stub`

After changing stubs, run:

```bash
php artisan vendor:publish --tag=x-change-auth --force
php artisan vendor:publish --tag=x-change-auth-tests --force
php artisan vendor:publish --tag=x-change-settings --force
php artisan vendor:publish --tag=x-change-settings-tests --force
```

Then verify:

```bash
php artisan test --compact tests/Feature/Auth/AuthenticationTest.php tests/Feature/Auth/RegistrationTest.php tests/Feature/Settings/ProfileUpdateTest.php tests/Feature/Settings/SecurityTest.php
php artisan x-change:doctor --json
npm run build
```

For narrow frontend checks during development:

```bash
npx eslint resources/js/pages/auth/Login.vue resources/js/pages/auth/Register.vue resources/js/pages/settings/Profile.vue resources/js/pages/settings/SecurityConfirm.vue
```

## Safe Host Overrides

Some applications will need to customize the scaffold after install.

Prefer this order:

1. Update x-change stubs if the behavior should be the default for every host.
2. Publish with `--force`.
3. Customize the host copy only if the behavior is host-specific.
4. Use `--no-auth`, `--no-auth-tests`, `--no-settings`, or `--no-settings-tests` on future installs to avoid overwriting host-specific files.

## Known Boundaries

The x-change package cannot fully avoid publishing host files because Laravel owns these integration points in the host app:

- `App\Models\User`
- Fortify-rendered Inertia pages
- settings controllers and routes
- host feature tests
- host Vite compilation

The package therefore uses stubs to make those host modifications explicit, repeatable, and reviewable.

## Minimal Acceptance Checklist

After a fresh install, this should pass:

```bash
php artisan x-change:doctor --json
php artisan test --compact tests/Feature/Auth/AuthenticationTest.php tests/Feature/Auth/RegistrationTest.php tests/Feature/Settings/ProfileUpdateTest.php tests/Feature/Settings/SecurityTest.php
npx eslint resources/js/pages/auth/Login.vue resources/js/pages/auth/Register.vue resources/js/pages/settings/Profile.vue resources/js/pages/settings/SecurityConfirm.vue
```

Manual smoke path:

1. Visit `/register`.
2. Register with mobile and PIN.
3. Visit `/settings/profile`.
4. Confirm the mobile field is visible and editable.
5. Visit `/settings/security`.
6. If confirmation is required, confirm the page stays under `/settings/security/confirm`.
7. Enter the current PIN.
8. Confirm the security page returns inside settings.
