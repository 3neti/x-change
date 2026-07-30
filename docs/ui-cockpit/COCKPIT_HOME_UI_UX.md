# Cockpit Home UI/UX Contract

## Purpose

`/x/cockpit` is the operating overview for an authenticated x-change Account.
It answers three questions, in this order:

1. What can I do?
2. What needs my attention?
3. What happened recently?

It is a control surface, not a marketing page, provider console, Treasury
ledger, or duplicate of the Funding and Pay Codes workspaces.

## Information Architecture

### Global Account facts

The shared Cockpit header remains the single place for Client Funds,
Outstanding Pay Codes, Issuance Capacity, and provider-liquidity facts
permitted for the current operator. The page body must not repeat those
figures.

### Controls

The first working surface contains four stable destinations:

- **Create** — design and issue a Pay Code.
- **Funding** — add and confirm Account funds.
- **Pay Codes** — inspect issued Pay Codes and their lifecycle.
- **Campaigns** — prepare and operate controlled batches.

These are navigation controls. They must remain keyboard accessible,
responsive, and visually comparable in weight.

### Operational Horizon

The horizon is a compact scan of:

- Pay Codes;
- claim progress;
- campaigns; and
- items needing attention.

It summarizes already-sanitized read models. It must not query a provider,
mutate money, or imply that a count is authoritative beyond its source
projection.

### Attention

Attention contains only actionable or exceptional items. Empty integration
placeholders and routine readiness copy must not compete with real work.
When no action is needed, the page says so plainly.

### Recent Activity

Recent Activity is one chronological operating log composed from the existing
sanitized issuance and dashboard activity projections. It does not introduce
a new ledger or audit source.

### Contextual guidance

First-use guidance appears only when there is no operational history. A pinned
campaign appears only when the campaign read model supplies one. Neither
occupies permanent dashboard space without context.

### System Status

Connected-service summaries, integration readiness, liquidity diagnostics,
pipeline detail, risk detail, and technical payload facts live inside one
secondary **System Status** disclosure. They remain available for support and
authorized operators without dominating the ordinary Account experience.

## Language

- Use **Cockpit** for the home destination.
- Use **Account**, **Client Funds**, and **Issuance Capacity** in ordinary UI.
- Avoid `wallet`, implementation names, DTO names, payload sales language, and
  duplicated read-only disclaimers.
- State a blocked or unavailable condition directly and offer a useful route
  when one exists.

## Interaction and Safety

- The dashboard route remains read-only.
- All mutations happen in their dedicated workspaces.
- Navigation uses Inertia links and package-owned routes.
- No provider call occurs on page load.
- No raw provider payload, credential, full destination, claimant payload, or
  unsafe metadata is exposed.
- System Status is disclosure, not authority to execute an operation.

## Responsive Behavior

- Controls form a compact grid on wide screens and remain comfortable touch
  targets on mobile.
- Horizon facts wrap without forcing horizontal scrolling.
- Attention and activity become a single column before their content becomes
  cramped.
- Primary navigation remains above diagnostic disclosure.
- Light and dark themes preserve the same hierarchy and contrast.

## Acceptance Contract

The focused frontend suite must prove:

- the four controls and their destinations render;
- the operational horizon renders from sanitized props;
- actionable attention is separated from placeholders;
- recent issuance and operational activity share one visible stream;
- contextual guidance is conditional;
- technical panels remain available under System Status;
- legacy read-model props remain compatible; and
- unsafe payload details are not introduced.

Package assets must be published, pass the x-change asset diagnostic, and
complete the production build before visual acceptance is closed.
