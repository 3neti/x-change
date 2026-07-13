# Cockpit Wave 72 — Manual Distribution External Evidence Runtime Implementation Decision

## Status

Complete / Runtime implementation not authorized.

## Final decision

The manual distribution external evidence intake planning track is complete.

Runtime implementation is deferred.

Decision:

```text
planning-track-complete / runtime-deferred
```

## Reason

The planning track established the required posture for future runtime work, but runtime implementation still requires explicit approval for:

- Database schema.
- Request/response contract.
- Storage strategy.
- Queue/job strategy.
- Runtime authorization policy implementation.
- Runtime redaction implementation.
- Runtime validation implementation.
- Runtime retention/purge implementation.
- Runtime review UI.
- Runtime journal handoff adapter.
- Runtime x-feedback correlation adapter.
- Runtime x-action continuation adapter.
- Runtime x-campaign attribution adapter.
- Incident runbook implementation.
- Rollback and migration plan.

## Completed planning track

The completed external evidence planning track includes:

- Evidence intake decision.
- Evidence schema/template.
- Runtime readiness audit.
- Runtime preconditions.
- Runtime decision closure.
- Authorization plan.
- Redaction plan.
- Authorization/redaction closure.
- Validation plan.
- Retention plan.
- Validation/retention closure.
- Review/handoff plan.
- Attachment/storage decision.
- Runtime readiness closure.
- Runtime implementation decision.

## Future approved shape if runtime is later authorized

The first future runtime slice should be limited to:

```text
structured redacted text-only external evidence intake
```

It must remain:

- Authorization-gated.
- Redaction-first.
- Validation-first.
- Retention-aware.
- Reviewable.
- Idempotent.
- Journal-ready but not journal-dependent.
- Feedback-correlatable but not feedback-mutating.
- Action-aware but not action-completing.
- Campaign-attributable but not campaign-mutating.
- Voucher-linked but not voucher-mutating.
- Non-provider-calling.
- Non-wallet-mutating.
- Non-money-moving.

## Explicit denials

Wave 72 does not add:

- Runtime evidence routes.
- Runtime evidence controllers.
- Runtime evidence forms.
- Runtime evidence tables.
- Runtime evidence migrations.
- Runtime evidence models.
- Runtime evidence DTOs.
- Runtime evidence repositories.
- Runtime evidence services.
- Runtime evidence policies.
- Runtime evidence storage.
- Runtime journal writers.
- Runtime feedback mutation.
- Runtime action completion.
- Runtime campaign mutation.
- Attachments.
- Screenshots.
- File uploads.
- Provider calls.
- Voucher mutation.
- Wallet mutation.
- Money movement.

## Published asset drift

Command:

```bash
php artisan x-change:doctor --assets --json
```

Result:

```text
checked 59
ok 59
stale 0
missing 0
extra 0
```

## Track completion

This closes the manual distribution external evidence planning track.

Next work should start from a fresh implementation decision outside this track.

Recommended next program checkpoint:

```text
Cockpit Next Capability Selection — Feedback delivery, campaign dispatch, short links, QR assets, print artifacts, or external evidence runtime authorization
```

