# Cockpit Wave 48A — Campaign Recipient Destination Context Copy Audit

## Status

Completed.

## Scope

Audit the campaign-recipient context copy shown on destination Cockpit pages after an operator opens a Pay Code or Distribution Workspace from campaign-attributed activity.

This slice is operator-copy only. It does not add backend behavior, campaign mutation, distribution dispatch, bulk issuance, feedback delivery, journal writes, provider calls, wallet movement, lifecycle truth ownership, or unsafe payload exposure.

## Current observations

- Pay Code Detail renders a visible campaign-recipient context card for safe `pay_code_detail` navigation.
- Distribution Workspace renders a visible campaign-recipient context card for safe `distribution_workspace` navigation.
- Return links preserve safe campaign-recipient query context.
- The current labels are technically accurate but operator-facing copy repeats internal phrasing such as `Campaign recipient context`, `Read-only Pay Code detail context`, and `campaign context` link suffixes.

## Operator clarity goals

- Make the card purpose obvious: the operator is inspecting a destination that was opened from campaign activity.
- Make navigation safety obvious: links only change the read-only Cockpit view.
- Keep diagnostics visible but secondary.
- Keep read-only/mutation boundaries explicit.

## Planned refinements

- Pay Code Detail should say `Opened from campaign activity`.
- Distribution Workspace should say `Inspecting distribution from campaign activity`.
- Return links should use direct operator language: `Back to Explorer`, `Back to Pay Code Detail`, and `Back to Campaign Dashboard`.
- Each return link should still visibly communicate read-only behavior.

## Boundary

No mutation behavior is authorized in Wave 48.

The destination context cards remain presentation-only and redaction-aware.
