# Cockpit Wave 44B — Campaign Recipient Activity Explorer Link Hydration

## Status

Completed.

## What changed

- Operator Issuance Activity cards now use safe campaign attribution to build recipient-aware Explorer links.
- The existing `Open in Explorer` link preserves campaign planning, execution, campaign, audience, recipient, source, and generated activity code query context.
- Activity cards can now show a read-only `Return to Campaign Dashboard` link when campaign attribution is available.

## Expected UI result

On `/x/cockpit`, campaign-attributed activity cards can show:

```text
Open in Explorer
Return to Campaign Dashboard
```

The Explorer link includes:

```text
campaign_planning_key
campaign_execution_id
campaign_id
campaign_audience_id
campaign_recipient_id
campaign_source
activity_code
activity_source
```

## Tests

- Frontend dashboard hydration test proves campaign context is preserved in both activity navigation links.
- Architecture report guard documents the checkpoint.

## Boundaries preserved

- Links are read-only navigation only.
- No campaign mutation.
- No campaign progress update.
- No bulk issuance.
- No delivery dispatch.
- No lifecycle truth ownership.
- No unsafe payload exposure.

## Next checkpoint

`Cockpit Wave 44C — Campaign Recipient Activity Navigation UI Hardening`.
