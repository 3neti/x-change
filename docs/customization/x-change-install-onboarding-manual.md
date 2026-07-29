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
php artisan xchange:lifecycle:run-group turnkey-onboarding --no-claim --timeout=1 --poll=1 --max-polls=1
php artisan test
```

During development, `npm run dev` can replace `npm run build`.

Before the installer changes the database or publishes UI files, every fresh or
safely resumable required Treasury connection must pass both static
configuration checks and a read-only live check. The live check authenticates
and reads an authoritative provider balance. It does not move money. An already
initialized connection is not live-probed during a reinstall. Diagnose provider
readiness independently with:

```bash
php artisan x-change:treasury:preflight --no-interaction
php artisan x-change:treasury:preflight --live --no-interaction
```

The live command reports only sanitized failure codes. It never prints provider
credentials, tokens, account numbers, authorization headers, raw response
bodies, or sensitive URLs.

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

## First Install Versus Reinstall

The installer classifies every active Treasury connection before contacting a
provider:

- **Fresh or resumable:** no Treasury topology exists, or an exact partial
  Position topology exists without its Inventory. Live preflight, idempotent
  provisioning, opening reconciliation, and any authorized opening
  capitalization run.
- **Initialized:** the ten canonical system Positions and matching Inventory
  are complete and agree with configuration. The installer skips provider
  access and every opening-balance action.
- **Incomplete or conflicting:** existing topology disagrees with immutable
  configuration, or an Inventory exists without the complete Position set. The
  installer fails before migrations or publication.

The routine reinstall and asset-republish path is:

```bash
php artisan x-change:install --force && php artisan optimize:clear && npm run dev
```

For an initialized NetBank Treasury, expect:

```text
Treasury already initialized [netbank-primary]; skipping opening live preflight and reconciliation.
```

That message means the command does not call the provider, does not rerun
Treasury provisioning, does not reinterpret operational balance drift as an
opening balance, and does not capitalize funds. Migrations and requested asset
publication continue normally.

This is deliberately different from `--no-treasury`. An initialized reinstall
still validates local Treasury configuration and canonical topology.
`--no-treasury` explicitly defers all Treasury checks and initialization work.

## Installer Options

Use these options to narrow or defer installation work:

```bash
php artisan x-change:install --force
```

Overwrite previously published files.

`--force` controls replacement of published files. It does not bypass Treasury
configuration or topology validation. Fresh and resumable connections still
require live provider readiness, opening reconciliation, and capitalization
controls. Initialized connections skip those one-time opening steps because
their durable topology proves initialization is complete.

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

```bash
php artisan x-change:install --no-treasury --no-interaction
```

Explicitly defer Treasury initialization for a build or recovery workflow. The
installer visibly reports the deferred state and skips provider preflight,
Treasury Position provisioning, and opening reconciliation. It never selects
this mode automatically after a provider failure.

For a first deployment where the reconciled provider opening balance is
confirmed to belong to the system principal:

```bash
php artisan x-change:install \
    --force \
    --treasury-opening-policy=system-capital \
    --capitalization-authorization-reference=deployment-20260726-001 \
    --confirm-system-ownership \
    --no-interaction
