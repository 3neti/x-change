# Account Funding Workspace UI/UX Guide

- Status: accepted product and implementation contract
- Route: `/x/cockpit/funding`
- Source owner: `3neti/x-change`
- Last reviewed: 2026-07-27

## Purpose

The Account Funding workspace helps an Account holder add verified value
without presenting x-change as a wallet or exposing internal Treasury
operations.

The page should answer four questions quickly:

1. How much value is currently available to the Account?
2. Which funding method should the Account holder use?
3. What must the Account holder do next?
4. Has the bank, payment provider, or Pay Code confirmed the addition?

The governing product promise is:

> Funds appear in the Account only after confirmation from the bank, payment
> provider, or an eligible Pay Code outcome.

The UI never offers a discretionary **Add funds** control. A button may request
instructions, query authoritative history, inspect a Pay Code, or confirm an
already-inspected outcome. It must not manufacture settlement authority.

## Audience

This guide has three audiences:

- **Account holders** use the first half as the product guide.
- **Developers** use the implementation map and acceptance contract.
- **AI agents** use the explicit invariants and change protocol.

The settlement and accounting design remains canonical in
[Funding Account Management](../architecture/FUNDING_ACCOUNT_MANAGEMENT.md).
The stable reusable-address protocol remains canonical in
[Standing Funding Address Protocol](../architecture/STANDING_FUNDING_ADDRESS_PROTOCOL.md).

## Product Vocabulary

| Term | Meaning in the UI |
| --- | --- |
| **Account** | The user's x-change accounting relationship. |
| **Client Funds** | Provider-positioned value attributed to this Account. |
| **Outstanding Pay Codes** | The Account's current Pay Code obligation. |
| **Issuance Capacity** | The value currently available for new issuance after applicable liquidity and reserve controls. |
| **Account Funding** | The product area for adding verified value to the Account. |
| **Funding Activity** | The durable, sanitized history of funding requests, observations, checks, and outcomes. |

Do not use **wallet**, **wallet balance**, **load**, or **top up** as primary
product language. Legacy identifiers may retain those words when changing them
would break a contract, but the user-facing vocabulary stays Account-oriented.

## For the Account Holder

### Page Anatomy

The Account-holder experience follows this order:

```text
Account position summary
    ↓
Account Funding command card
    ↓
QR Ph | Bank Transfer | Pay Code
    ↓
Other funding options, when needed
    ↓
Funding Activity
```

Internal provider diagnostics and Treasury reconciliation do not interrupt this
journey.

### 1. Account Position Summary

The global Cockpit header presents:

- **Client Funds**
- **Outstanding Pay Codes**
- **Issuance Capacity**

Provider liquidity is not an Account-holder metric. It is visible only in an
authorized Treasury view.

The Funding page must not repeat the same four monetary cards immediately below
the global header. The page-level summary instead reports funding workflow
state:

- **Awaiting Funds**
- **Settled Funding**
- **Open Suspense**
- **Recovery**

These are status cues, not buttons.

### 2. Account Funding Command Card

The command card keeps the three ordinary methods visible as equal tabs:

- **QR Ph**
- **Bank Transfer**
- **Pay Code**

Each tab uses an icon and text. Text remains the accessible name; icons are
decorative.

The card also contains **Other funding options**, which keeps reviewed or
diagnostic workflows out of the primary journey.

### 3. QR Ph

Use QR Ph for an open-amount transfer to the Account's reusable funding
address.

The Account holder sees:

- the reusable QR immediately when it is available;
- editable merchant name and city presentation fields;
- **Update QR** to regenerate the presentation fixture; and
- **Check NetBank** to query recent authoritative provider history.

Merchant fields label the QR only. They do not select the Account, classify the
payment, or authorize an Account credit.

Scanning the QR does not itself change Client Funds. The provider observation
must still satisfy destination, currency, status, idempotency, and configured
limit checks.

### 4. Bank Transfer

Use Bank Transfer when the payer will send to the configured receiving account
through a bank rail.

