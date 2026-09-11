# x-change v1.0.0 Release Decision

## Decision

Proceed with `3neti/x-change v1.0.0`.

The `v1.0.0-rc.1` release candidate was consumed by x-PayOut in a fresh Laravel Cloud cleanroom deployment and passed the required funded onboarding proof.

## Release line

| Package | Version |
|---|---|
| `3neti/x-change` | `v1.0.0` |
| RC proven | `v1.0.0-rc.1` |
| Consuming host | `3neti/x-payout v1.0.0-beta.40` |

After this release, consuming applications may target:

```json
"3neti/x-change": "^1.0"
```

## Compatibility contract

The v1 compatibility contract is recorded in:

```text
docs/releases/X_CHANGE_V1_COMPATIBILITY_CONTRACT.md
```

The release promise is that host applications can update within the `1.x` line while preserving the core platform meanings:

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

## RC readiness evidence

The RC readiness audit is recorded in:

```text
docs/releases/X_CHANGE_V1_RC1_READINESS_AUDIT.md
```

Package verification completed before RC tagging:

| Check | Result |
|---|---|
| `composer validate --strict --no-interaction` | Passed |
| Commissioning manifest tests | Passed: 14 tests, 148 assertions |
| Account funding / claim / doctor tests | Passed: 42 tests, 230 assertions |
| `git diff --check` | Passed |

## x-PayOut RC consumption proof

A fresh x-PayOut Laravel Cloud app was deployed from zero using:

- `3neti/x-payout v1.0.0-beta.40`;
- locked `3neti/x-change v1.0.0-rc.1`;
- prebuilt frontend assets;
- PHP-only Cloud deployment;
- no npm, Vite, or Vite Plus execution in Cloud;
- `--skip-build` commissioning.

Deployment:

| Item | Result |
|---|---|
| Cloud URL | `https://x-payout-cleanroom-20260911-production-niqeul.laravel.cloud` |
| Application ID | `app-a2b776ca-f79f-4998-be7e-1e8364587fc0` |
| Environment ID | `env-a2b776cd-12bf-407f-8aa2-8d836cebd221` |
| x-PayOut | `v1.0.0-beta.40` |
| x-change | `v1.0.0-rc.1` |
| Deployment | Succeeded |
| Frontend artifact | `public/build/manifest.json` confirmed |
| Cloud frontend build | Not run |

Health gates:

| Gate | Result |
|---|---|
| Pre-commission doctor | Passed |
| Commissioning with `--skip-build` | Passed |
| Final strict doctor | Passed |
| Post-claim strict doctor | Passed |
| Issuance/provider-liquidity guard | Ready |

Financial posture:

| Item | Amount |
|---|---:|
| NetBank opening inventory | `₱5,029.43` |
| Account Funding Reserve after commissioning | `₱4,829.43` |
| Pay Code Reserve before claims | `₱200.00` |

Onboarding proof:

| Role | Code | Result |
|---|---|---|
| Maker | `MAKE-WH7Z` | Claimed; success page showed Maker account ready; `₱100.00 available for instructions`; landed at `/x/cockpit/quick-generate` |
| Checker | `CHKR-9FDD` | Claimed; success page showed Checker account ready; `₱100.00 available for instructions`; landed at `/x/cockpit/overview` |

Database verification confirmed:

- both invitation records have redemption timestamps;
- the Maker and Checker users were created;
- role-specific identities persisted correctly.

The browser reused one authenticated session between the two claim flows. That was accepted as a browser-test artifact because the persisted identities, success pages, role-specific routes, and redemption records were correct.

## Profile correction noted during RC proof

The first cleanroom attempt correctly failed closed at pre-commission doctor because the Cloud environment used:

```text
XCHANGE_DEPLOYMENT_PROFILE=netbank-production
```

`netbank-production` is not a recognized x-change deployment profile.

The corrected configuration is:

```text
XCHANGE_DEPLOYMENT_PROFILE=netbank
XCHANGE_RUNTIME_TIER=production
```

This confirms the readiness gate blocked an invalid profile before commissioning, invitation issuance, or reserve movement.

## Remaining monitored risks

The final `v1.0.0` release accepts these as monitored `1.x` risks, not blockers:

- some upstream `3neti/*` dependencies still use beta constraints;
- Cockpit UI will continue to evolve visually;
- x-PayOut remains beta while consuming the stable platform package;
- Cloud-side frontend builds remain outside the current x-PayOut beta path.

These are acceptable because the host-facing x-change contract is stable and cleanroom x-PayOut consumption has been proven.

## Go / no-go

Go.

Tag:

```text
v1.0.0
```

Post-release host guidance:

1. Update x-PayOut from exact RC pin to:

   ```json
   "3neti/x-change": "^1.0"
   ```

2. Run one more local/package verification.
3. Release a follow-up x-PayOut beta consuming `^1.0`.
4. Keep the Cloud cleanroom report as the public beta deployment baseline.
