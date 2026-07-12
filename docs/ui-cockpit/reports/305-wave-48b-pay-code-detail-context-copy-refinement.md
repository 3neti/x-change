# Cockpit Wave 48B — Pay Code Detail Context Copy Refinement

## Status

Completed.

## Scope

Refine the Pay Code Detail campaign context card so operators can understand why campaign context is present and where return links go.

## UI result

Pay Code Detail now renders:

- `Campaign context`
- `Opened from campaign activity`
- a plain-language explanation that links only change the read-only Cockpit view;
- `Back to Explorer · read-only`
- `Back to Campaign Dashboard · read-only`

The same safe campaign-recipient query context is preserved. The rendered diagnostics remain redaction-aware.

## Boundary

No campaign mutation, distribution dispatch, bulk issuance, feedback delivery, journal writes, provider calls, wallet movement, lifecycle truth ownership, or unsafe payload exposure was added.

## Tests

- Frontend coverage verifies the new Pay Code Detail copy and unchanged safe return hrefs.
- Architecture coverage records the slice and ensures the new operator copy is present.
