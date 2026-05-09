# Strategy: Default UI and Installable Dashboard for `3neti/x-change`

## 1. Problem to Be Solved

`x-change` is becoming a reusable Laravel package, but a newly installed host app still needs a usable starting point.

At minimum, after installation, the package should provide:

1. a dashboard for the issuer/operator;
2. a voucher / Pay Code generation interface;
3. basic operational statistics;
4. public endpoints for claim / redeem flows;
5. extension points so licensees can customize branding, auth, layout, routes, and business integrations without forking the package.

The problem is not merely “how do we publish Vue files?”

The real problem is:

> How can `x-change` ship a useful default product experience while remaining API-first, contract-driven, and licensee-extensible?

---

## 2. Rationale

`x-change` should not require every licensee to reconstruct the same baseline dashboard and redemption endpoints from scratch.

A package that only exposes APIs may be architecturally pure, but it creates friction for demos, pilots, internal adoption, bank proof-of-concepts, and licensee onboarding.

However, a package that hardcodes its UI too deeply becomes difficult to license, brand, customize, or integrate into existing Laravel applications.

Therefore, the correct posture is:

> `x-change` should ship with a default UI, but the default UI must be treated as a replaceable reference implementation over stable APIs and contracts.

This aligns with the existing architectural direction: the package owns orchestration, workflows, routes, controllers, jobs, events, config, and service-container assembly, while host apps may override user/auth, branding, infrastructure, and environment-specific behavior.

---

## 3. Hypothesis

If `x-change` provides an install command such as:

```bash
php artisan x-change:install
```

then a licensee should immediately receive a working baseline product surface:

```text
/x/dashboard
/x/pay-codes/create
/x/pay-codes
/x/pay-codes/{code}
/x/redeem or /x/pay-codes/{code}/claim/start
/api/x/v1/...
```

The package should become usable on day one while still allowing the licensee to override:

- layout;
- branding;
- terminology;
- dashboard metric providers;
- voucher generation defaults;
- authentication middleware;
- user and merchant resolution;
- wallet resolution;
- success redirects;
- notification behavior;
- frontend components;
- routes.

The install experience should therefore produce a “working default,” not a “locked product.”

---

## 4. Observations

### Observation A — `x-change` is API-first, but not UI-hostile

The current compass defines `x-change` as an API-first financial workflow platform with onboarding, KYC, wallet orchestration, pricing, Pay Code issuance, claim, disbursement, and optional web controllers.

This means UI should not contain business logic. UI should consume package APIs and DTOs.

### Observation B — The voucher lifecycle is already clear

The lifecycle is already organized around:

```text
Onboarding
Issuance
Claim preparation
Claim completion
Claim submit
Settlement / disbursement
```

The guide states that `claim/submit` is the canonical public execution endpoint and that the package should own defaults while host apps may override services.

This gives the dashboard a natural scope: it should operate the lifecycle, not invent a separate workflow.

### Observation C — The redeem/disburse flow already has a proven UI pattern

The existing `/disburse` flow already has code entry, form-flow steps, completion, redemption, success page, and redirect behavior. The YAML driver controls which UX steps appear and in what order.

This means the default redeem UI should not be redesigned from zero. It should be adopted, packaged, and normalized.

### Observation D — Form-flow already supports dynamic redemption requirements

The existing redemption UI can include splash, wallet, KYC, bio fields, OTP, location, selfie, and signature depending on voucher instructions.

Therefore, `x-change` does not need a hardcoded redeem wizard. It needs to expose and install the form-flow-driven experience.

### Observation E — Extensibility is already a known package requirement

The packaging strategy warns against direct `App\...` dependencies and recommends contracts such as user resolver, wallet owner resolver, merchant context resolver, audit logger, feedback sender, success redirect resolver, system wallet resolver, and current merchant resolver.

This same principle must apply to the UI.

---

## 5. Analysis

The dashboard should not be treated as “admin scaffolding.”

It should be treated as the minimum operator console for the Pay Code lifecycle.

The minimum useful dashboard has only three jobs:

1. show whether the system is alive;
2. allow voucher / Pay Code generation;
3. show basic statistics about issued, claimed, pending, failed, and disbursed Pay Codes.

Everything else can be deferred.

The dashboard should not directly know how to issue vouchers. It should call the same action/API used by external clients.

The redeem endpoints should not be “dashboard features.” They are public product endpoints. The dashboard may link to them, but redemption must remain independent and public-facing.

The UI therefore has two surfaces:

```text
Operator UI
- dashboard
- generate Pay Code
- voucher list
- statistics

Claimant UI
- enter Pay Code
- complete claim flow
- submit claim
- success / redirect
```

This separation is important because the operator is authenticated, while the claimant/redeemer may not be.

---

## 6. Strategic Solution

`x-change` should provide an installable default UI layer composed of four parts:

### A. Default dashboard

The dashboard should show:

- total Pay Codes generated;
- total claimed;
- total pending;
- total failed;
- total amount issued;
- total amount disbursed;
- recent Pay Codes;
- recent claim attempts;
- provider / disbursement status summary if available.

