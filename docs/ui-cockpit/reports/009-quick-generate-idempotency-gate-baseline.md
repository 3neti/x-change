# Cockpit Slice 22 — Quick Generate Idempotency Gate Baseline

## Scope

Cockpit Slice 22 adds a read-only idempotency gate baseline for Quick Generate.

The slice exposes idempotency readiness as operator-facing facts. It does not persist idempotency keys, hash request payloads, query replay records, evaluate conflicts, read TTL policy, or enable Pay Code generation.

## Gate Facts

The baseline gate set is:

- `idempotency-policy-known`
- `idempotency-key-source-defined`
- `payload-fingerprint-defined`
- `replay-lookup-ready`
- `conflict-response-ready`
- `ttl-policy-ready`

Only `idempotency-policy-known` is marked as passed because Cockpit can represent the idempotency boundary as a read-only readiness fact.

All key-source, fingerprint, replay, conflict, and TTL gates remain blocked.

## Boundary

Idempotency gates are read-only facts in Slice 22.

No idempotency gate persists keys, fingerprints payloads, reads replay records, or enables mutation routes in Slice 22.

The slice does not introduce:

- mutation routes
- request persistence
- idempotency key generation
- idempotency key acceptance
- idempotency store reads
- idempotency store writes
- payload hashing or fingerprinting
- replay response lookup
- conflict evaluation
- TTL policy reads
- voucher generation
- journal events
- action runs
- feedback delivery

## Redaction

The idempotency gate read model exposes only gate status and diagnostic reasons.

The following payload classes remain excluded:

- `idempotency_key`
- `request_payload`
- `payload_fingerprint`
- `stored_response`
- `replay_payload`
- `cache_key`
- `raw_payload`

## Implementation Notes

The read model additions are:

- `CockpitQuickGenerateIdempotencyGateData`
- `CockpitQuickGenerateIdempotencyGateCheckData`
- `quick_generate_read_model.idempotency_gate`
- `CockpitQuickGenerateIdempotencyGatePanel`

The existing `draft_contract.idempotency_key` field remains null in this baseline. The new `idempotency_gate` field records the readiness checks that must become true before a future mutation route can safely submit generation requests with replay and conflict semantics.

## Verification

The Slice 22 tests protect:

- default not-wired idempotency gate shape
- hydrated Quick Generate idempotency gate facts
- absence of mutation route behavior
- absence of idempotency key, payload fingerprint, stored response, replay payload, cache key, and raw payload exposure
- frontend rendering without forms or side effects
