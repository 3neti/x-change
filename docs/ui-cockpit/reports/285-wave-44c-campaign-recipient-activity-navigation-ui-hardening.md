# Cockpit Wave 44C — Campaign Recipient Activity Navigation UI Hardening

## Status

Completed.

## What changed

- Activity `Open in Explorer` links now visibly indicate when they carry campaign context.
- Activity `Return to Campaign Dashboard` links are labeled read-only.
- Campaign context is not propagated into navigation links when attribution is marked mutating or not read-only.

## Expected UI result

Safe campaign-attributed activity cards show:

```text
Open in Explorer · campaign context
Return to Campaign Dashboard · read-only
```

Unsafe or mutating campaign attribution keeps only the basic activity Explorer link and does not render a campaign dashboard return link.

## Tests

- Frontend coverage proves safe links carry campaign context and visible read-only labels.
- Frontend coverage proves mutating attribution does not propagate campaign context into navigation links.
- Architecture report guard covers the checkpoint.

## Boundaries preserved

- Navigation remains read-only.
- No campaign mutation.
- No campaign progress update.
- No bulk issuance.
- No delivery dispatch.
- No lifecycle truth ownership.
- No unsafe payload exposure.

## Next checkpoint

`Cockpit Wave 44D — Campaign Recipient Activity Context Navigation Publish / Browser Verification`.
