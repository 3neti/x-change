# Purpose-first Order card plan

## Outcome

Make the issuer's intent the starting point while retaining the existing Quick Generate page and Pay Code card. Collect and Settle place amounts, destination, payment pattern and fees together; the live summary and walkthrough describe the actual instruction.

## Design and implementation

1. Present Disburse, Collect and Settle as clear, touch-friendly purpose choices before the amount.
2. Show only the fields and supported workflows relevant to the selected purpose. For settlement distinguish disbursement followed by repayment from PhilHealth's patient attestation, hospital review and later payment. Do not imply that every settlement has a disbursement.
3. Present collection destination and payment pattern next to the relevant amounts. Ensure destination summaries describe the actual selected destination, including Client Funds as the default.
4. Show a compact live sequence of actors, amounts, destinations and waiting stages. Keep the Pay Code card's full walkthrough and engineering preview authoritative.
5. Separate issuance type (Pay Code or Invitation) from the funding decision. Only show supported combinations. Derive the action label and funding summary from authoritative pricing and funding preflight, including pending and unavailable states.
6. Use mobile-first, vertically stacked controls, accessible labels and touch targets. Avoid horizontal scrolling and preserve existing desktop workflows.
7. Preserve drafts or require an explicit choice when switching purposes; never emit a hidden incompatible instruction. Keep voucher semantics and backend guards intact.
8. Test each workflow, fee bearer, destination, funding decision, preview and persisted instruction; exercise mobile and desktop widths, keyboard and screen-reader semantics.
9. Integrate in the sandbox only after checking existing published files and preserving unrelated local work; verify the host build and walkthrough.

## Out of scope

No changes to pricing policy, live payment execution, authorization or provider configuration. No real-money acceptance transactions without authorization.
