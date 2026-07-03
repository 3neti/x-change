# Cockpit Slice 23 — Quick Generate Request Validation and Redaction Gate Baseline

## Scope

Cockpit Slice 23 adds a read-only validation and redaction gate baseline for Quick Generate.

The slice exposes request validation and redaction readiness as operator-facing facts. It does not validate request payloads, persist payloads, expose submitted PII, build sanitized previews, return validation errors, or enable Pay Code generation.

## Gate Facts

The baseline gate set is:

- `request-schema-known`
- `required-fields-defined`
- `validation-rules-wired`
- `sensitive-fields-redacted`
- `sanitized-preview-ready`
- `validation-error-contract-ready`

Only `request-schema-known` is marked as passed because Cockpit can represent the existing draft contract schema as a read-only readiness fact.

All required-field, validation-rule, redaction, sanitized-preview, and validation-error-contract gates remain blocked.

## Boundary

Validation and redaction gates are read-only facts in Slice 23.

No validation/redaction gate validates requests, persists payloads, exposes submitted PII, or enables mutation routes in Slice 23.

The slice does not introduce:

- mutation routes
- request validation execution
- `GeneratePayCodeRequest` invocation
- submitted payload persistence
- validated payload persistence
- submitted PII exposure
- sanitized request preview generation
- validation error response contracts
- voucher generation
- journal events
- action runs
- feedback delivery

## Redaction

The validation/redaction gate read model exposes only gate status and diagnostic reasons.

The following payload classes remain excluded:

- `request_payload`
- `validated_payload`
- `validation_errors`
- `mobile`
- `email`
- `recipient_reference`
- `account_number`
- `raw_payload`

## Implementation Notes

The read model additions are:

- `CockpitQuickGenerateValidationRedactionGateData`
- `CockpitQuickGenerateValidationRedactionGateCheckData`
- `quick_generate_read_model.validation_redaction_gate`
- `CockpitQuickGenerateValidationRedactionGatePanel`

The existing `draft_contract` remains the read-only request shape. The new `validation_redaction_gate` field records the readiness checks that must become true before a future mutation route can safely accept, validate, redact, and submit generation requests.

## Verification

The Slice 23 tests protect:

- default not-wired validation/redaction gate shape
- hydrated Quick Generate validation/redaction gate facts
- absence of mutation route behavior
- absence of request payload, validated payload, validation error, mobile, email, recipient reference, account number, and raw payload exposure
- frontend rendering without forms or side effects
