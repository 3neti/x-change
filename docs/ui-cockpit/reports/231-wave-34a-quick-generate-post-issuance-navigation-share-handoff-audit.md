# Cockpit Wave 34A — Quick Generate Post-Issuance Navigation / Share Handoff Audit

## Mission

Define the operator navigation scope after a successful `/x/cockpit/quick-generate` issuance.

## Current State

Quick Generate already submits through the approved existing `GeneratePayCode` handoff and can show an operator-safe generated Pay Code result with an `Open Cockpit detail` link.

The Distribution Workspace is now a hardened read-only share surface, but successful Quick Generate results do not yet present that workspace as the next operator destination.

## Functional Parity Target

After a successful Quick Generate submission, the operator should see read-only post-issuance handoff links for:

- Cockpit voucher detail;
- Distribution / share workspace;
- optional Pay Code Explorer context refresh.

## Boundaries

Wave 34 must not:

- auto-redirect after generation;
- dispatch SMS, email, webhook, or in-app feedback;
- generate QR codes or short links;
- generate print artifacts;
- mutate vouchers beyond the existing `GeneratePayCode` issuance handoff;
- execute voucher drivers from Cockpit;
- write journal entries directly from the UI;
- execute x-action;
- create or mutate campaigns;
- call providers outside the existing issuance path;
- move money outside the existing issuance path;
- expose raw request, wallet, provider, voucher, feedback, or idempotency payloads.

## Proposed Slices

- Wave 34B — Post-Issuance Navigation Read Model Contract.
- Wave 34C — Quick Generate Result Handoff Hydration.
- Wave 34D — Quick Generate Post-Issuance UI Presentation.
- Wave 34E — Browser / Publish Verification.
- Wave 34F — Post-Issuance Navigation Closure.

## Expected UI Result

No visible UI change in this audit slice.

## Next Slice

Cockpit Wave 34B — Post-Issuance Navigation Read Model Contract.
