# Cockpit Wave 50B — Pending Human Result Policy

## Status

Completed.

## Policy

Pending human acceptance means:

- automated evidence may be treated as green;
- the UI flow may be considered technically verified;
- operator acceptance must not be reported as complete;
- mutation work must not use pending human acceptance as approval;
- non-mutating functional work may continue if it does not depend on the copy decision.

## Allowed while pending

- Read-only documentation and compass updates.
- Automated browser evidence.
- Non-mutating read-model improvements.
- Functional planning for campaign issuance, templates, and campaign context.
- Follow-up decision reports.

## Blocked while pending

- Claiming human acceptance passed.
- Enabling new campaign mutation.
- Enabling distribution dispatch.
- Enabling bulk issuance from campaign state.
- Sending feedback.
- Writing journal entries from this Cockpit path.
- Calling providers or moving wallet funds from this Cockpit path.

## Decision

Treat human acceptance as pending but not blocking for non-mutating functional campaign work.

## Next slice

`Cockpit Wave 50C — Campaign Destination Follow-up Decision Matrix`
