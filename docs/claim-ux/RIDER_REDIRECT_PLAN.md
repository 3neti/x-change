# Deterministic Rider Redirect Plan

Status: implemented locally; feature default-off; release and Cloud activation pending. Companion: [compass](RIDER_REDIRECT_COMPASS.md).

## Objective

Make rider URL navigation a backend-selected terminal action after authoritative claim success. Navigate in the same tab without an intermediate success countdown or advance destination notice. Preserve a durable, recoverable obligation and accurate journal evidence.

## Boundaries

Form Flow owns a portable terminal navigation response. x-rider owns destination validation. x-change owns issuance snapshots, claim eligibility, authorized handoff, recovery, outbox delivery, and receipt verification. x-journal remains the canonical immutable journal through its existing recorder API.

Freeze validated destinations at issuance, before charging. Successful claim recording makes the obligation eligible atomically. Persist journal events in a local outbox and deliver asynchronously with stable idempotency keys. Never repeat a financial execution to recover a redirect.

Only the submitting claim session can initiate a handoff. Optional destination integrations receive an opaque reference and authenticate acknowledgments. Record destination receipt and destination-reported page initialization separately. A redirect response is not arrival evidence.

## Compatibility and exclusions

Default off; only newly issued vouchers receive obligations. Existing vouchers retain legacy behavior. Pending, rejected, and reconciliation outcomes do not qualify. No iframe, mirroring, CDN-hosted landing-page service, pricing change, or arbitrary-site palette. No production deployment in this task.

## Verification gates

Cover frozen URLs, validation before issuance, authorization, success/pending/failure, duplicates, interruption/recovery, journal outages/replay, signed receipts and replay protection. Run focused Pest, lifecycle fixtures, browser checks, Pint, and whitespace checks. Package release precedes host adoption and testing; production requires explicit approval.
