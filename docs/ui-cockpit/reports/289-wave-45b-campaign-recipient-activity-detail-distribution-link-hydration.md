# Cockpit Wave 45B — Campaign Recipient Activity Detail / Distribution Link Hydration

## Status

Completed.

## What changed

- Operator Issuance Activity `Open Pay Code` links now preserve safe campaign-recipient query context.
- Operator Issuance Activity cards now expose a read-only `Open Distribution workspace` link.
- Distribution workspace links preserve the same safe campaign-recipient query context as detail and Explorer links.
- Mutating or non-read-only attribution does not propagate campaign query context into detail/distribution links.

## Expected UI result

Safe campaign-attributed activity cards can show:

```text
Open Pay Code · campaign context
Open Distribution workspace · campaign context
Open in Explorer · campaign context
Return to Campaign Dashboard · read-only
```

## Tests

- Frontend dashboard hydration test proves detail/distribution links preserve campaign-recipient context.
- Frontend dashboard hydration test proves unsafe attribution falls back to base detail/distribution links.
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

`Cockpit Wave 45C — Campaign Recipient Activity Detail / Distribution UI Hardening`.
