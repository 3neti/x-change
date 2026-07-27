# Create Pay Code UI/UX Contract

## Purpose

`/x/cockpit/quick-generate` is the ordinary Account holder’s Pay Code
designer. The route name and internal `QuickGenerate` implementation remain
stable for compatibility, but the product language is **Create Pay Code**.

The intended journey is:

```text
Create → Design → Review → Issue
```

The page must help a person who understands ordinary mobile applications
create a Pay Code without first learning voucher DTOs, mutation handoffs, or
provider internals.

## Primary experience

The first working surface contains:

- amount;
- recipient;
- purpose; and
- a live front/back Pay Code canvas.

The canvas is a digital credential preview, not a bank cheque. It must not use
MICR lines, account routing, signature lines, or “pay to the order of”
language.

The front prioritizes capability, value, recipient, purpose, and Pay Code
identity. The back explains the claim journey. Before issuance it uses an
explicitly non-scannable placeholder. After issuance it may display the real
claim credential.

## Starting Point

Every design begins from one compact control:

- **Blank Pay Code** removes optional instructions and clears amount, recipient,
  and purpose;
- **Repeat Last Design** restores the last successful reusable instruction
  blueprint, but never restores the recipient, feedback destinations,
  validation secrets, issuer/account identifiers, or absolute dates;
- **Choose Template** opens one responsive picker containing package-owned
  recommended templates and the Account holder’s **My Templates**; and
- **Save As Template** stores the current reusable design for that Account
  holder.

The old **Start Fresh** action must not reapply the currently selected product
profile. Blank means blank.

Recommended templates and personal templates are different concepts. A
recommended template is a package-owned issuance profile. A personal template
is an owner-scoped reusable blueprint whose `base_template_key` continues to
identify the recommended profile used by the issuance compiler.

When a personal template is used, the issued instruction metadata records its
reference and name under `metadata.custom.cockpit.saved_template`. It does not
replace `template_key` and it does not create or mutate campaign context.

## Personal template safety

Personal templates are package-owned database records. Their instruction
blueprints use Laravel’s encrypted array cast and are queried through the
authenticated owner relationship.

Before storage, x-change removes:

- recipient identifiers and recipient validation values;
- feedback email, mobile, and webhook destinations;
- validation secrets;
- absolute start and expiry dates;
- issuer and Account identifiers;
- campaign context;
- prior saved-template provenance;
- idempotency keys; and
- generated Pay Code values.

The user explicitly chooses whether amount and purpose are reusable. Amount is
excluded by default; purpose is included by default. Server-side sanitization
is authoritative even when the browser already removed those fields.

## Progressive disclosure

Claim inputs, verification, feedback, riders, slicing, settlement, execution,
metadata, and other advanced controls belong under **Instructions and
safeguards**.

The design-status checklist and DTO coverage are secondary disclosures. They
must not compete with the essentials or live canvas.

## Review and issue

**Review your Pay Code** summarizes the effective recipient, value, expiry,
claim inputs, safeguards, rider, feedback, and slicing choices.

The primary action is **Issue Pay Code**. While processing it reads
**Issuing Pay Code…**. Success reads **Pay Code issued** and keeps the issued
code visually prominent.

The existing issuance compiler, authorization, validation, idempotency,
pricing, funding, journal, action, feedback, campaign, provider, and Treasury
boundaries remain authoritative. This UI does not create a parallel issuance
runtime.

## Technical boundary

Ordinary users must not see:

- `GeneratePayCode`;
- DTO coverage as a primary surface;
- mutation-route provenance;
- architecture history;
- raw payloads; or
- runtime gate diagnostics.

Technical diagnostics remain package-owned and testable, but they are omitted
from the ordinary page. If a future privileged diagnostics surface reuses
them, authorization must happen in the server read model; hiding DOM with CSS
is not authorization.

## Responsive behavior

Desktop presents essentials and the credential side by side, with the canvas
sticky while editing. Narrow layouts stack the form and canvas, preserve the
front/back control, avoid horizontal scrolling, and keep all touch controls at
comfortable sizes.

## Acceptance

Acceptance requires:

- reactive amount, recipient, purpose, capability, expiry, and safeguard
  presentation;
- deterministic Blank, Repeat Last, recommended-template, and personal-template
  behavior;
- encrypted, owner-scoped personal-template persistence;
- recipient and one-time-data removal from reusable blueprints;
- no fake scannable QR before issuance;
- unchanged sanitized issuance payload semantics;
- structured validation errors;
- duplicate-submit prevention;
- dark-mode parity;
- focused Vue and architecture tests;
- successful asset drift diagnostics and production build; and
- desktop and narrow-layout browser inspection when browser control supports
  the required viewport.
