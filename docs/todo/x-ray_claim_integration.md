# x-ray Integration Into `/x/claim`

## Summary

Use x-ray as the public-safe inspection and preview surface in `/x/claim`, while x-change remains responsible for voucher lifecycle, claim preparation, compiled form-flow, slice selection, payout approval, and success redirects.

## Slice 1 - Implemented Target

- Add `3neti/x-ray` as an x-change dependency.
- Expose an x-change-hosted endpoint: `POST /api/x/v1/pay-codes/x-ray`.
- Build an x-change voucher-to-x-ray projection adapter.
- Render x-ray output in the active `/x/claim` preview area.
- Keep existing claim-experience lookup and compiled form-flow behavior intact.

## Ownership Rules

- x-change decides voucher lifecycle state and claim readiness.
- x-change prepares form-flow and claim-experience payloads.
- x-rider normalizes runtime/stage semantics.
- x-ray renders safe disclosure/inspection projections.
- The host app owns branding, deployment, and published assets.

## Follow-up Slices

- Replace remaining legacy preview shape assumptions with x-ray projection data where safe.
- Add issuer/onboarded/admin audience-specific disclosure scenarios.
- Move more visual stage rendering to x-ray once x-ray supports the x-rider stage shapes directly.
- Add browser coverage for `/x/claim` x-ray rendering after Vite/UI publishing.
