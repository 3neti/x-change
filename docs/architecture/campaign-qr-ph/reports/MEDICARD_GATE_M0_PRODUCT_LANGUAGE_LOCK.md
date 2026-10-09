# Medicard Gate M0 — Product and Language Lock

Date: 2026-10-09

Status: **Complete for implementation; stakeholder wording may be revised by a new version**

## Locked Demonstration Product

| Field | Value |
| --- | --- |
| Working title | MediCard Demo Benefit Pass |
| Driver | `medicard.demo-benefit@1.0.0` |
| Product code | `MEDICARD_DEMO_DAY` |
| Entry method | Reusable Campaign QR Ph |
| Demo price | PHP 50.00 |
| Demonstrated experience | One-day healthcare-access journey demonstration |
| Result code | `benefit_ready_demo` |
| Result title | Demo Benefit Summary |
| Summary-link lifetime | 7 days |
| External Medicard API | None; deterministic local demonstration driver |

The demonstration has no insured amount and no treatment value. PHP 50.00 is a
demonstration payment used to exercise the settlement lifecycle; it must not be
described as an HMO premium or a purchase of actual healthcare coverage.

## Beneficiary Inputs

Required:

- Full Name
- Mobile Number
- Email Address
- Birth Date
- Address
- OTP verification through the existing completion Pay Code journey

Prohibited in this version:

- medical history, symptoms, diagnoses, prescriptions, or treatment records;
- dependent or family medical information;
- government identifiers;
- employer or existing HMO membership identifiers; and
- payment-account or raw provider identity data.

## Customer Copy

### First SMS

> We received your PHP 50.00 MediCard demonstration payment. Complete your
> details: {claim_url}. DEMONSTRATION ONLY—no membership or healthcare coverage
> is created.

### Summary SMS

> Your MediCard Demo Benefit Summary is ready: {result_url}. DEMONSTRATION
> ONLY—not membership, coverage, treatment authorization, or reimbursement.

### Mandatory on-screen disclaimer

> DEMONSTRATION ONLY. This does not create MediCard membership, healthcare
> coverage, a policy, a letter of authorization, or a right to treatment or
> reimbursement.

The disclaimer must appear on the payment campaign, claim journey, processing
screen, success screen, and private summary page. It may not be hidden inside a
disclosure panel.

## Branding Boundary

Use the text label “MediCard demonstration” and the host's existing neutral
campaign visual system. Do not bundle, scrape, or imitate a MediCard logo,
trademark asset, proprietary typeface, or production member-card design until
the stakeholder supplies and approves those assets.

## Sanitized Request Fixture

```json
{
  "schema": "x-change.medicard-demo-benefit-request.v1",
  "driver_id": "medicard.demo-benefit",
  "driver_version": "1.0.0",
  "product_code": "MEDICARD_DEMO_DAY",
  "payment": {
    "currency": "PHP",
    "amount_minor": 5000,
    "status": "settled"
  },
  "applicant": {
    "name": "Demo Participant",
    "mobile": "+639000000000",
    "email": "participant@example.test",
    "birth_date": "1990-01-01",
    "address": "Sanitized demonstration address"
  }
}
```

The persisted private request may contain the approved applicant fields. Logs,
journal events, broadcasts, and Cockpit read models must contain only counts,
references, and sanitized status facts.

## Sanitized Result Fixture

```json
{
  "schema": "x-change.medicard-demo-benefit-response.v1",
  "status": "ready_demo",
  "result_code": "benefit_ready_demo",
  "demo_reference": "MEDICARD-DEMO-EXAMPLE",
  "product_code": "MEDICARD_DEMO_DAY",
  "effective_at": "2026-10-09T00:00:00+00:00",
  "expires_at": "2026-10-10T00:00:00+00:00",
  "demonstration_only": true,
  "membership_created": false,
  "healthcare_coverage_created": false,
  "treatment_authorized": false
}
```

## Retention and Access

- The private signed summary link expires after 168 hours.
- Existing immutable settlement, claim, audit, and idempotency records retain
  their package-defined lifecycle until a separately approved retention policy
  changes them.
- Customer-facing pages must not expose raw provider payloads or payment-account
  identity.
- The demonstration does not authorize exporting participant data to MediCard.

## Presenter Fallback

The presentation must have a synthetic, non-financial lifecycle fixture showing
the same screens and statuses. A live payment is optional and remains a separate
gate; the presentation must not depend on provider availability.

## Versioning Decision

Any stakeholder change to price, fields, meaning, result schema, disclaimer, or
completion semantics requires a new reviewed campaign/driver version. Existing
published campaign revisions and completed demonstrations remain immutable.
