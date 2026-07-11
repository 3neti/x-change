# Cockpit Wave 28C — Operator Activity Next Runtime Decision

## Status

Complete.

## Decision

Close the Operator Activity filter hardening sequence for now.

Do not continue adding filter-specific UX in the next wave.

## Rationale

The Operator Issuance Activity panel now has:

- durable activity read-model support;
- read-only search/status/handoff query intake;
- visible filter controls;
- result summaries;
- filtered no-match copy;
- compact active-filter summary;
- clear-per-filter links;
- Dusk browser coverage;
- clean host-published asset verification.

The next higher-value runtime work should move from filter refinement back to broader Cockpit operational visibility.

## Next Runtime Direction

Recommended next wave:

```text
Cockpit Wave 29 — Pay Code Explorer Runtime Parity / Activity Navigation Bridge
```

Recommended scope:

- inspect the current Pay Code Explorer Cockpit page against `/x/pay-codes`;
- preserve Cockpit read-only boundaries;
- improve navigation from Operator Issuance Activity cards into Pay Code Explorer/detail context;
- avoid new issuance, redemption, provider, wallet, journal, action, feedback, or campaign mutations.

## Explicit Boundaries

Wave 28C does not add:

- Pay Code Explorer changes;
- visible multi-select controls;
- saved filter presets;
- filter persistence;
- POST/PUT/PATCH/DELETE filter routes;
- runtime configuration mutation UI;
- handoff enablement toggles;
- retry, resend, rerun, or execute controls;
- provider calls;
- wallet mutation;
- voucher execution mutation;
- journal/action/feedback write expansion;
- campaign mutation;
- raw payload display.

## Open Questions for Wave 29

- Should activity card links continue opening voucher detail directly, or should they support an Explorer-filtered context first?
- Which `/x/pay-codes` fields are functionally required in Cockpit Explorer before UI parity work continues?
- Should Explorer filters reuse the Operator Activity query style?
- Which Pay Code lifecycle states should be visible first in Cockpit Explorer?

## Next Checkpoint

Cockpit Wave 28D — Operator Activity Filter Acceptance Closure.
