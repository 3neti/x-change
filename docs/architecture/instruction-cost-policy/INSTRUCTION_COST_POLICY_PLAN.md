# Instruction cost policy plan

## Summary

Define a package-owned expected provider-cost schedule for voucher instructions while preserving a strict separation between:

- customer-facing price;
- expected provider cost used for planning and unit economics; and
- actual provider cost confirmed by a provider statement, invoice, or debit record.

Ordinary contact and bio-field capture has no direct provider cost. Costs attach to external services and infrastructure that are actually consumed.

## Cost schedule

The initial working schedule uses PHP minor units and a provisional exchange-rate assumption of PHP 65 per USD:

| Instruction or service | Expected cost | Basis |
| --- | ---: | --- |
| Name capture | PHP 0.00 | Local input and persistence |
| Mobile capture | PHP 0.00 | Local input and persistence |
| Email capture | PHP 0.00 | Local input and persistence |
| Birthdate capture | PHP 0.00 | Local input and persistence |
| Plain-text address capture | PHP 0.00 | Local input and persistence |
| SMS notification or verification | PHP 1.30 | Configured provider rate |
| KYC / HyperVerge | PHP 20.00 | Configured provider rate |
| Selfie artifact storage | PHP 0.01 | Provisional storage and transfer reserve |
| Signature artifact storage | PHP 0.01 | Provisional storage and transfer reserve |
| Temporary Mapbox geocoding | PHP 0.05 | Provisional request reserve after free tier |
| Permanent Mapbox geocoding | PHP 0.33 | Provisional permanent-request reserve |

Unknown services remain at PHP 0.00 until a rate or authoritative settlement is configured. A zero estimate means “not configured,” not “guaranteed free.”

## Implementation direction

- Keep the schedule in the commercial pricing and cost layer, with effective timestamps and a documented basis for each rate.
- Keep x-change responsible for instruction orchestration, observed provider-cost settlements, reversals, and reconciliation.
- Keep customer price, expected provider cost, and actual provider settlement in separate fields and report categories.
- Treat Laravel Cloud compute, object storage, database, cache, and bandwidth as shared overhead. Allocate them for internal economics only; do not charge individual bio fields.
- Allow location to distinguish temporary lookup from permanent stored geocoding because their provider terms and rates differ.
- Do not post provisional estimates as cash movements or settled expenses in Operations Economics.

## Acceptance criteria

- Bio-field capture is represented as PHP 0.00 expected provider cost.
- SMS and KYC use their configured rates.
- Storage and geocoding reserves are visibly marked provisional.
- Actual provider-cost reporting remains driven by authoritative settlement evidence.
- Missing rates do not create invented provider-cost postings.
- Existing customer prices remain unchanged unless explicitly configured.

## Verification

- Review the schedule for minor-unit correctness and effective-rate metadata.
- Confirm that expected costs and actual settlements remain separate in the economics read model.
- Exercise temporary and permanent location-cost classifications.
- Confirm that unconfigured services remain visible as unconfigured rather than being presented as settled cost.

## Reliability controls

Pricing activation and Component Economics activation must be treated as one
governed cut-over. Before either active pointer changes, the package validates
the offering version, offering snapshot hash, manifest hash, catalog coverage,
and integer allocation conservation. Any failure rolls back the activation.

Every deployment runs the idempotent reconciliation command for all configured
commercial profiles, including `pay_code` and `account_funding`. The first
rollout reports an unhealthy profile without blocking the deployment while
the existing `account_funding` baseline is repaired. Once both profiles pass a
clean commissioning run, the deployment gate becomes strict.

Estimate and issuance paths perform the same readiness check. Missing or
inconsistent economics return a stable 503 application error and create no
payment order, reservation, debit, or Pay Code. The diagnostic identity is
recorded in safe audit metadata; provider payloads and database details are
never returned to the caller.

Operations monitoring exposes each profile's active offering version,
economics version, snapshot and artifact hashes, last reconciliation result,
and blocked estimate or issuance attempts. Expected provider costs remain
planning values and never become settled provider-cost movements.
