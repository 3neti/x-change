# x-change v1.0.0-rc.1 Readiness Audit

## Purpose

This audit decides whether `3neti/x-change v1.0.0-beta.348` is ready to become `v1.0.0-rc.1`.

The release-candidate gate is not the final stable release. It is the freeze point where the team proves the v1 compatibility contract through cleanroom host consumption.

## Candidate baseline

| Item | Baseline |
|---|---|
| Package | `3neti/x-change` |
| Current beta | `v1.0.0-beta.348` |
| Latest package commit reviewed | `4595dd45` |
| Host proof target | `3neti/x-payout` |
| Known green host release | `3neti/x-payout v1.0.0-beta.39` |
| Cleanroom proof date | 2026-09-10 |

## Audit summary

Recommendation: proceed to `v1.0.0-rc.1` as a contract freeze candidate after the focused package checks pass.

This recommendation is cautious, not celebratory confetti. The main x-change host-facing contract is now stable enough to freeze for RC testing, but the final `v1.0.0` should wait until x-PayOut consumes the RC from scratch and reproduces the cleanroom result.

## Evidence already established

### x-PayOut cleanroom proof

A fresh Laravel Cloud application was created from zero and successfully commissioned using:

- `3neti/x-payout v1.0.0-beta.39`;
- `3neti/x-change v1.0.0-beta.347`;
- prebuilt frontend assets;
- PHP-only Cloud deployment;
- `--skip-build` commissioning;
- NetBank-backed Treasury opening capitalization;
- funded Maker and Checker onboarding invitations.

Final result:

- pre-commission doctor: `24/24`;
- final strict doctor: `31/31`;
- Maker received `₱100.00` Client Funds;
- Checker received `₱100.00` Client Funds;
- role-specific cockpit routing worked.

`v1.0.0-beta.348` added documentation only, so it should preserve the beta347 runtime behavior.

### Current blessed deployment path

The accepted x-PayOut beta Cloud path is now documented in:

```text
docs/deployment/X_PAYOUT_BETA_TURNKEY_DEPLOYMENT_RUNBOOK.md
```

The important invariant is:

```text
prebuilt assets
    -> PHP-only Laravel Cloud deployment
    -> pre-commission doctor
    -> commissioning with --skip-build
    -> final strict doctor
    -> funded Maker/Checker onboarding claims
```

## Contract area review

### Pay Code issuance DTOs

Status: acceptable for RC.

Current rules are stable enough for host consumption:

- voucher instructions carry intent;
- payable/collectible use `target_amount`;
- disbursable uses `cash.amount`;
- settlement may carry both `cash.amount` and `target_amount`.

Risk: medium.

Reason: there has been recent movement around payable and settlement amount semantics. The current boundary is now well-understood, but this must be protected in RC by tests and x-PayOut cleanroom verification.

### Flow type vocabulary

Status: acceptable for RC with wording caution.

Stable concepts:

- disbursable;
- payable/collectible;
- settlement;
- onboarding/invitation flavored Pay Codes.

Risk: medium.

Reason: user-facing spelling and labels have evolved. The execution meaning is stable enough; the RC contract should protect behavior more strongly than copy.

### Claim, X-Ray, and Form Flow

Status: acceptable for RC.

Recent onboarding polish established the intended direction:

- X-Ray/context drives what the claim surface knows;
- Form Flow consumes explicit x-change metadata for titles, CTA copy, and review context;
- onboarding invitations can avoid generic “Claim Pay Code” language.

Risk: medium.

Reason: this area has active UX polish. The RC should allow visual refinement but freeze the metadata-driven integration contract.

### Treasury and Client Funds

Status: strong enough for RC.

Evidence:

- funded onboarding invitations reserve value from Treasury-backed Account Funding Reserve;
- claim completion moves reserved value into the onboarding user's Client Funds;
- Maker and Checker both received usable `₱100.00` Client Funds in cleanroom Cloud verification;
- issuance capacity reflected Client Funds after provider-liquidity readiness was fixed.

Risk: low-to-medium.

Reason: real money semantics require continued caution, but the model is now coherent and observed end to end.

### NetBank provider topology

Status: acceptable for RC.

Evidence:

- malformed provider override failed closed and was diagnosed;
- omitting unnecessary `XCHANGE_PAYOUT_PROVIDER` override allows default NetBank provider resolution;
- NetBank opening inventory capitalization is explicit and connection-restricted in production.

Risk: medium.

Reason: environment setup is still sharp-edged. This is acceptable if the runbook remains the canonical deployment path and doctor checks stay strict.

### Commissioning commands and manifests

Status: acceptable for RC.

Stable path:

```bash
php artisan x-change:doctor --pre-commission --strict
composer x-payout:bootstrap -- --manifest=commissioning/default.yaml --skip-build --no-interaction
php artisan x-change:doctor --strict
```

Risk: low-to-medium.

Reason: the `--force` mismatch has been eliminated from the blessed path. `--skip-build` is now essential for x-PayOut Cloud beta deployment and should be treated as part of the contract.

### Cockpit read models and pages

Status: acceptable for RC.

Evidence:

