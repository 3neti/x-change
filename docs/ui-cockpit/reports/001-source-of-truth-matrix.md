# 001 — Cockpit Source-of-Truth Matrix

| Capability | Current source | Maturity | Recommendation |
|---|---|---|---|
| Claim UI flow | x-change resources | tested/mature | preserve |
| Paynamics OTP approval UX | x-change resources | tested/mature | preserve |
| Rider message UX | x-change resources plus x-rider components | tested/mature | preserve/promote |
| Splash/redirect success UX | x-change resources | tested/mature | preserve |
| Current dashboard | x-change `resources/js/pages/x-change/Dashboard.vue` | useful baseline | promote into Cockpit after shell exists |
| Current sidebar layout | x-change `resources/js/layouts/x-change/XChangeLayout.vue` | useful baseline | enhance; remove starter-kit residue |
| Dashboard stat cards/recent activity | x-change components | useful baseline | promote into Cockpit widgets |
| Pay Code generation UI | x-change current pages/tests | tested baseline | preserve; Quick Generate should wrap existing safe issuance flows later |
| Execution state/correlation | voucher Execution Engine | scaffold complete through Slice 9 | consume through host read models only |
| Journal/audit timeline | x-journal Cockpit reader/read models | package-ready baseline | consume with host redaction/visibility |
| Workflow actions/CTAs | x-action host composer | package-ready baseline | consume as read-side action bundles only |
| Feedback delivery/in-app/notification UI | x-feedback console/UI component view models | package-ready baseline | consume as presentation facts only |
| Voucher evidence display | x-change settlement/evidence services plus redeem-x prior art | partial/prior-art | enhance later in Voucher Detail slice |
| QR/share/distribution | redeem-x prior art plus current x-change QR/payment components | mixed | adapt later in Distribution Workspace |
| PWA/mobile operator patterns | redeem-x prior art | prior-art | defer until desktop shell stabilizes |
| AI copilot/search | Cockpit docs only | conceptual | defer |
| Campaign/program scale | future x-campaign | not started | defer |

## Boundary Matrix

| Layer | Cockpit may do | Cockpit must not do |
|---|---|---|
| Execution Engine | display execution status, driver labels, execution IDs, and outcome summaries supplied by backend/read models | resolve drivers, mutate voucher state, move money, or reinterpret execution instructions |
| x-journal | display journal entries, artifacts, verification status, and timeline facts after authorization/redaction | write journal truth directly from UI components, bypass visibility, or treat journal entries as workflow authorization |
| x-action | display available/required/blocked/completed CTAs supplied by host composer | execute money, complete workflow by rendering, or treat capability filtering as authorization |
| x-feedback | display notification, delivery, in-app, and retry handoff presentation facts | own lifecycle truth, send provider messages from UI, queue retries, or become Cockpit page owner |
| Claim UX | link to or preserve claimant journey | merge operator Cockpit UX into redeemer claim flow |

## Source Priority

1. Current x-change package source and tests.
2. Current x-change package resources, especially protected Claim UX.
3. Prior-wave package compasses/source for execution, journal, action, and feedback facts.
4. `redeem-x` prior art where current x-change lacks a mature equivalent.

