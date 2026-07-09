# Cockpit Mutation Wave 1D — Idempotency and Replay Contract

Status: Backend idempotency and replay scaffolded; UI submit remains deferred

## Purpose

Protect the Cockpit Quick Generate mutation route from duplicate operator submits before enabling the visible UI submit flow.

Wave 1D reuses the existing x-change idempotency infrastructure instead of creating Cockpit-specific storage.

## Behavior

The Cockpit Quick Generate mutation route now:

- extracts the configured `Idempotency-Key` header through `IdempotencyService`
- includes the idempotency key in the action payload `_meta`
- fingerprints the validated issuance-compatible payload
- stores the redacted Cockpit operator response
- replays the stored redacted response for the same key and same payload
- returns an idempotency conflict for the same key with a different payload
- avoids calling `GeneratePayCode` during replay or conflict paths

## Response Contract

First successful submit with a key:

```text
201 Created
status: issued
idempotency.replayed: false
```

Replay with the same key and same payload:

```text
200 OK
status: replayed
idempotency.replayed: true
```

Reuse with the same key and different payload:

```text
409 Conflict
code: IDEMPOTENCY_CONFLICT
```

## Redaction Boundary

The stored replay response is the same operator-safe response returned to Cockpit.

It excludes:

- request payload
- validated payload
- voucher ID
- issuer payload
- wallet data
- debit data
- allocation data
- cost breakdown
- provider payload
- raw payload
- secrets

## Explicit Non-Goals

Wave 1D does not add:

- UI submit enablement
- frontend form submission
- optimistic UI
- post-issuance page refresh
- Pay Code Explorer navigation handoff
- Voucher Detail navigation handoff
- direct provider calls from Cockpit
- direct wallet access from Cockpit
- direct journal writes from Cockpit
- action execution from Cockpit
- feedback delivery from Cockpit
- campaign mutation behavior

## Next Recommended Slice

```text
Cockpit Mutation Wave 1E — UI Submit Enablement
```

Wave 1E may enable the visible Quick Generate submit path only if it uses the idempotency-protected route and does not bypass the existing issuance action.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php tests/Unit/Architecture/CockpitQuickGenerateIdempotencyReplayContractTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php
```
