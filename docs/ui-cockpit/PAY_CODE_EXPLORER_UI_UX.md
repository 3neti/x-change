# Pay Code Explorer UI/UX Guide

- Status: accepted product and implementation contract
- Route: `/x/cockpit/pay-codes`
- Source owner: `3neti/x-change`
- Last reviewed: 2026-07-27

## Purpose

The Pay Code Explorer is a scan-first, read-only workspace for finding Pay
Codes and opening their detail or distribution workspaces. It follows the same
Cockpit grammar as Account Funding: one clear command surface, compact facts,
quiet secondary context, and durable records immediately below the controls.

The page should answer five questions quickly:

1. How many Pay Codes exist and how many need attention?
2. Which Pay Code am I looking for?
3. What can it do, which instructions govern it, and who is it for?
4. What is its amount and lifecycle state?
5. Which safe, read-only workspace can I open next?

It does not issue, claim, cancel, deliver, or mutate a Pay Code.

## Audience

This guide has three audiences:

- **Account holders and operators** use the first half as the product guide.
- **Developers** use the implementation map and acceptance contract.
- **AI agents** use the explicit invariants and change protocol.

The Pay Code lifecycle and accounting rules remain outside this presentation
contract. This guide governs what the Explorer shows, how it behaves, and which
boundaries it must preserve.

## Product Vocabulary

| Term | Meaning in the UI |
| --- | --- |
| **Pay Code** | The human-facing identifier for an issued voucher. |
| **Capability** | Whether the Pay Code supports disbursement, collection, or bidirectional settlement. |
| **Instructions** | Safe operational summaries of the controls attached to the Pay Code. |
| **Target** | The masked intended party before a claim. |
| **Claimed by** | The masked party summary after redemption. |
| **Lifecycle status** | The normalized voucher state used by the result row and status filter. |
| **Needs attention** | Pending, failed, or awaiting-approval Pay Codes. |
| **Distribution** | The read-only workspace for inspecting and manually copying a beneficiary URL. |

The Explorer uses **Pay Code** as product language. Internal model names such as
`Voucher`, complete `VoucherInstructionsData`, provider payloads, and claim
payloads do not become general list-page vocabulary.

## For the Account Holder or Operator

### Page Anatomy

```text
Global Account position
    ↓
Pay Codes command card
    ├─ Total | Active | Redeemed | Needs attention
    └─ Search | Status | Search | Quick Generate
    ↓
Optional campaign or activity context
    ↓
Pay Code results
    ↓
Explorer details
    └─ Technical details, when authorized
```

The global Cockpit header remains the source of Account-level Client Funds,
Outstanding Pay Codes, and Issuance Capacity. The Explorer does not repeat
those metrics in its page body.

### 1. Command Card

The title, read-only badge, Quick Generate action, four lifecycle facts, and
search controls live in one compact card. They must not be repeated in separate
summary and filter cards.

**Needs attention** means pending, failed, or awaiting approval. Expired Pay
Codes are a normal terminal state and do not inflate the attention count.

Search is read-only GET navigation. Active filter chips and the clear action
appear only while a search or status filter is active. Search and action icons
are decorative; the visible text remains the accessible name.

### 2. Search and Status Filters

Text search and lifecycle filtering deliberately use different interaction
timing:

- typing in the search field does not send a request;
- **Search** applies the current text and selected status;
- changing **Status** applies immediately;
- a status change preserves the text currently typed in the search field;
- campaign context remains attached to either operation; and
- **Clear** removes the Explorer search and status while retaining valid
  campaign orientation context.

The immediate status change uses an Inertia partial GET reload for
`pay_codes_read_model`. It preserves scroll and page state and replaces the
current browser-history entry. It never posts a form or mutates a Pay Code.

The accepted status vocabulary is:

