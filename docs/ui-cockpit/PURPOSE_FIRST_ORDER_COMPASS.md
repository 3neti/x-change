# Purpose-first Order card compass

**Status:** Local implementation and automated checks complete; sandbox review in progress.  
**Owner:** x-change package; the sandbox hosts generated projections.  
**Plan:** [Purpose-first Order card](PURPOSE_FIRST_ORDER_PLAN.md).

## North star

An issuer can explain who pays, how much, what must happen first, and where the money goes before issuing a Pay Code. The page is calm and legible at phone width, and its live summary, walkthrough, engineering preview and issued instruction agree.

## Guardrails

- Purpose leads; context controls visibility. Keep Disburse, Collect and supported Settle workflows distinct.
- Client Funds and external destinations must be explicit and accurately represented.
- Show fee bearer and funding requirements without inventing a price or confusing a payment target with issuance cost.
- Treat invitations as an issuance choice and funding as a separate decision.
- Preserve data deliberately when changing purpose; never send hidden stale data.
- PhilHealth includes patient attestation, hospital review, waiting and verified payment; loan settlement may disburse before repayment.
- Mobile controls are readable, accessible and usable without horizontal scrolling.
- The package owns source; protect the sandbox's existing local work.

## Evidence

- Purpose, issuance type, settlement workflow, contextual destination and fee controls, funding summary, and live flow are implemented in the package.
- Destination drafts are isolated by purpose; PhilHealth presents attestation, review, waiting and hospital payout, and requires an explicit destination.
- The Claim Experience overview follows the Order selection instead of exposing duplicate purpose and collection-target controls.
- Full frontend suite: 197 files, 1,378 tests passed.
- Focused backend issuance and routing checks: 8 tests, 99 assertions passed.
- Local sandbox build passed; Collect, loan repayment and PhilHealth selections were inspected in a separate browser tab, including a 390-pixel-wide viewport.
- Additional funding is visible without exposing the Client Funds balance when the global header is hidden; a frontend regression test covers this.
- Preview/submission contracts are covered by automated tests. A real persisted issuance and real payment were not performed; sandbox user review and release CI remain pending.
