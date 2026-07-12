# Cockpit Wave 51A — Campaign Template Quick Generate Functional Bridge Audit

## Status

Completed.

## Mission

Return from destination-navigation polish to the functional objective:

```text
Generate Pay Codes using template and campaign ideas.
```

## Existing source inspected

- `DefaultCockpitCampaignIssuanceDraftAdapter`
- `DefaultCockpitQuickGenerateDraftFactory`
- `DefaultCockpitIssuanceDraftCompiler`
- `DefaultCockpitIssuanceDraftValidator`
- `GeneratePayCodeRequest`

## Current architecture

The existing bridge already has the correct shape:

```text
Campaign context
    → Cockpit campaign issuance draft adapter
    → Cockpit issuance draft
    → Cockpit issuance draft compiler
    → GeneratePayCodeRequest-compatible payload
    → existing GeneratePayCode owner
```

## Audit finding

Wave 51 should not create a parallel issuance runtime.

The useful next work is to strengthen characterization around single-recipient campaign/template context becoming a safe Quick Generate payload, while keeping campaign state read-only.

## Boundary

Wave 51 does not authorize:

- campaign mutation;
- bulk issuance;
- distribution dispatch;
- feedback delivery;
- journal writes;
- provider calls;
- direct wallet movement;
- lifecycle truth ownership;
- unsafe payload exposure.

## Next slice

`Cockpit Wave 51B — Single Recipient Campaign Draft Characterization`