| Filter | Meaning |
| --- | --- |
| **All** | No lifecycle constraint. |
| **Awaiting Approval** | A required approval remains unresolved. |
| **Active** | The Pay Code is currently available or claimable. |
| **Locked** | The Pay Code is unavailable while a lock is in force. |
| **Redeemed** | Redemption has completed. |
| **Expired** | The permitted validity period has elapsed. |
| **Pending** | The Pay Code has not yet reached an active state. |
| **Cancelled** | The Pay Code was explicitly cancelled. |
| **Closed** | The lifecycle has been explicitly closed. |
| **Failed** | The lifecycle reports a failed outcome requiring inspection. |

The server owns the option list and active state. Unknown status query values
normalize to **All** instead of becoming an untrusted or client-defined filter.

### 3. Context

Campaign and operator-activity context render only when present. Each appears
as a slim banner with its primary return action visible and supporting metadata
inside a disclosure. Context must never displace search or results as the
primary task.

Campaign identifiers may be preserved across Explorer, detail, and distribution
navigation. They are orientation metadata only and never authorize access or
change the Pay Code query scope.

### 4. Results

The results surface presents:

- Pay Code, template, and capability;
- up to three allowlisted instruction badges, followed by `+N` when more
  instructions exist;
- amount and lifecycle status;
- the masked target before claim or the masked claimant summary after claim;
  and
- safe row actions.

Desktop uses a compact table; smaller viewports use cards with the same facts
and action destinations. Amounts use tabular numerals. Status remains text
inside the badge, so color is never the only signal.

The capability vocabulary is:

- **Disbursement · Redeemable** for one-way beneficiary disbursement;
- **Collection · Payable** for collection; and
- **Settlement · Bidirectional** for vouchers that can participate in both
  directions.

Instruction badges are semantic summaries owned by `3neti/voucher`, not a
serialization of `VoucherInstructionsData`. Examples include Mobile-bound,
Vendor-bound, OTP, Selfie, Signature, Location, Divisible, InstaPay, and Account
funding. Mobile numbers, vendor binding values, secrets, webhook destinations,
and provider execution payloads never appear inside the badges.

The party column is labeled **Target / Claimed by**. Before claim, it shows a
vendor alias, masked mobile, or Open claim. After claim, it prefers the contact
name with a masked mobile beneath it. Full mobile numbers are never included in
the Cockpit list projection.

Created, start, expiry, and redemption dates remain available in each row's
**More** disclosure. They do not occupy a primary scan column.

The header shows the total, current visible range, and page-size control.
Previous and next controls remain in the footer where they are available after
scanning the rows. The page-size choices are 10, 25, and 50, with 25 as the
default. This is presentation paging over the authorized, filtered read model;
changing page or page size does not call a provider or mutate server state.

Empty, unavailable, and unauthorized states are explicit. A missing row must
not be replaced with synthetic production data.

### 5. Row Information Hierarchy

Desktop rows use six primary columns:

1. **Pay Code**
2. **Instructions**
3. **Amount**
4. **Status**
5. **Target / Claimed by**
6. **Actions**

The Pay Code column shows the code first, then its template and capability.
Instructions show no more than three allowlisted badges before a `+N` overflow
summary. Amount uses tabular numerals. Status is always visible as text.

The primary enabled row actions are:

- **View details** for the read-only lifecycle workspace; and
- **Distribution** for beneficiary-link inspection and browser-local copy.

Unavailable Timeline or Notify actions may remain visible inside **More** with
their reason. They must not look executable. Created, start, expiry, and
redemption dates also stay inside **More** so chronology remains available
without becoming a primary scan column.

### 6. Mobile Results

At smaller viewports, each result becomes a card using the same information
order as the desktop row:

- code, template, and capability;
- instruction badges;
- amount and status;
- target or claimant;
- enabled actions; and
- secondary facts under **More**.

Mobile is not a reduced-data mode. The same safe facts and destinations remain
available without document-level horizontal overflow.

### 7. Secondary Details

One collapsed **Explorer details** disclosure holds filter metadata, list
totals, row-action guidance, and sanitized read-model boundaries. These facts
remain inspectable without competing with the working surface.