The dashboard should get this data from a `DashboardStatsProviderContract`, not from raw model queries hardcoded in Vue or controllers.

### B. Default Pay Code generator

The generator should allow the issuer/operator to create a basic voucher with:

- amount;
- currency;
- quantity;
- expiration;
- required fields;
- optional validation gates;
- rider message;
- rider redirect URL;
- basic settlement/disbursement rail options.

The generator should call the package’s issuance action/API.

It should not duplicate voucher creation logic.

### C. Default redeem / claim endpoints

The package should install public endpoints for:

```text
claim/start
claim/complete
claim/submit
success/redirect
```

Legacy-compatible `/redeem` or `/disburse` endpoints may exist, but the canonical public vocabulary should remain claim-first.

This matches the lifecycle guide’s principle that public API is unified, while internal execution may choose redeem or withdraw.

### D. Publishable and overrideable frontend

The UI should be shipped inside the package, but publishable into the host app.

The host app should be able to override:

```text
resources/js/vendor/x-change/pages
resources/js/vendor/x-change/components
resources/js/vendor/x-change/composables
resources/js/vendor/x-change/layouts
resources/views/vendor/x-change
config/x-change.php
```

This gives licensees a working default and a clean escape hatch.

---

## 7. Install Experience Hypothesis

The install command should make the host app feel immediately product-ready.

Conceptually:

```bash
composer require 3neti/x-change
php artisan x-change:install
npm install
npm run build
php artisan migrate
```

Afterward:

```text
/x/dashboard
```

should work.

The command should not merely publish files. It should establish the package’s operating surface.

It should install:

- config;
- routes;
- migrations;
- default dashboard UI;
- default redeem UI;
- form-flow driver;
- default service bindings;
- optional frontend assets;
- default navigation entries;
- basic terminology;
- install notes for optional handlers like KYC, OTP, location, selfie, and signature.

---

## 8. Scientific Conclusion

The working theory is:

> A licenseable financial workflow package becomes easier to adopt when it provides a complete default operating surface, but it remains licenseable only if the operating surface is driven by contracts, config, APIs, and publishable UI.

Therefore:

> `x-change` should ship with a default dashboard and redeem flow, but the dashboard must be a reference client of the package APIs, not the source of business logic.

---

## 9. Design Doctrine for the AI Agent

The AI agent should understand these rules before making an implementation plan:

### Rule 1 — API first, UI second

Every dashboard action must map to an action/service/API path.

Do not put issuance, redemption, statistics, or disbursement rules directly inside Vue components.

### Rule 2 — Dashboard is default, not mandatory

The package should work headlessly.

The dashboard is installed for convenience and adoption.

### Rule 3 — Claimant UI and operator UI are separate

Operator dashboard requires auth.

Redeem/claim endpoints must support public claimant access where appropriate.

### Rule 4 — Use existing redeem-x UI as source material

Do not invent a new redemption UX if the form-flow-driven `/disburse` experience already solves it.

Adopt it, normalize it, and package it.

### Rule 5 — Licensees override through contracts and published assets

No hard dependency on `App\Models\User`, host layouts, host policies, host notifications, or host-specific middleware.

### Rule 6 — Statistics come from providers

Dashboard statistics should come from a contract such as:

```php
DashboardStatsProviderContract
```

not from hardcoded database assumptions.

### Rule 7 — Terminology is externalized

The UI should say “Pay Code,” “Claim,” “Cash Out,” or licensee-specific terms through config/lang, while internal code can keep precise domain terms.

### Rule 8 — Installation should create a working baseline

The success condition is not “files were published.”

The success condition is:

> after `php artisan x-change:install`, a developer can open the dashboard, generate a Pay Code, and redeem it through installed endpoints.

---

## 10. Minimum Product Surface

The AI agent should target this minimum product surface:

```text
Authenticated Operator Surface
- /x/dashboard
- /x/pay-codes
- /x/pay-codes/create
- /x/pay-codes/{code}

Public Claimant Surface
- /x/claim
- /x/pay-codes/{code}/claim/start
- /x/pay-codes/{code}/claim/complete
- /x/pay-codes/{code}/claim/submit
- /x/pay-codes/{code}/success
- /x/pay-codes/{code}/redirect

API Surface
- POST /api/x/v1/pay-codes/estimate
- POST /api/x/v1/pay-codes
- GET  /api/x/v1/pay-codes
- GET  /api/x/v1/pay-codes/{code}
- POST /api/x/v1/pay-codes/{code}/claim/start
- POST /api/x/v1/pay-codes/{code}/claim/complete
- POST /api/x/v1/pay-codes/{code}/claim/submit
- GET  /api/x/v1/dashboard/stats
```

---

## 11. Final Strategic Statement

`x-change` should become installable as a complete Pay Code operating kit.

At installation, it should provide:

- a default dashboard;
- a default Pay Code generator;
- basic statistics;
- public claim/redeem endpoints;
- form-flow-driven redemption;
- API-first lifecycle contracts;
- overrideable UI and service bindings.

But it must avoid becoming a rigid application.

The correct end state is:

> `x-change` is a package that installs like a product, behaves like a platform, and customizes like a framework.