The Account holder:

1. enters the desired amount;
2. may choose **₱100**, **₱500**, **₱1,000**, **₱5,000**, or **₱10,000**;
3. selects **Get bank transfer instructions**;
4. transfers the unique exact amount shown in the instruction dialog; and
5. checks the provider from the dialog or the later Funding Activity row.

Choosing a quick amount updates the field, marks the amount selected, focuses
the input, and selects its contents so it remains easy to adjust.

The unique adjustment is matching data, not a fee. The instruction dialog must
distinguish real-time InstaPay expectations from banking-day PESONet timing.
Closing the dialog does not discard the request; the request remains available
in Funding Activity.

The sender's screenshot or reference may support audit, but it does not replace
receiver-side provider verification.

### 5. Pay Code

Use Pay Code when an eligible one-time Pay Code can add its reserved value to
Client Funds.

The flow deliberately has two steps:

1. **Check Code** inspects eligibility and displays the amount.
2. **Add to my Account** confirms the Account addition.

Inspection moves no funds. The confirmation step prevents an accidental credit
when a user only intended to inspect a code.

The preview must clearly say whether the code is ready, unavailable, or
expired. It must not expose claim payloads or internal settlement evidence.

### 6. Reviewed Value

Reviewed Value is secondary. Use it when QR Ph and provider-verifiable bank
transfer are unsuitable, such as controlled cash, precious metal, jewelry,
vehicle, or another independently reviewed source.

The Account holder requests an amount and may add a message or private
evidence. Submission creates a review workflow; it does not immediately change
Client Funds.

Maker-checker controls and system Treasury payment remain operator workflows.
They must not make the Account holder perform Treasury work.

### 7. Funding Activity

Funding Activity is the one durable history surface for all funding methods. It
normalizes:

- QR Ph receipts;
- bank-transfer instructions and checks;
- Pay Code outcomes; and
- reviewed-value requests.

Every row uses the same core grammar:

| Field | Purpose |
| --- | --- |
| **Method** | QR Ph, Bank Transfer, Pay Code, or Reviewed Value |
| **Reference** | Sanitized request or receipt reference |
| **Amount** | Requested, expected, or recognized value as labelled by the row |
| **Status** | Current workflow outcome |
| **Updated** | Most recent relevant time |
| **Action** | Only the next action currently allowed |

Provider transaction identifiers, raw evidence, credentials, unmasked
destinations, and payer identity do not belong in this general read model.

## Status Language

| Status | User interpretation |
| --- | --- |
| **Awaiting Funds** | Instructions exist, but authoritative funds have not been matched. |
| **Checking** | A non-overlapping provider query is in progress. |
| **Observed** | Provider evidence exists but recognition or approval is not complete. |
| **Settled / Funded** | Verified value was posted to the Account. |
| **Suspense** | Evidence was mismatched, ambiguous, or outside an automatic policy. |
| **Recovery** | A reversal or deficit constrains future Issuance Capacity. |
| **Expired** | The instruction can no longer accept an automatic match. |

Status color is supplementary. Text must always carry the meaning.

## Treasury and System-Operator View

An authorized Treasury/system operator may additionally see:

- **Treasury oversight** for cached provider Inventory, liquidity freshness,
  position control, refresh, and reconciliation;
- **Treasury controls → Provider diagnostics** for installed provider posture,
  address-scheme warnings, safeguards, and legacy one-time Funding Intents; and
- exception or approval controls allowed by the server read model.

`can_view_treasury_controls` is the presentation gate for both Treasury
oversight and Provider diagnostics.

An ordinary Account holder must not render:

- Provider Inventory or provider liquidity;
- refresh or reconciliation controls;
- provider installation/readiness diagnostics;
- address-scheme implementation warnings;
- reconciliation queues; or
- legacy one-time Funding Intent history.

Hiding a control is not authorization. Every provider call and Treasury
mutation repeats authorization on the server.

## Visual and Interaction Contract

### Hierarchy

