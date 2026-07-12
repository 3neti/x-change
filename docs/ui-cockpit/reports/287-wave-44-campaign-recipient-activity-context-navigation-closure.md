# Cockpit Wave 44 — Campaign Recipient Activity Context Navigation / Explorer Bridge

## Status

Completed.

## Completed checkpoints

- Wave 44A — Campaign Recipient Activity Context Navigation Audit
- Wave 44B — Campaign Recipient Activity Explorer Link Hydration
- Wave 44C — Campaign Recipient Activity Navigation UI Hardening
- Wave 44D — Campaign Recipient Activity Context Navigation Publish / Browser Verification

## As-built behavior

Dashboard Operator Issuance Activity cards can now use safe campaign-recipient attribution to preserve campaign context during read-only navigation.

When safe attribution exists, the activity card can show:

```text
Open in Explorer · campaign context
Return to Campaign Dashboard · read-only
```

The Explorer link preserves:

- generated activity code;
- activity source;
- planning key;
- execution id;
- campaign id;
- audience id;
- recipient id;
- campaign source.

If attribution is marked mutating or not read-only, Cockpit does not propagate campaign context into navigation links and does not render a Campaign Dashboard return link.

## Verified behavior

- Frontend tests cover safe recipient-aware navigation links.
- Frontend tests cover mutating attribution being blocked from campaign-context navigation.
- Published asset drift check passed.
- Playwright browser test seeds a durable campaign-attributed activity and verifies the dashboard navigation links in-browser.

## Preserved boundaries

- No campaign mutation.
- No campaign progress update.
- No bulk issuance.
- No delivery dispatch.
- No lifecycle truth ownership.
- No provider calls.
- No wallet movement.
- No unsafe payload exposure.
- Existing `GeneratePayCode` remains the issuance owner.

## Expected UI result

On `/x/cockpit`, campaign-attributed activity cards can show read-only navigation to:

- Pay Code Explorer with campaign-recipient context;
- Campaign Dashboard with campaign-recipient context.

## Next recommended wave

`Cockpit Wave 45 — Campaign Recipient Activity Detail / Distribution Context Bridge`.

Recommended scope:

- preserve safe campaign-recipient context when opening Pay Code detail from an activity card;
- preserve safe campaign-recipient context when opening Distribution workspace from a Pay Code context;
- keep all detail/distribution actions read-only unless separately authorized;
- keep campaign mutation, bulk issuance, delivery, lifecycle truth ownership, provider calls, wallet movement, and unsafe payload exposure blocked.