```

This does not accept a balance amount. The installer reads and reconciles each
selected provider, then moves the exact `Legacy Unattributed` value to the
system `Account Funding Reserve`. The authorization reference must identify a
real deployment or control record and must remain stable when retrying the same
installation.

Use `--treasury-opening-policy=unattributed` (the default) when ownership has
not been established. Use `--treasury-opening-policy=configured` to apply
provider-specific settings such as:

```dotenv
XCHANGE_TREASURY_NETBANK_OPENING_POLICY=system-capital
XCHANGE_TREASURY_PAYNAMICS_OPENING_POLICY=unattributed
```

Capitalization options cannot be combined with `--no-treasury`.

The installer order is:

1. static Treasury configuration and canonical topology classification;
2. fail immediately on incomplete or conflicting local topology;
3. live provider preflight only for fresh or resumable connections;
4. migration preparation and migrations;
5. system principal and Treasury Position provisioning only for live-ready
   fresh or resumable connections;
6. their authoritative opening-balance observation and reconciliation;
7. optional, explicitly authorized opening capitalization; and
8. UI and remaining asset publication.

A failed required live connection that still needs initialization stops before
migrations and publication. A failed optional fresh connection is reported but
does not block healthy required connections; it receives no Treasury Positions.
An initialized connection neither calls the live probe nor the balance reader
during install. `--no-interaction` never prompts, silently skips a required
fresh provider, accepts an operator-entered balance, or falls back to manual
capitalization.

## Operational Treasury Drift Is Not Installation

Once the installer reports `Treasury already initialized`, provider balance
differences belong to operational reconciliation. Reinstalling, clearing
caches, or republishing UI files must not mutate them.

For provider connectivity:

```bash
php artisan x-change:treasury:preflight --live --no-interaction
```

`connection_timeout` is a sanitized provider-read failure. Retry the health
check when the provider is reachable; it should not send an initialized
Treasury back through opening reconciliation.

When the provider balance is below internal Inventory and Positions with
`provider-balance-below-internal-attribution`, inspect the narrowly controlled
historical repair:

```bash
php artisan x-change:treasury:repair-missing-disbursement-postings \
    --connection=netbank-primary \
    --json \
    --no-interaction
```

The default is read-only. Commit only the exact reconciliation IDs returned by
an eligible inspection. If it returns `status=rejected` and
`No authoritative missing disbursement postings explain the Treasury deficit.`,
stop: there is no evidence-backed automatic repair. Do not invent IDs, seed a
new opening balance, rerun opening capitalization, or write off the difference
through the installer. Reconcile the provider statement against the append-only
Treasury operations and use an approved accounting correction workflow.

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

## Turnkey Lifecycle Verification

The installer should leave the host in a state where onboarding readiness can be checked through the lifecycle runtime, not only through static config inspection.

Use the `turnkey-onboarding` group for a fast, credential-safe host smoke check:

```bash
php artisan xchange:lifecycle:run-group turnkey-onboarding --no-claim --timeout=1 --poll=1 --max-polls=1
```

This group currently runs:

- `turnkey_mobile_boot`
- `turnkey_bank_onboarding_required`
- `turnkey_basic_cash_mobile`

The first two scenarios use the `turnkey_onboarding` runner. They do not generate vouchers, do not attempt provider disbursement, and do not require NetBank, Paynamics, SMS, or OTP credentials. They verify that the installed host can resolve the mobile-first auth surface, issuer mobile channel, provider topology, and onboarding gateway boundary.

The `turnkey_basic_cash_mobile` scenario keeps the standard voucher lifecycle connected to the same mobile-first defaults. In the group command above, `--no-claim` prevents a real claim/disbursement attempt.

For JSON diagnostics, run the individual onboarding scenarios:

```bash
php artisan xchange:lifecycle:run turnkey_mobile_boot --prepare --json
php artisan xchange:lifecycle:run turnkey_bank_onboarding_required --json
```

Expected checks for `turnkey_mobile_boot`:

- mobile-first auth is enabled
- Fortify username is `mobile`
- lifecycle issuer has a mobile channel
- provider topology resolves
- issuer onboarding gateway accepts or safely falls back

Expected checks for `turnkey_bank_onboarding_required`:

- mobile-first auth is enabled
- lifecycle issuer has a mobile channel
- provider topology resolves
- bank onboarding requirement is handled or safely falls back

If `3neti/onboarding` is installed and registered, these scenarios should exercise the real onboarding gateway. If it is absent or not bound in the host container, the scenarios should still pass through the explicit fallback response so that package installation can be validated without external credentials.

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
php artisan xchange:lifecycle:run-group turnkey-onboarding --no-claim --timeout=1 --poll=1 --max-polls=1
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
php artisan xchange:lifecycle:run-group turnkey-onboarding --no-claim --timeout=1 --poll=1 --max-polls=1
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
