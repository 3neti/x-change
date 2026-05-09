# x-change:install — Resource Installation Map

**Version**: 1.0
**Last Updated**: 2026-05-09

This document maps every resource published by `php artisan x-change:install`, where it goes, which package owns it, and why the host app needs it.

---

## How Installation Works

```bash
php artisan x-change:install [--force] [--no-assets] [--no-migrate]
```

The command runs these steps in order:

1. **Publish x-change UI** (`--tag=x-change-ui`) — pages, components, layouts, composables, form-flow shared dependencies
2. **Publish branding assets** (`--tag=x-change-assets`) — logos, favicons
3. **Publish form-flow packages** (per provider) — configs, Vue pages for each handler
4. **Run migrations** — database tables for vouchers, wallets, claims, etc.

After install, run `npm install && npm run build` to compile the frontend.

---

## Resource Map

### x-change UI (`--tag=x-change-ui`)

Published by `XChangeServiceProvider`. These are the package-owned Vue source files.

#### Pages

| Source (package) | Destination (host) | Purpose |
|---|---|---|
| `resources/js/pages/x-change/Dashboard.vue` | `resources/js/pages/x-change/` | Operator dashboard with stats, quick actions, activity |
| `resources/js/pages/x-change/pay-codes/Index.vue` | same | Pay Code listing |
| `resources/js/pages/x-change/pay-codes/Create.vue` | same | Pay Code generation form with live pricing |
| `resources/js/pages/x-change/pay-codes/Show.vue` | same | Pay Code detail with instructions, claims, dates |
| `resources/js/pages/x-change/balances/Index.vue` | same | Balance monitoring and reconciliation status |
| `resources/js/pages/x-change/claim/Error.vue` | same | Claim error page (invalid/expired/redeemed) |
| `resources/js/pages/x-change/claim/Success.vue` | same | Post-claim success with rider message and redirect |

**Why**: Inertia requires Vue pages in the host app's `resources/js/pages/` at build time. The package owns the source; publishing copies them for Vite to compile.

#### Components

| Source | Destination | Purpose |
|---|---|---|
| `resources/js/components/x-change/StatCard.vue` | `resources/js/components/x-change/` | Dashboard stat card |
| `resources/js/components/x-change/QuickActions.vue` | same | Dashboard quick action links |
| `resources/js/components/x-change/RecentActivity.vue` | same | Dashboard recent activity table |
| `resources/js/components/x-change/BalanceWidget.vue` | same | Balance display card |
| `resources/js/components/x-change/ReconciliationStatusCard.vue` | same | Reconciliation status display |

**Why**: Reusable UI components consumed by the x-change pages.

#### Layouts

| Source | Destination | Purpose |
|---|---|---|
| `resources/js/layouts/x-change/XChangeLayout.vue` | `resources/js/layouts/x-change/` | Package-owned sidebar layout with Dashboard, Pay Codes, Balances navigation |

**Why**: Decouples x-change from the host app's `AppSidebar.vue`. The package owns its own navigation.

#### Composables

| Source | Destination | Purpose |
|---|---|---|
| `composables/useXChangeDashboardApi.ts` | `resources/js/composables/` | Dashboard stats/activity API client |
| `composables/usePayCodeApi.ts` | same | Pay Code estimate, generate, pricelist API client |
| `composables/usePayCodeForm.ts` | same | Pay Code form state, validation, debounced estimate |
| `composables/useXChangeRoutes.ts` | same | Centralized route definitions (eliminates hardcoded URLs) |

**Why**: Typed API clients and form state management. Designed for reuse in future PWA.

#### Form-Flow Shared Dependencies

These are frontend files required by form-flow's Vue pages but not yet published by form-flow's own packages. x-change bundles them temporarily until form-flow publishes them natively.

| Source (in x-change package) | Destination (host) | Origin | Purpose |
|---|---|---|---|
| `components/x-change-shared-financial/` | `resources/js/components/financial/` | redeem-x | Bank selector (`BankEMISelect`), country selector, settlement rail selector |
| `components/x-change-shared-phone-input/` | `resources/js/components/ui/phone-input/` | redeem-x | Phone number input with country code |
| `components/NumberInputWithKeypad.vue` | `resources/js/components/` | redeem-x | Numeric input with on-screen keypad (mobile UX) |
| `components/NumericKeypad.vue` | `resources/js/components/` | redeem-x | On-screen numeric keypad component |
| `composables/useFormFlowSummary.ts` | `resources/js/composables/` | redeem-x | Flattens form-flow collected data for Complete.vue summary |
| `composables/useTheme.ts` | `resources/js/composables/` | redeem-x | Theme management (dark/light/custom themes) |
| `layouts/PublicLayout.vue` | `resources/js/layouts/` | x-change | Minimal layout shell for public pages (no sidebar) |
| `config/` | `resources/js/config/` | redeem-x | Bank restrictions config (EMI limits, rail rules) |
| `data/` | `resources/js/data/` | redeem-x | Bank list data (`banks.ts`), country list (`countries.ts`) |
| `documents/` | `resources/documents/` | emi-netbank / money-issuer | `banks.json` (InstaPay bank registry), `zip_codes_list.json` (PH postal codes for address generation) |

**Why**: Form-flow's GenericForm.vue, Complete.vue, and handler pages import these components, composables, and data files. Without them, `npm run build` fails. These should eventually be published by the form-flow packages themselves.

---

### x-change Branding Assets (`--tag=x-change-assets`)

Published by `XChangeServiceProvider`.