**Technical details** are visible only to an authorized System Treasury
principal. The server omits both the diagnostic read model and its permission
for ordinary Account holders; hiding markup on the client is not an
authorization boundary. Ordinary Account holders receive only Pay Codes owned
by their exact issuer morph and identifier. System Treasury may receive the
broader operational inventory.

## Capability and Instruction Grammar

Capability is orthogonal to lifecycle status. A Pay Code may be active,
expired, or redeemed regardless of whether its capability is disbursement,
collection, or settlement.

The accepted labels are:

| Capability key | Explorer label | Voucher type |
| --- | --- | --- |
| `disbursement` | **Disbursement** | **Redeemable** |
| `collection` | **Collection** | **Payable** |
| `settlement` | **Settlement** | **Bidirectional** |

Instruction badges are produced from an allowlist. Accepted examples include:

- Mobile-bound
- Vendor-bound
- Account funding
- InstaPay / PESONet
- Divisible
- Inputs
- OTP
- Selfie
- Signature
- Location
- Face match
- Time-bound

Badges state that a control exists. They do not reveal its configured value,
secret, destination, complete rule set, or raw instruction object. Adding a new
instruction to the voucher DTO does not automatically make it visible in the
Explorer.

## Party Presentation

The list provides only the minimum party information needed to distinguish a
Pay Code:

- a vendor alias when the Pay Code is vendor-bound;
- a masked mobile when it is mobile-bound;
- **Open claim** when no target is bound; or
- a safe claimant name with a masked mobile after redemption, when available.

Full mobile numbers and private claim material never enter the list read model.
Masking is performed before rendering and is not delegated to CSS or visual
clipping.

## Visual and Interaction Contract

### Hierarchy

1. The command card is the primary orientation and filtering surface.
2. Campaign or activity context is conditional and compact.
3. Results begin immediately after the working controls.
4. Row facts are optimized for scanning, not exhaustive inspection.
5. Explorer and technical metadata remain collapsed and secondary.

### Controls

- Search, status, row actions, pagination, and page size have visible text
  labels.
- Icons are decorative and use `aria-hidden`.
- Focus indicators remain visible in light and dark modes.
- Loading status disables repeated lifecycle-filter requests.
- The status control exposes a polite busy announcement.
- Active filter chips summarize applied state but are not the only indication
  of that state.
- Color supplements capability and status text; it never replaces it.

### Responsive Behavior

At desktop width:

- the search field receives the flexible width;
- the status control retains a stable usable width;
- the result table exposes all six scan columns; and
- row actions remain compact.

At phone width:

- search, status, and actions stack without clipping;
- result cards replace the table;
- primary row actions remain easy to tap;
- disclosures remain keyboard accessible; and
- the page itself must not overflow horizontally.

## State Refresh Contract

The Explorer is request-driven. It does not require Echo, polling, WebSockets,
or a provider call.

- Search submission receives a fresh authorized read model.
- Status change receives a partial authorized read model.
- Client-side page size and pagination only rearrange the rows already
  received.
- Returning from detail or distribution may perform normal Inertia navigation.

The read model is always the source of truth. Browser state may preserve a
typed query for continuity, but it never broadens authorization or invents a
record.

## Authorization, Privacy, and Safety

- Ordinary Account holders receive only Pay Codes owned by their exact issuer
  morph type and identifier.
- Authorized System Treasury users may receive the broader operational
  inventory and technical details.
- Server authorization decides both query scope and technical-detail props.
- The list projection is `sanitized-list-summary-only`.
- Instruction output is `allowlisted-operational-badges-only`.
- Party output is `masked-contact-summary-only`.
- Complete instructions, claim payloads, provider payloads, credentials,
  Account positions, and raw evidence remain excluded.
- No provider call occurs because the Explorer is opened or filtered.
- No journal entry, delivery, execution, claim, Account posting, or money
  movement originates here.

Hiding markup is not authorization. Every linked workspace repeats its own
server authorization.

## Interaction and Safety Contract

