# Cockpit Wave 45C — Campaign Recipient Activity Detail / Distribution UI Hardening

## Status

Completed.

## What changed

- Campaign-context Pay Code detail links now visibly state `read-only`.
- Campaign-context Distribution workspace links now visibly state `read-only`.
- Unsafe or mutating attribution still falls back to base detail/distribution links without campaign-context or read-only campaign labels.

## Expected UI result

Safe campaign-attributed activity cards show:

```text
Open Pay Code · campaign context · read-only
Open Distribution workspace · campaign context · read-only
```

Unsafe or mutating campaign attribution does not show campaign-context/read-only labels on detail or distribution links.

## Tests

- Frontend coverage proves the safe links include read-only labels.
- Frontend coverage proves unsafe fallback links do not carry campaign labels.
- Architecture report guard covers the checkpoint.

## Boundaries preserved

- Detail and Distribution remain read-only navigation.
- No campaign mutation.
- No campaign progress update.
- No bulk issuance.
- No delivery dispatch.
- No lifecycle truth ownership.
- No unsafe payload exposure.

## Next checkpoint

`Cockpit Wave 45D — Campaign Recipient Activity Detail / Distribution Publish / Browser Verification`.