1. The Account Funding command card is the page's primary orientation surface.
2. The active funding method is the primary working surface.
3. Funding Activity is the durable follow-up surface.
4. Reviewed Value is secondary.
5. Treasury and provider diagnostics are role-gated and collapsed.

Do not place engineering boundary prose above the first user action.

### Method Panels

`CockpitFundingMethodPanel.vue` is the shared shell for QR Ph, Bank Transfer,
and Pay Code.

All three panels must retain:

- the same title/action/description header geometry;
- the same border, radius, shadow, and horizontal padding;
- a stable desktop minimum height so changing tabs does not move the page
  abruptly; and
- dark-mode parity.

QR Ph may be taller on a phone because its QR must remain scannable. Do not
shrink the QR merely to force identical mobile panel heights.

### Icons

The accepted icon vocabulary is:

| Control | Icon meaning |
| --- | --- |
| QR Ph | QR code |
| Bank Transfer | Bank/institution |
| Pay Code | Ticket with check |
| Check NetBank / Update QR | Refresh |
| Get bank transfer instructions | Document |
| Check Code | Search |
| Add to Account | Plus |

Icons use consistent sizing and spacing. Loading refresh icons may spin. Icons
remain `aria-hidden`; visible text remains present.

### Buttons and Forms

- Primary actions are at least 44 pixels high.
- Destructive or unavailable states never rely on color alone.
- Loading state disables repeat submission.
- Validation appears next to the relevant field.
- Invalid bank amounts return focus to the amount field and select the value.
- Quick-amount controls use `aria-pressed`.
- Keyboard focus uses a visible focus indicator.
- Buttons keep their label during normal operation; icons never replace text.

### Responsive Behavior

At desktop width:

- the three method tabs share equal width;
- method headers and card floors remain stable;
- form actions may align beside inputs; and
- history tables may use a contained horizontal scroller.

At phone width:

- the three method tabs remain equal height;
- Bank Transfer may wrap to two lines without changing the other tab heights;
- quick amounts use a compact 3+2 grid;
- primary actions span the available width; and
- the page itself must not overflow horizontally.

## State Refresh Contract

The UI may learn about a completed change through:

- the direct response to the user's action;
- Inertia partial reload;
- non-overlapping polling while open work exists; or
- an optional private Laravel Echo event.

These are presentation refresh mechanisms only. Echo, polling, and browser
state never authorize settlement. Accounting commits first; the UI projects the
committed result.

The page must remain usable when broadcasting is disabled. Provider cooldown
and `Retry-After` instructions must prevent repeated **Check NetBank** calls.

## Security and Regulatory Invariants

- A webhook is evidence, not credit authority.
- No client-entered amount, reference, screenshot, mobile number, or merchant
  label independently authorizes Account funding.
- There is no manual Account-credit control in the Account-holder UI.
- Provider credentials and raw payloads never enter general Inertia props.
- A Funding Address is purpose-bound before a payment is observed.
- One provider observation can fund at most one Account outcome.
- Replays do not duplicate Inventory or Account posting.
- The UI uses Account and Client Funds language instead of presenting x-change
  as a general-purpose wallet.
- Automated tests and AI agents never initiate a real-money transfer.

## For Developers

### Package Ownership

The source of truth is package-owned:

- `resources/js/cockpit/pages/Funding.vue`
- `resources/js/cockpit/components/CockpitFundingMethodPanel.vue`
- `resources/js/cockpit/components/CockpitFundingActivity.vue`
- `resources/js/cockpit/types.ts`
- `src/Http/Controllers/Web/Cockpit/CockpitFundingPageController.php`
- `src/Services/Cockpit/FundingActivityCockpitReadModel.php`
- `src/Data/Cockpit/CockpitFundingReadModelData.php`

Paths above are relative to `packages/x-change`.

The host `resources/js/cockpit/**` files are published mirrors. Do not make
product decisions or durable edits only in the host copy.

Frontend-to-backend navigation and submissions use generated Wayfinder route
functions. Do not introduce hard-coded route URLs.

