# Cockpit Wave 52B — Runtime Adoption Decision Existing Payload Path

## Status

Completed.

## Decision

Campaign Template Quick Generate runtime adoption will use the existing Quick Generate mutation payload path:

```text
Campaign read model / source link
    ↓
Quick Generate form prefill
    ↓
GeneratePayCodeRequest-compatible payload
    ↓
CockpitQuickGenerateDraftFactoryContract
    ↓
CockpitIssuanceDraftValidatorContract
    ↓
CockpitIssuanceDraftCompilerContract
    ↓
GeneratePayCode
```

## Rationale

This preserves the already-approved x-change issuance ownership boundary.

The campaign draft adapter remains a source-link/read-model preparation seam. It should not become a second mutation dependency inside the Quick Generate route because that would create two paths for building issuance payloads.

## Explicit Boundary

The runtime route may accept campaign context as request metadata.

The runtime route must not:

- call `x-campaign`;
- mutate campaign state;
- perform bulk issuance;
- bypass `GeneratePayCodeRequest`;
- bypass `GeneratePayCode`;
- call provider, wallet, journal, action, or feedback services directly.

## Implementation Consequence

Wave 52C should align the frontend campaign-prefilled payload with the compiler-compatible request shape by including:

```text
cash.validation.mobile
inputs.fields[] = mobile
```

when a recipient mobile/reference is present.

## Expected UI Result

No new operator-visible panel is expected from this decision slice.

Operators should continue seeing the existing Campaign context prefill card and successful Quick Generate result panels.
