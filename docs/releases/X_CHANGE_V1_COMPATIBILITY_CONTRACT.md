# x-change v1 Compatibility Contract

## Purpose

This document defines what `3neti/x-change` promises for the `1.x` release line.

The goal is to let consuming applications such as x-PayOut depend on `3neti/x-change:^1.0` without pinning every beta tag, while keeping financial, onboarding, Pay Code, and deployment behavior predictable.

## Versioning rule

After `v1.0.0`, x-change follows semantic versioning:

| Release type | Meaning |
|---|---|
| `1.0.x` | Backward-compatible fix, documentation, test, or operational hardening |
| `1.x.0` | Backward-compatible feature or additive contract |
| `2.0.0` | Breaking change to a public contract listed in this document |

Consumers should normally require:

```json
"3neti/x-change": "^1.0"
```

During the release-candidate gate, consumers may pin the RC exactly:

```json
"3neti/x-change": "1.0.0-rc.1"
```

## Public host contract

x-change is a package-first platform. Host applications are expected to be thin Laravel applications that install, publish, and configure x-change.

The `1.x` line keeps these host-facing contracts stable:

- package service provider registration;
- publish/install/build catalog behavior;
- public claim routes under `/x/claim`;
- payable routes under `/x/pay`;
- Cockpit routes under `/x/cockpit`;
- package-owned Vue/Inertia assets published through x-change commands;
- commissioning manifests;
- doctor/readiness commands;
- Treasury/provider readiness gates.

Host apps should not edit package-owned files in `vendor/` or generated host projections as durable source.

## Pay Code and voucher instruction contract

The following Pay Code instruction concepts are stable for `1.x`:

- voucher instruction payloads remain the source of intent;
- `cash.amount` remains the source amount/value container for disbursable behavior;
- `target_amount` remains the collection target for payable/collectible behavior;
- settlement may carry both `cash.amount` and `target_amount` as independent values;
- generated Pay Codes expose enough read-model data for cockpit presentation without requiring frontend business inference;
- payable Pay Codes use `/x/pay/{code}` for payer payment experiences;
- disbursable and onboarding invitations use `/x/claim/{code}` for claim experiences.

Breaking examples requiring `2.0.0`:

- renaming `target_amount`;
- collapsing settlement `cash.amount` and `target_amount` into one field;
- changing the canonical meaning of payable vs disbursable;
- removing `/x/claim/{code}` or `/x/pay/{code}` behavior without a compatibility layer.

## Flow type contract

Flow type remains explicit instruction metadata.

The stable flow families are:

| Flow | Meaning |
|---|---|
| `disbursable` | Pre-funded value claimed or withdrawn by a recipient |
| `payable` / `collectible` | Payer-facing collection request where confirmed payment credits the intended account |
| `settlement` | Bilateral or policy-gated value movement where source and target values may coexist |
| `invitation` / onboarding flavored instructions | Account or workspace onboarding through the Pay Code claim surface |

x-change may add aliases or presentation labels in `1.x`, but must not silently change the execution meaning of existing flow values.

## Claim, X-Ray, and Form Flow contract

The public claim experience remains voucher-mediated:

```text
Pay Code entry
    -> X-Ray/context resolution
    -> Form Flow, when required
    -> execution engine
    -> success/completion context
```

For `1.x`:

- X-Ray remains the pre-claim source for what the user is accepting, paying, or redeeming;
- form-flow UI may consume x-change metadata for title, review context, and CTA labels;
- OTP, KYC, location, selfie, and signature handlers remain pluggable through Form Flow;
- x-change-specific Form Flow presentation must be activated by explicit metadata, not route guessing;
- successful onboarding may route the authenticated user into the appropriate Cockpit workspace.

Breaking examples requiring `2.0.0`:

- bypassing X-Ray for claim context where it currently governs presentation;
- requiring host apps to replace Form Flow manager contracts to keep existing x-change claims working;
- changing onboarding success routing without a compatibility/default path.

## Treasury and Client Funds contract

The `1.x` financial model preserves these semantics:

- provider inventory and account/client positions remain reconciled through Treasury;
- Client Funds represent the account owner's usable value for Pay Code instructions;
- Pay Code Reserve represents value reserved for issued Pay Codes before claim/consumption;
- funded onboarding invitations move reserved value into the onboarded account's Client Funds on successful claim;
- provider liquidity readiness participates in issuance capacity;
- production opening capitalization is explicit operator authority, not a package default.

