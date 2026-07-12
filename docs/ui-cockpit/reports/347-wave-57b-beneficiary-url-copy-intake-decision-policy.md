# Cockpit Wave 57B — Beneficiary URL Copy Intake Decision Policy

## Status

Completed on 2026-07-12.

## Purpose

Define how to classify human acceptance evidence for beneficiary URL copy UX.

## Decision States

### Pass

Use `Pass` only when:

- Voucher Detail was tested.
- Distribution Workspace was tested.
- Both pages showed the beneficiary URL card.
- Both pages showed `Copy beneficiary URL`.
- Both copied values matched their visible beneficiary URLs.
- No backend side effects were observed.
- No delivery side effects were observed.
- No voucher, journal, action, campaign, provider, wallet, or money movement mutation was observed.

### Blocked

Use `Blocked` when:

- clipboard APIs were blocked by browser/security context
- no Pay Code with visible beneficiary URL was available
- the page could not be opened locally
- the tester could not inspect clipboard contents
- the environment could not distinguish app behavior from browser policy

Blocked does not imply implementation failure.

### Fail

Use `Fail` when:

- the copied value does not match the visible beneficiary URL
- unsafe payloads are exposed
- a backend endpoint is called by copy
- feedback, campaign dispatch, journal writes, action execution, provider calls, voucher mutation, wallet mutation, or money movement occurs unexpectedly

## Intake Rule

Do not mark the wave as accepted without explicit human evidence.

If no evidence is provided, keep status as:

```text
pending-human-intake
```

## Verification

- `php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave57bBeneficiaryUrlCopyIntakeDecisionPolicyTest.php`

## Next

Cockpit Wave 57C — Pending Human Intake Status Record.
