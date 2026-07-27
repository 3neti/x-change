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

## Progressive disclosure

Templates are optional starting points. Claim inputs, verification, feedback,
riders, slicing, settlement, execution, metadata, and other advanced controls
belong under **Instructions and safeguards**.

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
- no fake scannable QR before issuance;
- unchanged sanitized issuance payload semantics;
- structured validation errors;
- duplicate-submit prevention;
- dark-mode parity;
- focused Vue and architecture tests;
- successful asset drift diagnostics and production build; and
- desktop and narrow-layout browser inspection when browser control supports
  the required viewport.
