# Cockpit Wave 68B — Manual Distribution External Evidence Retention Plan

## Status

Complete / Planning-only retention baseline.

## Purpose

Define the retention and purge posture required before manual distribution external evidence intake can become runtime behavior.

This slice does not implement storage, retention jobs, purge jobs, models, migrations, or evidence persistence. It records the retention decisions that must be explicit before Cockpit may store any operator-submitted external distribution evidence.

## Retention decision

External evidence must be retained only when it is explicitly authorized, redacted, validated, scoped, reviewable, and purgeable.

The default posture is:

```text
do-not-store-until-retention-policy-is-approved
```

## Required retention classes

| Evidence class | Retention posture |
|---|---|
| Redacted evidence summary | Candidate for short operational retention after approval. |
| Redacted delivery reference | Candidate for short operational retention after approval. |
| Operator note summary | Candidate for short operational retention after approval. |
| Review decision | Candidate for audit retention after approval. |
| Supersession link | Candidate for audit retention after approval. |
| Raw notes | Reject; do not retain. |
| Raw beneficiary URLs | Reject in free-form evidence; do not retain outside approved URL surfaces. |
| Attachments | Block until attachment retention, scanning, storage, and purge policy exist. |
| Screenshots | Block until image redaction, scanning, storage, and purge policy exist. |
| Provider payloads | Reject; do not retain. |
| Webhook payloads | Reject; do not retain. |
| Credentials and tokens | Always reject; do not retain. |

## Required purge rules

Future runtime evidence storage must define:

- Retention duration.
- Purge trigger.
- Manual purge authorization.
- Automatic purge schedule.
- Legal hold behavior.
- Superseded evidence handling.
- Rejected evidence handling.
- Correction evidence handling.
- Audit summary retention.
- Journal handoff retention boundary.
- Backup and restore expectations.

## Required read-model rules

Future Cockpit read models must:

- Show only current redacted evidence summaries by default.
- Mark purged evidence as purged without exposing original content.
- Show rejected evidence only to authorized reviewers.
- Hide superseded evidence from default operator views.
- Avoid raw payload display.
- Avoid exposing purge internals as lifecycle truth.

## Explicit denials

Until retention is implemented and approved, Cockpit must not add:

- Evidence tables.
- Evidence models.
- Evidence migrations.
- Evidence repositories.
- Evidence storage disks.
- Evidence file retention.
- Evidence purge jobs.
- Evidence archival jobs.
- Evidence restore flows.
- Attachment storage.
- Screenshot storage.
- Raw transcript storage.
- Journal handoff persistence.
- Feedback mutation.
- Action completion.
- Campaign mutation.
- Provider calls.
- Voucher mutation.
- Wallet mutation.
- Money movement.

## Runtime implication

Retention planning does not authorize runtime evidence intake. Review workflow, handoff policy, attachment policy, incident runbook, and rollback/migration plans remain required.

## Next slice

```text
Cockpit Wave 68C — Manual Distribution External Evidence Validation / Retention Closure
```