- Quick Generate is the only visible creation handoff.
- Search and filtering change only the displayed read model.
- Detail and distribution links open read-only workspaces.
- Unavailable actions remain inspectable without looking enabled.
- No provider call occurs because this page is opened.
- No journal entry, delivery, execution, claim, Account posting, or money
  movement originates here.

## For Developers

### Package Ownership

Package-owned sources:

- `resources/js/cockpit/pages/PayCodeExplorer.vue`
- `resources/js/cockpit/components/CockpitPayCodeSearchBar.vue`
- `resources/js/cockpit/components/CockpitPayCodeFilterBuilder.vue`
- `resources/js/cockpit/components/CockpitPayCodeResultsTable.vue`
- `resources/js/cockpit/payCodeExplorerDefaults.ts`
- `resources/js/cockpit/types.ts`
- `src/Http/Controllers/Web/Cockpit/CockpitPayCodeExplorerPageController.php`
- `src/Support/Cockpit/CockpitReadOnlyPageProps.php`
- `src/Services/VoucherAccessService.php`
- `src/Services/VoucherLifecycleService.php`
- `src/Services/Cockpit/VoucherLifecycleCockpitReadModelProvider.php`
- `src/Data/Cockpit/CockpitPayCodeCapabilityData.php`
- `src/Data/Cockpit/CockpitPayCodeInstructionBadgeData.php`
- `src/Data/Cockpit/CockpitPayCodePartyData.php`
- `src/Data/Cockpit/CockpitPayCodeTimingData.php`
- `src/Data/Cockpit/CockpitPayCodeListRecordData.php`
- `src/Data/Cockpit/CockpitPayCodeListReadModelData.php`

The semantic capability and badge mapper is package-owned by:

- `3neti/voucher/src/Data/VoucherOperationalSummaryData.php`

The host files under `resources/js/cockpit` are generated mirrors. Change the
package source first, publish it, and run the asset drift check.

Frontend navigation to the page controller uses generated Wayfinder functions.
Do not add a second hard-coded endpoint for reactive status filtering.

### Read-Model Flow

```text
3neti/voucher operational summary
    ↓
x-change authorization and list projection
    ↓
sanitized Inertia pay_codes_read_model
    ↓
PayCodeExplorer.vue
    ├─ search and status controls
    └─ desktop rows / mobile cards
```

The semantic summary from `3neti/voucher` is provider-neutral. `3neti/x-change`
owns Account authorization, lifecycle filtering, party masking, route actions,
and the Cockpit presentation DTOs.

### Change Rules

- Add lifecycle options in the server-owned status option list.
- Normalize unknown status values to **All**.
- Keep status filtering as read-only GET navigation.
- Keep free-text search explicit; do not turn every keystroke into a request.
- Preserve valid campaign context across search, filter, clear, detail, and
  distribution navigation.
- Add new instruction pills only through the voucher operational-summary
  allowlist.
- Mask party data before it reaches Vue.
- Keep dates and unavailable actions in the row disclosure.
- Make durable changes in `packages/x-change`; publish host mirrors
  mechanically.

### Primary Verification

Focused backend coverage includes:

- `tests/Unit/Cockpit/CockpitPayCodeExplorerProviderParityTest.php`
- `tests/Unit/Cockpit/CockpitPayCodeExplorerReadModelContractTest.php`
- the Pay Code Explorer cases in
  `tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php`
- `tests/Unit/Architecture/PayCodeExplorerVoucherListRationalizationTest.php`

Focused frontend coverage includes the Pay Code Explorer tests under
`tests/frontend/cockpit`, especially hydration, foundation, campaign context,
operational rows, and reactive status filtering.

Every UI change must verify:

1. the focused frontend tests;
2. the relevant PHP feature, unit, and architecture tests;
3. package/host published-asset parity;
4. the production frontend build;
5. `git diff --check`; and
6. browser acceptance at desktop and phone widths when browser control is
   available.

Browser acceptance checks interaction, responsive layout, and console errors.
It must not claim, deliver, cancel, or otherwise mutate a real Pay Code.

## For AI Agents

Before changing this page:

