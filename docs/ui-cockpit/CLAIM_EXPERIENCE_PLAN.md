# Claim Experience editor plan

- Owner: `3neti/x-change`
- Route: `/x/cockpit/quick-generate`
- Status: implementation in progress

## Product contract

Claim Experience is the authoritative editor for recipient requirements,
validation, value use, rider content, status updates, and advanced claim rules.
Order remains the quick starting point and Pay Code remains the live
Stamp/Design/Claim/Cost preview. Both cards keep their existing internals;
only their page placement may change. All surfaces edit or read one draft and
compile one voucher-instruction payload.

The editor explains three value flows using the existing `voucher_type`:
Disburse releases funded value; Collect uses a collection target with zero
funded principal; Settle has an independent collection target and release
value. Existing POS sale normalization remains authoritative.

## Experience sequence

1. Select the value flow in Claim Experience, then summarize recipient,
   amount, and value use. Order and Claim Experience use the same draft.
2. Define recipient requirements, then claim/release checks. Location needs an
   explicit center and radius, with a test coordinate result. The browser
   location button requires an intentional click and has a manual fallback.
3. Distinguish daily claim windows from issuance start, expiry, and scheduled
   slices. Test a clock time in the chosen timezone and show whether the
   claim would be allowed or blocked.
4. Offer whole, equal, flexible, and scheduled value use through the existing
   slice contract. Show reusable balance only when its capability is ready,
   and state why it is disabled otherwise.
5. Configure rider content and status updates, then review the compiled
   instructions and pricing in the existing Pay Code card.

Location and time rules are enforced at governed claim or release steps.
Payments made outside the application cannot be geofenced. Claim replay must
use the same compiled instruction data as the live journey; no second preview
model is introduced.

## Safety and acceptance

- Reject incomplete or placeholder location rules and malformed daily windows
  at the cockpit issuance request boundary, before pricing or issuance.
- Keep identity binding, capability gating, idempotency, pricing, funding,
  provider calls, accounting, and voucher lifecycle behavior unchanged.
- Verify Disburse, Collect, Settle, location/time allowed and blocked cases,
  slice plans, stored-value readiness, desktop/mobile layout, and replay/live
  parity. Preserve Order and Pay Code regression coverage.
- Release x-change first. Adopt in hosts only from a tagged package through
  Composer and package-owned asset publication.
