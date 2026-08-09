# Pay Code Issuance UI/UX Guide

- Status: accepted product and implementation contract
- Route: `/x/cockpit/quick-generate`
- Source owner: `3neti/x-change`
- Last reviewed: 2026-07-29

## Purpose

Pay Code Issuance lets an Account holder create a Pay Code without first learning
the underlying voucher instruction model. The page uses familiar payment and
check language while preserving the precise instruction contract behind the
existing issuance handoff.

The page answers three questions in order:

1. What is this Pay Code ordering?
2. What will the Pay Code look like and cost?
3. What will the recipient experience when claiming it?

## Page Anatomy

```text
Create
Pay Code Issuance
Create a Pay Code for someone to claim.

Order                              Pay Code
value · pay to · purpose           Stamp · Design · Claim · Cost

Claim Experience
optional requirements, checks, Rider content, and advanced rules
```

### Order

**Order** is the primary authoring surface. It deliberately echoes the
check-era idea of an order to pay, without describing a bank transfer or an
already-executed payment.

It contains only the common starting facts:

- **Amount** — the Pay Code value.
- **Pay To** — a recipient reference, mobile number, vendor alias, or an open
  recipient when the product permits it.
- **Purpose** — the human reason for the Pay Code. It also supplies the Rider
  Message when no more specific Rider Message is configured.
- **Templates** — a safe starting point for a repeatable Order.

An Order remains a draft until **Issue Pay Code** succeeds. Changing this card
does not move money, contact a provider, deliver a message, or create a Pay
Code.

#### Pay To Interpretation

**Pay To** uses one canonical, fail-closed interpretation shared by Vue and the
issuance request boundary:

| Entry | Interpretation | Automatic claim controls |
| --- | --- | --- |
| Blank or `CASH` | Open recipient | No identity control is added. |
| Valid Philippine mobile | Mobile-bound | Mobile input, exact mobile match, and OTP are selected and locked. |
| Email address | Email-bound, deferred | Email input is selected, but issuance remains unavailable until email OTP is implemented. |
| `@alias` | Vendor-bound | Vendor matching is selected and composes with every other claim safeguard. |
| Any other 4–255 character value | Release secret | The value becomes a private release code. |
| Double-quoted value | Explicit release secret | Quotes are removed; even a mobile, email, or alias-shaped value is treated as a secret. |

A malformed mobile-like value is never silently downgraded to a secret. The
operator must correct it or quote it deliberately. Release secrets are masked
outside the focused field, redacted from previews and estimates, and sent only
to the issuance request. OTP identity verification does not enable SMS feedback
delivery.

Email OTP remains a deliberate follow-up capability. It must introduce its own
verification contract, price-list item, readiness check, and Claim workflow;
generic mobile OTP and email feedback must not be reused as substitutes.

### Pay Code

**Pay Code** is the live representation of the object being created. Its tabs
use x-change’s stable vocabulary:

| Tab | What it shows |
| --- | --- |
| **Stamp** | The visible Pay Code face, recipient context, instruction indicators, and claim marker. |
| **Design** | Rider Message, Rider Link, Rider Splash, and Stamp presentation controls. |
| **Claim** | A no-money, cached walkthrough of the recipient journey. A Rider Link may end with a provider-aware artwork handoff frame. |
| **Cost** | The estimated instruction charges and total Account debit for the selected quantity. |

The Pay Code canvas is a preview until issuance. The issued modal and public
claim/share assets rely on persisted instruction snapshots, not this editable
preview.

### Claim Experience

**Claim Experience** is the collapsed, optional control area. It groups
everything that changes how a recipient may claim a Pay Code:

- issuance details and schedule;
- recipient and claim requirements;
- validation and verification;
- Rider content and presentation;
- feedback/status updates; and
- advanced settlement or execution instructions.

The label intentionally avoids **Safeguards** as the headline: some controls
are requirements, some are presentation, and some are optional product rules.
The expanded section remains explicit about its advanced nature.

## Language Rules

| Prefer | Avoid in this surface | Reason |
| --- | --- | --- |
| **Create** | Create Pay Code in navigation | Keeps the primary navigation compact beside Funding and Pay Codes. |
| **Pay Code Issuance** | Create Pay Code as the page heading | States the financial operation precisely without making navigation formal. |
| **Issue Pay Code** | Generate Pay Code as the primary action | Makes the irreversible issuance step explicit. |
| **Order** | Essentials | Tells a new user what the first card is for. |
| **Pay To** | Recipient in the primary card | Uses familiar payment language while retaining recipient terminology in technical controls and persisted contracts. |
| **Pay Code** | Canvas or card front | Names the product, not the implementation. |
| **Stamp** | Front | A first-class x-change presentation term. |
| **Claim Experience** | Instructions And Safeguards | Describes the recipient-facing effect of optional controls. |

These are presentation terms only. Existing request keys, voucher instruction
DTOs, validation behavior, authorization, pricing, issuance, provider calls,
and accounting semantics remain unchanged.

## Acceptance Contract

- The navigation item says **Create** and the page heading says **Pay Code
  Issuance**.
- The page header explains: **Create a Pay Code for someone to claim.**
- The left authoring card is **Order** and visibly includes **Amount**, **Pay
  To**, and **Purpose**.
- Valid mobile Pay To values automatically lock mobile and OTP claim controls.
- Mobile-like mistakes block issuance; quoting explicitly selects a release
  secret.
- Email Pay To values are recognized but fail closed until email OTP exists.
- The right preview card remains **Pay Code** with the tab order **Stamp**,
  **Design**, **Claim**, **Cost**.
- **Claim Experience** is collapsed by default and has a concise explanation
  of optional controls.
- Templates, cost estimate, voucher kind, issuance controls, and all existing
  payload behavior remain available.
- The host-published Cockpit mirror is generated from the package source; do
  not edit it directly.