### Primary Verification

The focused presentation tests are:

- `tests/frontend/cockpit/CockpitFundingFoundation.test.ts`
- `tests/frontend/cockpit/CockpitFundingActivity.test.ts`
- `tests/Unit/Architecture/CockpitFundingTreasuryPortfolioPresentationTest.php`

Backend behavior is covered by the Cockpit Funding feature tests under
`tests/Feature/Cockpit`.

Every UI change must verify:

1. the focused frontend tests;
2. the relevant PHP feature or architecture tests;
3. package/host published-asset parity;
4. the production frontend build;
5. `git diff --check`; and
6. browser acceptance at desktop and phone widths.

Browser acceptance checks interaction, layout, and console errors. It must not
perform a real provider payment.

## For AI Agents

Before changing this page:

1. Read this guide.
2. Read
   [Funding Account Management](../architecture/FUNDING_ACCOUNT_MANAGEMENT.md)
   for settlement authority and accounting boundaries.
3. Read
   [Standing Funding Address Protocol](../architecture/STANDING_FUNDING_ADDRESS_PROTOCOL.md)
   when changing QR Ph or provider observation behavior.
4. Inspect the package source and its focused tests before editing.

AI agents must preserve these rules:

- Keep the Account-holder journey shorter than the Treasury/operator journey.
- Do not expose Provider diagnostics to an ordinary Account holder.
- Do not duplicate global Account-position metrics in the page body.
- Keep QR Ph, Bank Transfer, and Pay Code visually parallel.
- Keep Funding Activity as the single normalized history.
- Do not collapse inspection and confirmation into one Pay Code action.
- Do not turn QR presentation metadata into routing or settlement data.
- Do not create a manual credit path for convenience.
- Do not treat Echo, polling, a webhook, or a screenshot as settlement
  authority.
- Make durable changes in `packages/x-change`, publish the host mirror
  mechanically, and commit tested slices separately.
- Preserve unrelated worktree changes.

If a requested visual simplification would weaken authorization, evidence,
idempotency, or accounting boundaries, stop and explain the conflict instead
of implementing the shortcut.

## Acceptance Checklist

### Account holder

- [ ] Sees Client Funds, Outstanding Pay Codes, and Issuance Capacity.
- [ ] Sees QR Ph, Bank Transfer, and Pay Code as the primary methods.
- [ ] Can understand the next action without reading architecture prose.
- [ ] Can select a common Bank Transfer amount and edit it immediately.
- [ ] Can inspect a Pay Code without accidentally adding it.
- [ ] Can find every request or receipt in Funding Activity.
- [ ] Does not see provider liquidity, Provider Inventory, Treasury controls,
      Provider diagnostics, or reconciliation queues.

### Treasury/system operator

- [ ] Sees the same Account Funding journey.
- [ ] May open Treasury oversight when authorized.
- [ ] May open Treasury controls → Provider diagnostics when authorized.
- [ ] Cannot bypass server authorization through the visible controls.

### Responsive and accessible

- [ ] No document-level horizontal overflow at 1440×1000 or 390×844.
- [ ] Three method tabs retain equal dimensions.
- [ ] Method headers do not jump when changing tabs.
- [ ] QR remains scannable on a phone.
- [ ] Primary actions remain at least 44 pixels high.
- [ ] Icons are decorative and labels remain available.
- [ ] Keyboard focus and validation placement are visible.
- [ ] Dark mode preserves hierarchy and contrast.
- [ ] No browser console errors occur during the primary journeys.

## Change Record

The 2026-07-27 accepted pass established:

- the consolidated Account Funding command card;
- summary-state placement inside that card;
- visually parallel QR Ph, Bank Transfer, and Pay Code panels;
- common funding-method descriptions, dimensions, and icons;
- Bank Transfer quick amounts;
- immediate reusable QR presentation and compact merchant-label editing;
- Funding Activity as the normalized history surface; and
- complete removal of Treasury controls and Provider diagnostics from the
  ordinary Account-holder DOM.
