# Cockpit Wave 41 — Campaign Recipient Source-Link Selection / Operator Entry Point Closure

## Status

Completed.

## Completed checkpoints

- Wave 41A — Campaign Recipient Source-Link Selection Audit
- Wave 41B — Campaign Recipient Source-Link Read Model Contract / Hydration
- Wave 41C — Campaign Recipient Source-Link UI Presentation
- Wave 41D — Campaign Recipient Source-Link Publish / Browser Verification

## As-built behavior

Campaign Cockpit read models can now expose safe recipient-level Quick Generate entry points through:

```text
campaign_read_model.recipient_quick_generate_links
```

The Campaign Cockpit Adoption panel renders those links as `Recipient Quick Generate entry points`.

Each link:

- opens the existing Quick Generate route;
- carries safe campaign, planning, execution, recipient, template, amount, currency, recipient reference, and purpose context;
- remains read-only until the operator explicitly submits Quick Generate;
- does not mutate campaign state;
- does not perform bulk issuance;
- does not send feedback;
- does not call providers;
- does not move money;
- does not expose raw campaign, recipient, wallet, provider, or delivery payloads.

## Tests and verification

- Backend source-link feature coverage passed.
- Cockpit read model baseline coverage passed.
- Frontend dashboard hydration coverage passed.
- Architecture/documentation guards passed.
- Published asset drift check passed.
- Existing Campaign `Open Quick Generate` Playwright smoke passed.

## Expected UI result

When a campaign adapter provides safe recipient source-link contexts, `/x/cockpit` shows:

```text
Recipient Quick Generate entry points
```

with one link per safe recipient context. Selecting a link opens campaign-prefilled Quick Generate.

## Remaining boundaries

Campaign mutation, bulk generation, delivery, journal/action/feedback side effects, provider calls, and wallet movement remain outside this wave.

## Next recommended wave

`Cockpit Wave 42 — Campaign Recipient Quick Generate Submission Attribution / Result Closure`.

Recommended scope:

- verify recipient-level source-link submissions preserve recipient attribution in Quick Generate result metadata;
- ensure post-issuance Campaign Explorer/Dashboard return links include recipient context;
- keep campaign mutation and bulk issuance blocked.