1. Read this guide.
2. Inspect the package page, components, read-model provider, and focused
   tests.
3. Inspect `3neti/voucher` operational-summary mapping before changing
   capability or instruction vocabulary.
4. Confirm whether the requested behavior belongs in Explorer, detail,
   distribution, Quick Generate, or the public claim experience.

AI agents must preserve these rules:

- Keep the Explorer scan-first and read-only.
- Do not duplicate the global Account-position metrics.
- Keep text search explicit and lifecycle status reactive.
- Keep all supported lifecycle states, including **Locked**, selectable.
- Do not expose raw `VoucherInstructionsData` as pills.
- Do not expose full mobile numbers or private claimant data.
- Do not move dates back into primary scan columns.
- Do not make disabled Timeline or Notify actions appear enabled.
- Do not show System Treasury diagnostics to ordinary Account holders.
- Do not make a provider call, execute a driver, or move money from this page.
- Use package source and generated Wayfinder routes.
- Publish the host mirror and commit tested slices separately.
- Preserve unrelated worktree changes.

If a visual simplification would weaken authorization, masking, redaction, or
the read-only boundary, stop and explain the conflict instead of implementing
the shortcut.

## Acceptance Checklist

### Account holder or operator

- [ ] Sees one compact command card with Total, Active, Redeemed, and Needs
      attention.
- [ ] Can search by code, recipient, amount, campaign context, or status.
- [ ] Search waits for **Search**; typing alone makes no request.
- [ ] Changing lifecycle status refreshes the results immediately.
- [ ] Can select All, Awaiting Approval, Active, Locked, Redeemed, Expired,
      Pending, Cancelled, Closed, and Failed.
- [ ] Sees capability, instruction summaries, amount, lifecycle status, and
      masked party at a glance.
- [ ] Can open read-only detail or distribution.
- [ ] Can inspect dates and unavailable actions under **More**.
- [ ] Can clear Explorer filters without losing valid campaign orientation.

### Authorization and privacy

- [ ] Ordinary Account holders see only their own issued Pay Codes.
- [ ] Authorized System Treasury may see the broader operational inventory.
- [ ] Full mobile numbers, complete instructions, claim payloads, provider
      payloads, credentials, and Account positions are absent.
- [ ] Technical details are absent from the ordinary Account-holder props and
      DOM.

### Responsive and accessible

- [ ] No document-level horizontal overflow at 1440×1000 or 390×844.
- [ ] Desktop uses the six-column scan hierarchy.
- [ ] Mobile cards retain the same safe information and actions.
- [ ] Status and capability do not rely on color alone.
- [ ] Icons are decorative and labels remain available.
- [ ] Keyboard focus and busy states are visible.
- [ ] Dark mode preserves hierarchy and contrast.
- [ ] No browser console errors occur during the primary journey.

## Acceptance Contract

Every material change must verify:

1. authorized and unauthorized Inertia props;
2. hydrated, empty, filtered, campaign-context, and activity-context states;
3. desktop table and mobile card layouts;
4. search, clear, page-size, pagination, detail, and distribution controls;
5. redeemable, payable, and settlement capability labels;
6. targeted, open, and claimed party presentations with mobile masking;
7. instruction badge overflow and date disclosure behavior;
8. dark-mode-compatible contrast and visible focus states;
9. package/host asset parity;
10. TypeScript, focused frontend tests, focused Pest tests, formatting, and the
    production build; and
11. browser acceptance at desktop and mobile widths when browser control is
    available.

## Change Record

The 2026-07-27 accepted pass established:

- one compact command card for title, summary, search, status, and Quick
  Generate;
- immediate lifecycle filtering with explicit free-text search;
- complete lifecycle options, including Locked and terminal states;
- capability and allowlisted instruction summaries;
- masked target and claimant presentation;
- six-column desktop rows and information-equivalent mobile cards;
- dates and unavailable actions under **More**;
- client-side density and paging controls;
- collapsed Explorer metadata and role-gated technical details; and
- package-owned source with mechanically published host mirrors.
