# Cockpit Wave 67B — Manual Distribution External Evidence Redaction Plan

## Status

Complete / Planning-only redaction baseline.

## Purpose

Define the redaction posture required before manual distribution external evidence intake can become runtime behavior.

This slice does not implement redaction code. It records what future runtime evidence intake must redact, summarize, or reject before Cockpit may accept operator-submitted external distribution evidence.

## Redaction decision

External evidence intake must be operator-safe by default.

Future runtime evidence may store only the minimum approved facts needed to support manual distribution review. Raw delivery transcripts, unredacted recipient contact details, secret URLs, provider payloads, credentials, access tokens, wallet details, bank details, and free-form sensitive payloads must be rejected or redacted before persistence or presentation.

## Required redaction classes

| Evidence field | Required handling |
|---|---|
| Beneficiary URL | Treat as sensitive settlement access material; display only through approved URL surfaces. |
| Pay Code | Allow only scoped, operator-authorized display. |
| Recipient mobile/email/reference | Mask by default; reveal only under explicit scope. |
| Delivery channel reference | Store/display only a redacted reference. |
| Provider message ID | Store/display only if it is non-secret and operator-safe. |
| External workflow name | Allow if it does not expose credentials or tenant secrets. |
| Operator notes | Validate and redact; reject secrets and raw payloads. |
| Attachments | Block until attachment policy, scanning, retention, and redaction are approved. |
| Screenshots | Block until image redaction, storage, scanning, retention, and review policy are approved. |
| Webhook payloads | Reject raw payloads; future summaries must be allowlisted. |
| Credentials and tokens | Always reject. |
| Wallet, bank, and provider internals | Always reject. |

## Presentation rules

Future Cockpit presentation must:

- Render redacted evidence summaries only.
- Mark evidence as operator-submitted external evidence, not lifecycle truth.
- Distinguish manual distribution evidence from x-feedback delivery truth.
- Distinguish campaign attribution from campaign mutation.
- Distinguish journal handoff status from journal authority.
- Avoid raw payload rendering.
- Avoid secret URL, credential, token, wallet, bank, provider, and webhook payload rendering.

## Explicit denials

Until redaction is implemented and approved, Cockpit must not add:

- Evidence text areas.
- Evidence upload controls.
- Attachment upload.
- Screenshot upload.
- Raw transcript storage.
- Raw provider payload storage.
- Raw webhook payload storage.
- Unmasked recipient contact display.
- Evidence persistence.
- Evidence journal writes.
- Feedback delivery mutation.
- Action completion.
- Campaign mutation.
- Provider calls.
- Voucher mutation.
- Wallet mutation.
- Money movement.

## Runtime implication

Redaction planning alone is not sufficient to unblock runtime evidence intake. Authorization, validation, retention, review, handoff, and rollback plans must also be complete.

## Next slice

```text
Cockpit Wave 67C — Manual Distribution External Evidence Authorization / Redaction Closure
```

