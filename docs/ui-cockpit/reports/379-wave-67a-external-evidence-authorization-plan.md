# Cockpit Wave 67A — Manual Distribution External Evidence Authorization Plan

## Status

Complete / Planning-only authorization baseline.

## Purpose

Define the authorization posture required before manual distribution external evidence intake can become runtime behavior.

This slice does not implement authorization code. It records the minimum authorization decisions that must exist before any future evidence form, route, controller, model, migration, DTO, storage path, journal handoff, feedback correlation, action completion, campaign attribution, provider call, voucher mutation, wallet mutation, or money movement is allowed.

## Authorization decision

External evidence intake must be denied by default.

Future runtime evidence intake may only proceed when all of these scopes are explicit:

- Authenticated operator identity.
- Cockpit access permission.
- Evidence-create permission.
- Evidence-view permission.
- Evidence-review permission.
- Tenant scope.
- Campaign scope, when campaign context exists.
- Voucher / Pay Code scope.
- Distribution workspace scope.
- Operator role scope.
- Redaction policy scope.
- Audit visibility scope.

## Required authorization gates

| Gate | Required decision |
|---|---|
| Operator authentication | The submitter must be an authenticated operator. |
| Cockpit access | The operator must be allowed to view Cockpit. |
| Evidence create | The operator must be allowed to record manual distribution evidence. |
| Evidence view | The operator must be allowed to see redacted evidence summaries. |
| Evidence review | Reviewers must be explicitly authorized separately from creators. |
| Pay Code scope | The operator must be scoped to the Pay Code being referenced. |
| Campaign scope | Campaign-scoped evidence must require campaign visibility. |
| Tenant scope | Cross-tenant evidence visibility must be denied by default. |
| Sensitive URL handling | Beneficiary URLs remain sensitive settlement access material. |
| Incident escalation | Suspicious or mistaken disclosure evidence must route to an approved escalation path before runtime acceptance. |

## Explicit denials

Until the gates above are implemented and approved, Cockpit must not add:

- Evidence submission forms.
- Evidence upload controls.
- Evidence routes.
- Evidence controllers.
- Evidence policies.
- Evidence database tables.
- Evidence models.
- Evidence DTOs.
- Evidence storage.
- Evidence journal writes.
- Feedback delivery or delivery-status mutation.
- Action completion.
- Campaign state mutation.
- Provider calls.
- Voucher mutation.
- Wallet mutation.
- Money movement.

## Runtime implication

Authorization planning alone is not sufficient to unblock runtime evidence intake. Redaction, validation, retention, review, handoff, and rollback plans must also be complete.

## Next slice

```text
Cockpit Wave 67B — Manual Distribution External Evidence Redaction Plan
```