- Pay Codes amount presentation has been hardened for flow type;
- Funding mobile balance/carousel work was merged into main before x-PayOut stale UI checks;
- Maker and Checker cockpit entry points work after onboarding.

Risk: medium.

Reason: Cockpit UI remains actively shaped. Treat visual changes as minor-compatible; protect data meanings and routes as contract.

### Partner API

Status: acceptable for RC, but monitor.

Direction:

- API clients send voucher instructions as intent;
- platform-owned provider credentials remain application/runtime configuration;
- scopes/mandates authorize estimate, issue, read, and pay behavior.

Risk: medium.

Reason: BPLS QR Ph integration is newly documented and should be verified against the RC before final `v1.0.0`.

## Dependency audit

The current `composer.json` already uses caret ranges for most dependencies, but several important upstream packages are still beta-constrained:

| Dependency | Current constraint | RC note |
|---|---|---|
| `3neti/voucher` | `^1.0.0-beta.9` | Important execution dependency; monitor before final v1 |
| `3neti/wallet` | `^2.0.0-beta.8` | Important stored-value/wallet dependency; monitor before final v1 |
| `3neti/x-provisioning` | `^1.0.0-beta.11` | Commissioning/provisioning adjacent; acceptable for RC |
| `3neti/emi-core` | `^2.0@beta` | Provider contract dependency; acceptable for RC with NetBank proof |
| `3neti/emi-paynamics` | `^2.0@beta` | Optional/secondary provider path; not blocking NetBank RC |

Conclusion: these do not block `v1.0.0-rc.1`, but they should be called out before final `v1.0.0`.

The v1 promise should be owned by x-change's public contract. Remaining beta dependencies are internal risk until they affect the public contract.

## Known non-blockers for RC1

- Cockpit UI can still be polished under `1.x` as long as routes, data meanings, and capabilities remain stable.
- x-PayOut can remain beta while consuming an x-change RC.
- Cloud-side npm/Vite remains out of the current beta deployment path.
- The cleanroom Cloud URL is not canonical; it is evidence, not a permanent endpoint.

## RC1 blockers

No known blocker prevents tagging `v1.0.0-rc.1` if the focused verification below passes.

However, final `v1.0.0` must not be tagged until the RC is consumed by x-PayOut and the cleanroom result is reproduced.

## Required verification before tagging RC1

Run in `/Users/rli/PhpstormProjects/packages/x-change`:

```bash
composer validate --strict --no-interaction
php -d memory_limit=2G vendor/bin/pest tests/Feature/Console/CommissioningManifestCommandTest.php
php -d memory_limit=2G vendor/bin/pest tests/Feature/Funding/SystemAccountFundingPayCodeIssuanceTest.php tests/Feature/Funding/PayCodeAccountFundingClaimTest.php tests/Feature/Console/DoctorXChangeCommandTest.php
git diff --check
```

If PHP files are changed during RC preparation, also run:

```bash
vendor/bin/pint --dirty --format agent
```

This documentation-only gate does not require Pint unless PHP files change.

## Verification results

Completed on 2026-09-11:

| Check | Result |
|---|---|
| `composer validate --strict --no-interaction` | Passed |
| `php -d memory_limit=2G vendor/bin/pest tests/Feature/Console/CommissioningManifestCommandTest.php` | Passed: 14 tests, 148 assertions |
| `php -d memory_limit=2G vendor/bin/pest tests/Feature/Funding/SystemAccountFundingPayCodeIssuanceTest.php tests/Feature/Funding/PayCodeAccountFundingClaimTest.php tests/Feature/Console/DoctorXChangeCommandTest.php` | Passed: 42 tests, 230 assertions |

The first Pest attempt was blocked by local filesystem sandbox permissions for Testbench storage, then rerun with repository write access and passed. No product failure was observed.

## Required RC consumption proof

After `v1.0.0-rc.1` is tagged, x-PayOut should test exact RC consumption first:

```json
"3neti/x-change": "1.0.0-rc.1"
```

Then verify:

- fresh local `composer create-project`;
- package install and Composer lock resolution;
- prebuilt assets still present;
- local commissioning;
- Maker/Checker funded onboarding;
- Cloud cleanroom deployment;
- pre-commission doctor;
- final strict doctor;
- Maker and Checker claims;
- Client Funds projection;
- role-specific cockpit routing.

Only after this is green should x-PayOut move to:

```json
"3neti/x-change": "^1.0"
```

## Final v1.0.0 go/no-go rule

Tag `3neti/x-change v1.0.0` only if:

- `v1.0.0-rc.1` has no breaking contract findings;
- x-PayOut consumes the RC from a cleanroom install;
- funded onboarding remains green;
- BPLS/payable voucher API usage remains compatible;
- no known host-app-only patches are required for x-change behavior;
- final strict doctor remains green in the cleanroom Cloud deployment.

If a breaking contract issue is found, fix it and cut `v1.0.0-rc.2`.

## Audit conclusion

The practical next step is to run the focused package checks, then tag:

```text
v1.0.0-rc.1
```

The final stable `v1.0.0` should wait for one clean x-PayOut consumption proof against the RC.
