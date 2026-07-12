# Cockpit Wave 65B — Manual Distribution External Evidence Schema / Template

## Status

Scaffolded / Planning template recorded.

## Purpose

Wave 65B defines a planning-only template for future external evidence intake.

This is not a database schema, request contract, upload endpoint, or runtime DTO. It is a safe vocabulary for discussing future intake before implementation.

## Planning Template

```text
schema: x-change.cockpit.manual-distribution-external-evidence.planning.v1
pay_code:
beneficiary_url:
external_workflow_name:
external_workflow_reference:
recipient_verification_method:
recipient_reference_redacted:
operator_reference:
handoff_performed_at:
evidence_status: pending | supplied | rejected | unavailable
delivery_reference_redacted:
operator_notes_redacted:
attachments_allowed: false
raw_payload_allowed: false
lifecycle_truth: false
feedback_truth: false
journal_truth: false
action_truth: false
campaign_truth: false
```

## Required Redaction Position

Future evidence intake must default to redacted, operator-safe references only.

It must not collect:

- OTP values.
- Secret claim material.
- Raw message bodies.
- Provider payloads.
- Wallet details.
- Unredacted recipient PII.
- Screenshots containing sensitive third-party account data.
- Unapproved attachments.

## Allowed Planning States

- `pending`: evidence is expected but not supplied.
- `supplied`: evidence was supplied through an approved future intake path.
- `rejected`: evidence was reviewed and rejected.
- `unavailable`: evidence cannot be supplied.

## Planning-Only Boundary

This template does not create:

- Tables.
- Models.
- Migrations.
- DTOs.
- Routes.
- Controllers.
- Upload endpoints.
- Journal records.
- Feedback records.
- Action records.
- Campaign records.
- Provider calls.
- Voucher mutations.
- Wallet mutations.
- Money movement.

## Next Checkpoint

Cockpit Wave 65C — Manual Distribution External Evidence Intake Closure.
