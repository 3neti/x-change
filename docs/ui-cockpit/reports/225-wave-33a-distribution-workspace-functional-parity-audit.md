# Cockpit Wave 33A — Distribution Workspace Functional Parity Audit

## Mission

Define the read-only hardening scope for `/x/cockpit/pay-codes/{code}/distribution` before replacing Distribution Workspace placeholders with hydrated share-surface facts.

## Current State

The Distribution Workspace route exists and renders the Cockpit package page, but the page still primarily uses static planning placeholders for:

- digital distribution channels;
- print templates;
- share / QR assets;
- operational analytics;
- disabled distribution actions.

The route receives the standard voucher-scoped `read_model` prop, but the package page adapter does not yet pass props into the Cockpit page and the page does not yet consume voucher-scoped distribution facts.

## Functional Parity Target

Distribution Workspace should become a read-only operator surface for safe sharing and distribution inspection around a Pay Code. It should show:

- voucher code and display status;
- read-only share targets;
- QR / short-link / copy-text readiness;
- print template readiness;
- feedback-channel readiness from read models where available;
- explicit disabled mutation actions.

## Boundaries

Wave 33 must not:

- dispatch SMS, email, webhook, or in-app feedback;
- generate QR codes or short links;
- generate print artifacts;
- mutate vouchers;
- execute voucher drivers;
- write journal entries;
- execute x-action;
- create campaigns;
- call providers;
- move money;
- expose raw claim, provider, wallet, or feedback payloads.

## Proposed Slices

- Wave 33B — Distribution Workspace Read Model Contract.
- Wave 33C — Distribution Workspace Route Prop Hydration.
- Wave 33D — Distribution Workspace UI Presentation.
- Wave 33E — Distribution Workspace Browser / Publish Verification.
- Wave 33F — Distribution Workspace Share Surface Closure.

## Expected UI Result

No visible UI change in this audit slice.

## Next Slice

Cockpit Wave 33B — Distribution Workspace Read Model Contract.
