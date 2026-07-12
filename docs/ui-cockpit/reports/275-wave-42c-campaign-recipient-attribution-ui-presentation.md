# Cockpit Wave 42C — Campaign Recipient Attribution UI Presentation

## Status

Completed.

## What changed

The Quick Generate result panel now renders recipient-aware campaign attribution facts:

- recipient id;
- recipient reference;
- template key;
- amount and currency.

The existing campaign attribution panel remains read-only and continues to state that campaign state is not mutated.

## Safety constraints

- The UI only renders operator-safe response fields.
- No campaign mutation button was added.
- No bulk generation affordance was added.
- No delivery/journal/action/feedback dispatch was added.
- Unsafe payloads remain excluded from presentation.

## Test coverage

- `CockpitQuickGenerateFoundation.test.ts` verifies recipient attribution rendering and recipient-aware Campaign Explorer/Dashboard return-link hrefs.

## Expected UI result

After a campaign-recipient Quick Generate submission succeeds, the result panel can show the campaign attribution plus the recipient id, recipient reference, template, amount, and generated Pay Code.

## Next checkpoint

`Cockpit Wave 42D — Campaign Recipient Attribution Publish / Browser Verification`.