Breaking examples requiring `2.0.0`:

- removing Pay Code Reserve accounting;
- treating NetBank opening inventory as ordinary user wallet balance without Treasury attribution;
- making production capitalization implicit;
- changing Client Funds to mean a provider balance rather than a normalized account position.

## NetBank and provider topology contract

For `1.x`, the NetBank-backed deployment profile remains a supported provider topology.

Stable expectations:

- provider configuration is supplied through environment/secrets;
- malformed explicit provider overrides should fail closed;
- the default NetBank provider class may be used by omitting unnecessary overrides;
- provider-liquidity snapshots are required where issuance capacity depends on provider backing;
- provider calls that move money remain protected by explicit commands, idempotency, and readiness gates.

## Commissioning and doctor contract

The following command surfaces are stable for `1.x`:

```bash
php artisan x-change:doctor --pre-commission --strict
php artisan x-change:doctor --strict
composer x-payout:bootstrap -- --manifest=commissioning/default.yaml --skip-build --no-interaction
```

The exact set of doctor checks may grow in `1.x` if checks are additive and fail with actionable messages. Existing successful deployment paths must not be broken without a compatibility note and migration path.

Commissioning manifests remain YAML files that can describe:

- system/account setup;
- NetBank-backed provider profile;
- onboarding invitation roles;
- funded invitation amounts;
- authorization and funding-source references;
- expected post-commissioning readiness posture.

Breaking examples requiring `2.0.0`:

- removing `--pre-commission` or `--strict`;
- removing `--skip-build` from the x-PayOut bootstrap path while Cloud still relies on prebuilt assets;
- changing the default manifest shape so existing x-PayOut commissioning files no longer parse.

## Cockpit and read-model contract

Cockpit may continue to evolve visually in `1.x`, but these contracts remain stable:

- Cockpit pages remain available under `/x/cockpit`;
- balances expose Client Funds, Outstanding Pay Codes, and issuance capacity semantics;
- Pay Codes read models identify flow type and amount presentation;
- funding pages surface account funding, suspense, recovery, and reserve posture;
- role-specific onboarding routes can land users in their intended workspace.

Visual layout changes are minor-compatible as long as they do not remove existing capabilities, public route assumptions, or required data fields.

## Partner API contract

The `1.x` Partner API direction is:

- bearer-token/OAuth identity identifies the caller;
- voucher instructions carry the Pay Code intent;
- payable/collectible generation should not require clients to submit internal provider credentials;
- mandate/scopes govern what a partner may issue, estimate, read, or pay.

Breaking examples requiring `2.0.0`:

- requiring external clients to submit NetBank credentials for ordinary payable Pay Code generation;
- removing existing estimate/issue/read/pay scopes without replacement;
- changing successful response payloads in a way that breaks current BPLS-style integrations.

## Deployment contract

For the current x-PayOut beta deployment path:

- x-PayOut ships prebuilt assets;
- Laravel Cloud should not run npm/Vite for that beta path;
- Cloud deployment is PHP-only;
- commissioning uses `--skip-build`;
- strict doctor must pass before invitations are considered safe to claim.

This path is documented in:

```text
docs/deployment/X_PAYOUT_BETA_TURNKEY_DEPLOYMENT_RUNBOOK.md
```

## Dependency policy for v1

x-change `1.x` may depend on upstream `3neti/*` packages that are still tagged with beta constraints where those packages have not yet majoralized.

That is acceptable for `v1.0.0-rc.1` only if:

- the x-change host-facing contract is stable;
- the dependency version ranges are intentional and documented;
- cleanroom x-PayOut installation remains green;
- no known upstream beta dependency is actively breaking the contract described here.

Before final `v1.0.0`, the team should either majoralize the most critical upstream packages or explicitly accept the remaining beta dependencies as monitored internals of the `x-change:^1.0` platform contract.

## Compatibility promise

The promise of `x-change:^1.0` is not that the UI stops improving.

The promise is that a consuming Laravel host can update within the `1.x` line and keep the same core meanings:

```text
voucher instructions define intent
flow type defines capability
X-Ray explains the claim/payment
Form Flow collects required evidence
the execution engine moves value
Treasury proves backing and reserves
Cockpit presents the account posture
doctor gates decide readiness
```
