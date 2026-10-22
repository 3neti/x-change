# Claim Experience compass

## Objective

Make the Claim Experience section of Quick Generate a coherent, trustworthy
authoring surface for every voucher mode without changing the internals of
the Order and Pay Code cards.

## Boundaries

- x-change owns the editor, draft compilation, and cockpit request validation.
- Voucher and Form Flow packages retain canonical claim and presentation
  behavior. The editor must express their existing contracts rather than
  inventing a parallel execution path.
- A location/time rule governs claim or release, not an external payment.
- Order and Pay Code internals are protected; card placement may change.
- Generated host frontend files are never edited by hand.

## Implementation checkpoints

- A shared draft already feeds Order, Pay Code, and Claim Experience.
- Collect/Settle targets already compile through the existing issuance path.
- Claim area now requires an explicit center and radius; the editor can test a
  location before issuance. Invalid location and daily window rules are
  rejected at the cockpit request boundary.
- The Claim Experience overview explains each value flow and current value
  use without adding a second state model. Its own mode, value, and collection
  target controls can author the draft without opening Order. The guided
  sections now follow Basics, Requirements, When & Where, Value Use, Rider,
  Updates, and Advanced.

## Open acceptance gates

- Confirm the guided section layout and capability-aware value-use policy in
  a mobile browser after package adoption.
- Prove replay/live parity in a browser. The sandbox host has overlapping
  uncommitted generated frontend work, so this package slice has not been
  published into it.
- Focused Claim Experience Vitest: 6 passed. Cockpit POS and claim-preview
  Pest: 15 passed. The broader foundation frontend suite has eight
  artwork/Teleport and legacy assertion failures; a clean-HEAD archive
  reproduces the same eight failures, so they predate this editor. The
  existing location lifecycle suite
  is blocked first by missing governed Commercial Offering fixtures; adding
  those fixtures alone exposes a separate missing transfer-idempotency key.
  These baseline fixture repairs remain a separate workstream.
- TypeScript project checking is blocked by the checkout's absent
  `@types/node`, which `tsconfig.json` requires. No dependency was added.
