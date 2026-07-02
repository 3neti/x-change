# x-change Cockpit Compass

## Current Objective

Establish the x-change Cockpit workstream as the operator shell for the Settlement Operating System without disturbing the existing Claim UI, execution runtime, journal, action, or feedback package boundaries.

Current slice: Slice 0 — Discovery and Compass  
Status: Complete  
Last updated: 2026-07-03

## Completed

- Read the Cockpit planning documents under `/Users/rli/PhpstormProjects/x-change-sandbox/docs/todo/x-change_cockpit`.
- Inspected the current x-change package resources, routes, package scripts, frontend tests, and package docs.
- Compared Cockpit intent against the current Execution Engine, x-journal, x-action, and x-feedback baselines.
- Inspected `redeem-x` as prior art only.
- Created the required Slice 0 discovery reports:
  - `reports/000-source-discovery.md`
  - `reports/001-source-of-truth-matrix.md`
  - `reports/002-porting-map.md`
  - `reports/003-scaffold-plan.md`
- Repaired stale frontend expectations for existing `slice_selector` form-flow support before committing Slice 0.

## In Progress

No implementation slice is in progress.

## Next

Recommended next slice: Slice 1 — Cockpit namespace and shell.

Scope should remain foundation-only:

- create `resources/js/cockpit/` or the nearest convention-compatible Cockpit namespace
- create Cockpit layout primitives
- create global header and sidebar navigation placeholders
- create placeholder dashboard shell
- create balance HUD placeholder
- add Vitest coverage under `tests/frontend/cockpit/`
- preserve all existing Claim UI tests

## Risks

- The current x-change dashboard and layout are useful but not yet a distinct Cockpit namespace.
- Existing `XChangeLayout.vue` still contains starter-kit footer links and a narrow navigation model.
- Cockpit read models can expose sensitive financial, journal, action, feedback, and provider data if redaction is not designed before broad operator exposure.
- x-journal Cockpit read models are available, but production redaction, idempotency, signed verification, and visibility-aware pagination remain deferred.
- x-action host bundles are side-effect-free and correlation-oriented; Cockpit must not treat them as authorization, execution, persistence, or lifecycle truth.
- x-feedback UI component view models are presentation facts only; Cockpit owns pages, navigation, authorization, and operator workflows.
- Voucher execution results have `execution_id`, but execution persistence and host wiring remain deferred.
- Concrete settlement-envelope and stored-value gateway bindings remain unresolved.

## Decisions

- Productized Cockpit work belongs inside `/Users/rli/PhpstormProjects/x-change-sandbox/packages/x-change`.
- Do not create a new `cockpit` package for this wave.
- Do not modify `redeem-x`; use it only as historical/prior-art reference.
- Preserve the current Claim Journey, Paynamics OTP approval UX, rider message UX, splash UX, URL redirect UX, and frontend-tested claim flow.
- Cockpit consumes existing system truth. It must not invent execution, journal, action, or feedback behavior.
- Cockpit should start desktop-first, with PWA/mobile adaptations later.
- Cockpit UI tests should live under `tests/frontend/cockpit/`.

## Open Questions

- Whether the Cockpit namespace should be `resources/js/cockpit/` or `resources/js/components/cockpit` plus `resources/js/pages/cockpit`; Slice 1 should decide after testing import ergonomics.
- Which host API/read-model contracts should expose execution, journal, action, and feedback facts to Cockpit without leaking sensitive payloads.
- How operator authorization and redaction should be represented before real Cockpit pages expose journal, action diagnostics, feedback delivery records, or provider payloads.
- Whether existing `/x/dashboard` should be promoted into Cockpit or left as a compatibility route while Cockpit grows under a new route namespace.

## Test Status

- Slice 0 changed documentation only.
- Detected package commands:
  - frontend: `npm run test:frontend`
  - backend: `composer test`
- Ran `npm run test:frontend` from `packages/x-change`.
- Initial result: failed with two stale form-flow expectation mismatches unrelated to Cockpit Slice 0.
  - `tests/frontend/formFlow.test.ts` expected supported field types without `slice_selector`, but `SUPPORTED_FORM_FLOW_FIELD_TYPES` already included `slice_selector`.
  - `tests/frontend/formFlowRendererComponents.test.ts` expected renderer components without `SliceSelectorFieldRenderer`, but `FORM_FLOW_RENDERER_COMPONENTS` already included `SliceSelectorFieldRenderer`.
- Updated those expectations to match the existing runtime.
- Final result: `59 passed, 381 tests`.
- No Cockpit production code or frontend code was changed in Slice 0.