| Source | Destination | Purpose |
|---|---|---|
| `resources/assets/images/logo-orange.png` | `public/vendor/x-change/images/` | Light mode logo |
| `resources/assets/images/logo-silver.png` | same | Dark mode logo |
| `resources/assets/images/logo-slate.png` | same | Slate variant logo |
| `resources/assets/images/logo.png` | same | Sketch logo |
| `resources/assets/favicon.ico` | `public/vendor/x-change/` | Favicon (ICO) |
| `resources/assets/favicon.png` | same | Favicon (PNG) |
| `resources/assets/favicon.svg` | same | Favicon (SVG) |
| `resources/assets/apple-touch-icon.png` | same | iOS home screen icon |

**Why**: Branding files served as static assets. Namespaced under `public/vendor/x-change/` to avoid collisions with host app assets. Configurable via `config('x-change.branding')` env vars for white-labeling.

---

### Form-Flow Package Assets (per provider)

Published by each form-flow service provider. The `x-change:install` command iterates over installed providers and publishes their assets.

#### Form-Flow Core (`FormFlowServiceProvider`)

| Source | Destination | Purpose |
|---|---|---|
| `config/form-flow.php` | `config/` | Form-flow configuration |
| `config/form-flow-drivers/` | `config/form-flow-drivers/` | YAML driver configs (x-change overrides callbacks) |
| `stubs/resources/js/pages/form-flow/core/` | `resources/js/pages/form-flow/core/` | Splash.vue, GenericForm.vue, Complete.vue, MissingHandler.vue |

#### KYC Handler (`KYCHandlerServiceProvider`)

| Source | Destination | Purpose |
|---|---|---|
| `config/kyc-handler.php` | `config/` | HyperVerge API configuration |
| `stubs/resources/js/pages/form-flow/kyc/` | `resources/js/pages/form-flow/kyc/` | KYCInitiatePage.vue, KYCStatusPage.vue |

#### Location Handler (`LocationHandlerServiceProvider`)

| Source | Destination | Purpose |
|---|---|---|
| `config/location-handler.php` | `config/` | OpenCage/Mapbox configuration |
| `stubs/resources/js/pages/form-flow/location/` | `resources/js/pages/form-flow/location/` | LocationCapturePage.vue |

#### OTP Handler (`OtpHandlerServiceProvider`)

| Source | Destination | Purpose |
|---|---|---|
| `config/otp-handler.php` | `config/` | EngageSpark SMS configuration |
| `stubs/resources/js/pages/form-flow/otp/` | `resources/js/pages/form-flow/otp/` | OtpCapturePage.vue |

#### Selfie Handler (`SelfieHandlerServiceProvider`)

| Source | Destination | Purpose |
|---|---|---|
| `config/selfie-handler.php` | `config/` | Camera configuration |
| `stubs/resources/js/pages/form-flow/selfie/` | `resources/js/pages/form-flow/selfie/` | SelfieCapturePage.vue |

#### Signature Handler (`SignatureHandlerServiceProvider`)

| Source | Destination | Purpose |
|---|---|---|
| `config/signature-handler.php` | `config/` | Signature pad configuration |
| `stubs/resources/js/pages/form-flow/signature/` | `resources/js/pages/form-flow/signature/` | SignatureCapturePage.vue |

**Why**: Form-flow uses Inertia pages for each step handler. These must exist in the host app for Vite to compile them.

---

### Static HTML Files (manual)

These are NOT published by any package and must be copied manually if needed:

| File | Destination | Purpose |
|---|---|---|
| `form-flow-demo.html` | `public/` | Form-flow testing/demo page (development only) |
| `form-flow-complete.html` | `public/` | Form-flow completion test page (development only) |

**Why**: Development utilities. Not required for production.

---

## npm Dependencies

The following npm packages are required by the published frontend files:

| Package | Required by | Purpose |
|---|---|---|
| `marked` | `claim/Success.vue` | Render rider messages as markdown |
| `dompurify` | `form-flow/core/Splash.vue` | Sanitize HTML content |
| `axios` | `form-flow/location/` | HTTP client for geocoding API |

These must be in the host app's `package.json`. Install with:
```bash
npm install marked dompurify axios
```

---

## Architectural Notes

### Ownership Model

```
x-change package ─── owns ──→ pages, components, layouts, composables, branding
                  └── bundles (temp) ──→ form-flow shared dependencies

form-flow package ─── owns ──→ core pages (Splash, GenericForm, Complete)
form-handler-* ───── own ───→ handler pages (KYC, OTP, location, selfie, signature)
                  └── do NOT yet publish ──→ shared frontend dependencies
```

### Why x-change Bundles Form-Flow Dependencies

Form-flow's Vue pages (`GenericForm.vue`, `Complete.vue`, handler pages) import components that currently only exist in the redeem-x host app:
- Financial components (bank selector, settlement rail)
- Phone input component
- Numeric keypad
- Theme composable
- Form-flow summary composable
- Bank data files

Until the form-flow packages publish these themselves, x-change includes them in its `x-change-ui` publish tag. This is documented with `// Form-flow shared dependencies (until form-flow packages publish these natively)` in the service provider.

### Future: Form-Flow Self-Publishing

When form-flow packages are updated to publish their own frontend dependencies:
1. Remove the shared dependency entries from `XChangeServiceProvider::bootConfig()`
2. Remove the corresponding files from `packages/x-change/resources/js/components/`, `composables/`, `data/`, etc.
3. Update `x-change:install` if form-flow provides its own install command
4. Update this document
