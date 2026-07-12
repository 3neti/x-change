# Cockpit Wave 51B — Single Recipient Campaign Draft Characterization

## Status

Completed.

## Scope

Characterize one campaign recipient context becoming a Cockpit issuance draft.

## Protected behavior

The test proves a campaign recipient context can produce:

- `template_key: ofw-remittance`
- campaign planning, execution, campaign, audience, and recipient identifiers;
- recipient reference;
- recipient mobile and email;
- amount and currency;
- purpose/message context;
- read-only campaign metadata.

## Boundary

The characterization does not issue a Pay Code.

It does not mutate campaign state, execute bulk issuance, dispatch distribution, send feedback, write journal entries, call providers, move wallet funds, own lifecycle truth, or expose unsafe payloads.

## Next slice

`Cockpit Wave 51C — Campaign Draft Compiler Request Compatibility`
