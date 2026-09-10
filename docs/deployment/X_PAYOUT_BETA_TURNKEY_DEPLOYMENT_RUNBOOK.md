# x-PayOut Beta Turnkey Deployment Runbook

## Purpose

This is the blessed beta deployment path for a fresh x-PayOut Laravel Cloud instance.

Use this runbook when the goal is to create a new x-PayOut app from zero, commission it with NetBank-backed liquidity, and produce funded Maker and Checker onboarding invitations.

## Current blessed package set

| Package | Version |
|---|---|
| `3neti/x-payout` | `v1.0.0-beta.39` |
| `3neti/x-change` | `v1.0.0-beta.347` |

The matching x-PayOut release ships prebuilt frontend assets in `public/build`.

## Non-negotiable deployment rule

Do not run npm, Vite, or Vite Plus in Laravel Cloud for this beta path.

Cloud must deploy the prebuilt release artifact. The Cloud build should be PHP-only. Commissioning should use `--skip-build`.

This rule exists because Laravel Cloud repeatedly hid the actionable frontend build exception while local and package-level builds were already proven. The beta stabilization path is therefore: build assets before release, ship assets in the release artifact, and keep Cloud focused on PHP install, migration, runtime readiness, and commissioning.

## Required Cloud posture

Before commissioning, the Cloud environment must have:

- application secrets and environment variables applied through Laravel Cloud configuration, not shell-sourced `.env` files;
- NetBank provider configuration;
- SMS/OTP configuration;
- KYC configuration, if required by the deployed readiness profile;
- Map/location configuration, if enabled in the UI;
- durable private claim-evidence storage;
- queue, cache, and session settings suitable for production;
- explicit production treasury opening capitalization authorization for the intended NetBank connection.

For the current NetBank-backed commissioning path, the production capitalization authorization is intentional and must be explicit:

```text
XCHANGE_TREASURY_OPENING_CAPITALIZATION_ALLOW_PRODUCTION=true
XCHANGE_TREASURY_OPENING_CAPITALIZATION_ALLOWED_CONNECTIONS=netbank-primary
```

Do not make those package defaults. They are operator authority for opening reserve capitalization.

## Cloud build and deploy shape

Use a PHP-only build path. Do not invoke `npm install`, `npm ci`, `npm run build`, `vite`, or `vp` in Cloud for this release line.

Expected build shape:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
```

Expected deploy command:

```bash
php artisan migrate --force
```

The release artifact must already contain:

```text
public/build/manifest.json
```

## Commissioning sequence

After deployment is active and migrations have run:

```bash
php artisan x-change:doctor --pre-commission --strict
composer x-payout:bootstrap -- --manifest=commissioning/default.yaml --skip-build --no-interaction
php artisan x-change:doctor --strict
```

Commissioning must stop if the pre-commission doctor fails.

Invitations must not be claimed unless the final strict doctor is green.

## Expected funded onboarding behavior

The current default commissioning manifest creates:

- one Maker onboarding invitation funded with `₱100.00`;
- one Checker onboarding invitation funded with `₱100.00`.

The expected reserve movement is:

```text
Opening NetBank provider inventory
        -> Account Funding Reserve
        -> Pay Code Reserve: ₱200.00
        -> Maker Client Funds: ₱100.00 after Maker claim
        -> Checker Client Funds: ₱100.00 after Checker claim
```

Before claims, the Pay Code Reserve should contain the two funded invitation allocations.

After both claims, the `₱200.00` should move out of Pay Code Reserve and into the onboarded users' Client Funds.

## Cleanroom proof: 2026-09-10

A fresh Laravel Cloud app was deleted and recreated from zero, then commissioned successfully.

| Item | Result |
|---|---|
| Cloud URL | `https://x-payout-cleanroom-20260910-production-m5tfo0.laravel.cloud` |
| Application ID | `app-a2b6a2c4-55da-4ebe-a066-d444a2a21f93` |
| Environment ID | `env-a2b6a2c6-7471-4765-8261-fb21d2247dbc` |
| x-PayOut | `v1.0.0-beta.39` |
| x-change | `v1.0.0-beta.347` |
| Frontend build | Prebuilt assets used; no Cloud npm/Vite build |
| Pre-commission doctor | `24/24` passed |
| Final strict doctor | `31/31` passed |
| NetBank opening inventory | `₱5,029.43` |
| Account Funding Reserve after commissioning | `₱4,829.43` |
| Pay Code Reserve before claims | `₱200.00` |
| Issuance/provider-liquidity guard | Ready |

Generated invitations:

| Role | Code | Result |
|---|---|---|
| Maker | `MAKE-GB4P` | Claimed; landed at `/x/cockpit/quick-generate`; Client Funds `₱100.00` |
| Checker | `CHKR-CG93` | Claimed; landed at `/x/cockpit/overview`; Client Funds `₱100.00` |

Invariant confirmed:

```text
₱5,029.43 opening inventory - ₱200.00 funded invitations = ₱4,829.43 Account Funding Reserve
```

## Report format for future runs

Every cleanroom deployment report should include:

- Cloud URL;
- application ID;
- environment ID;
- x-PayOut version;
- x-change version;
- whether Cloud ran npm/Vite;
- pre-commission doctor result;
- final strict doctor result;
- NetBank opening inventory;
- Account Funding Reserve after commissioning;
- Pay Code Reserve before claims;
- issuance/provider-liquidity guard state;
- Maker claim URL;
- Checker claim URL;
- Maker post-claim workspace and Client Funds;
- Checker post-claim workspace and Client Funds.

## Failure handling

If deployment fails before commissioning:

- do not commission;
- do not manually create invitations;
- report the first failing command and the Cloud deployment ID.

If commissioning fails after partial mutation:

- do not claim invitations;
- report the generated codes, reserve state, and strict doctor result;
- prefer deleting the cleanroom app and starting over unless a documented recovery path exists.

If the final strict doctor fails:

- treat the instance as not operational;
- do not use onboarding invitations for public testing;
- fix the failed readiness gate first.

## Current conclusion

The beta turnkey path is reproducible when the release artifact includes prebuilt assets and Cloud commissioning uses `--skip-build`.

The blessed operational posture is:

```text
build assets before release
ship prebuilt public/build assets
deploy PHP-only in Laravel Cloud
run pre-commission doctor
commission with --skip-build
run final strict doctor
claim Maker and Checker invitations
verify Client Funds
```
