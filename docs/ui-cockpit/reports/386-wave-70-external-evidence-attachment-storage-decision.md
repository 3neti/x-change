# Cockpit Wave 70 — Manual Distribution External Evidence Attachment / Storage Decision

## Status

Complete / Attachment and storage decision baseline.

## Decision

Attachments and screenshots remain blocked.

Future manual distribution external evidence intake may start only with structured, redacted, text-only summaries after runtime authorization. File-based evidence requires a separate explicit implementation wave.

Current decision:

```text
attachments-blocked / text-only-evidence-first
```

## Rationale

Attachments and screenshots create risk that is not solved by the current planning baseline:

- Secret beneficiary URLs may appear in images.
- Recipient PII may appear in screenshots.
- Delivery transcripts may contain sensitive free-form content.
- Files require malware scanning.
- Files require storage disk selection.
- Files require retention and purge guarantees.
- Files require preview redaction.
- Files require access logging.
- Files require export and legal-hold policies.

## Storage decision

No evidence storage disk is authorized yet.

Future structured text evidence storage remains blocked until a database schema decision and runtime implementation decision are explicitly approved.

Future file evidence storage remains blocked until all of these are approved:

- Storage disk.
- Encryption policy.
- Malware scanning policy.
- File type allowlist.
- File size limits.
- Image redaction policy.
- Preview generation policy.
- Access logging policy.
- Retention and purge policy.
- Legal hold policy.
- Backup and restore policy.

## Allowed future first runtime shape

If runtime is later approved, the first implementation should be:

```text
structured redacted text-only evidence
```

It should not include:

- Attachments.
- Screenshots.
- File uploads.
- Image previews.
- External object storage.
- Raw transcripts.
- Raw provider payloads.
- Raw webhook payloads.

## Explicit denials

Wave 70 does not add:

- File upload controls.
- Attachment DTOs.
- Attachment models.
- Attachment tables.
- Attachment migrations.
- Storage disks.
- File scanners.
- Image redaction services.
- Preview generators.
- File purge jobs.
- Evidence storage.
- Journal handoff persistence.
- Feedback mutation.
- Action completion.
- Campaign mutation.
- Provider calls.
- Voucher mutation.
- Wallet mutation.
- Money movement.

## Next checkpoint

```text
Cockpit Wave 71 — Manual Distribution External Evidence Runtime Readiness Closure
```

