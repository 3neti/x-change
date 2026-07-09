# Host Integration Slice 1E — Campaign Cockpit Dashboard Presentation Hydration

## Scope

Render the existing `campaign_read_model` prop on the Cockpit dashboard through a read-only dashboard presentation panel.

## Presentation

The dashboard now displays a sanitized campaign adoption panel with:

- campaign summary facts
- planning and execution identifiers
- read-only campaign surfaces
- workspace panel statuses
- operator action availability
- mutation blocked reason
- unavailable/readiness reason when the campaign read model is absent or unavailable

## Boundaries

- No campaign mutation endpoints
- No Pay Code generation through campaign
- No delivery dispatch
- No journal writes
- No feedback sends or retries
- No wallet reads or writes
- No money movement

## Notes

This slice is presentation-only. It does not add a campaign route namespace, dashboard polling, mutation buttons, provider calls, queue dispatch, persistence, or x-campaign hard dependency.
