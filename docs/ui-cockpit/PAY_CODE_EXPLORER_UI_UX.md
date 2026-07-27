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

The page should answer four questions quickly:

1. How many Pay Codes exist and how many need attention?
2. Which Pay Code am I looking for?
3. What is its amount and lifecycle state?
4. Which safe, read-only workspace can I open next?

It does not issue, claim, cancel, deliver, or mutate a Pay Code.

## Page Anatomy

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

## Command Card

The title, read-only badge, Quick Generate action, four lifecycle facts, and
search controls live in one compact card. They must not be repeated in separate
summary and filter cards.

**Needs attention** means pending, failed, or awaiting approval. Expired Pay
Codes are a normal terminal state and do not inflate the attention count.

Search is read-only GET navigation. Active filter chips and the clear action
appear only while a search or status filter is active. Search and action icons
are decorative; the visible text remains the accessible name.

## Context

Campaign and operator-activity context render only when present. Each appears
as a slim banner with its primary return action visible and supporting metadata
inside a disclosure. Context must never displace search or results as the
primary task.

## Results

The results surface presents:

- Pay Code;
- amount;
- type or template;
- lifecycle status;
- created and expiry dates; and
- safe row actions.

Desktop uses a compact table; smaller viewports use cards with the same facts
and action destinations. Amounts use tabular numerals. Status remains text
inside the badge, so color is never the only signal.

The header shows the total, current visible range, and page-size control.
Previous and next controls remain in the footer where they are available after
scanning the rows. Empty and unauthorized states are explicit.

## Secondary Details

One collapsed **Explorer details** disclosure holds filter metadata, list
totals, row-action guidance, and sanitized read-model boundaries. These facts
remain inspectable without competing with the working surface.

**Technical details** are visible only to an authorized System Treasury
principal. The server omits both the diagnostic read model and its permission
for ordinary Account holders; hiding markup on the client is not an
authorization boundary. Raw provider payloads, credentials, Account positions,
and claim payloads are never included.

## Interaction and Safety Contract

- Quick Generate is the only visible creation handoff.
- Search and filtering change only the displayed read model.
- Detail and distribution links open read-only workspaces.
- Unavailable actions remain inspectable without looking enabled.
- No provider call occurs because this page is opened.
- No journal entry, delivery, execution, claim, Account posting, or money
  movement originates here.

## Implementation Map

Package-owned sources:

- `resources/js/cockpit/pages/PayCodeExplorer.vue`
- `resources/js/cockpit/components/CockpitPayCodeSearchBar.vue`
- `resources/js/cockpit/components/CockpitPayCodeFilterBuilder.vue`
- `resources/js/cockpit/components/CockpitPayCodeResultsTable.vue`
- `src/Http/Controllers/Web/Cockpit/CockpitPayCodeExplorerPageController.php`
- `src/Support/Cockpit/CockpitReadOnlyPageProps.php`

The host files under `resources/js/cockpit` are generated mirrors. Change the
package source first, publish it, and run the asset drift check.

## Acceptance Contract

Every material change must verify:

1. authorized and unauthorized Inertia props;
2. hydrated, empty, filtered, campaign-context, and activity-context states;
3. desktop table and mobile card layouts;
4. search, clear, page-size, pagination, detail, and distribution controls;
5. dark-mode-compatible contrast and visible focus states;
6. package/host asset parity;
7. TypeScript, focused frontend tests, focused Pest tests, formatting, and the
   production build; and
8. browser acceptance at desktop and mobile widths when browser control is
   available.

