# Quick Generate — Order Card Decluttering, Cycle 1

Date: August 22, 2026  
Status: Implemented, released, and deployed to Laravel Cloud testing  
Package release: `3neti/x-change v1.0.0-beta.241`

## Outcome

The Quick Generate Order card now keeps its primary ordering controls visible and places its four secondary controls inside a collapsed-by-default **Order options** disclosure. The same release fixes the narrow-screen Order header overlap and the horizontal overflow in Claim Experience step rows.

The result is available for review at [Laravel Cloud testing — Quick Generate](https://x-change-testing-testing-uw1gvj.laravel.cloud/x/cockpit/quick-generate).

## Implemented behavior

The following controls remain immediately visible in the Order card:

- Amount
- Pay To
- Purpose
- Templates toolbar
- Issue Pay Code button
- Pay Code / Invitation mode toggle and classification badge

The following controls now live inside **Order options**:

- Claim Requirements
- Status Updates
- Value Use
- Transfer Network

The disclosure is collapsed by default on desktop and mobile. Its badge counts how many of those four control groups have non-default configuration. A configured group contributes one to the count regardless of how many values it contains.

The disclosure trigger is a native button with `aria-expanded` and `aria-controls`; it therefore supports click, Enter, and Space without custom keyboard handling. The panel is exposed as a labelled region.

At 320 px, the Order header stacks rather than allowing the title and Issue button to overlap. Claim Experience summary rows and their enclosing grid boundaries now permit text to shrink and wrap without widening the page.

The existing desktop grid ratio was deliberately preserved:

```text
xl:grid-cols-[minmax(19rem,0.74fr)_minmax(28rem,1.26fr)]
```

The Templates toolbar remains horizontally scrollable when its actions exceed the available width. It does not hard-clip the Save Template action.

## Source changes

Primary implementation:

- [`CockpitQuickGenerateSubmitPanel.vue`](../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue)

Focused frontend coverage:

- [`CockpitQuickGenerateFoundation.test.ts`](../../../tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts)
- [`CockpitQuickGenerateOrderHelp.test.ts`](../../../tests/frontend/cockpit/CockpitQuickGenerateOrderHelp.test.ts)
- [`CockpitQuickGenerateClaimRequirements.test.ts`](../../../tests/frontend/cockpit/CockpitQuickGenerateClaimRequirements.test.ts)
- [`CockpitQuickGenerateInvitationMode.test.ts`](../../../tests/frontend/cockpit/CockpitQuickGenerateInvitationMode.test.ts)
- [`CockpitQuickGenerateValueUse.test.ts`](../../../tests/frontend/cockpit/CockpitQuickGenerateValueUse.test.ts)

New stable test identifiers:

- `cockpit-quick-generate-order-options-toggle`
- `cockpit-quick-generate-order-options-panel`

No existing test identifiers were renamed or removed.

## Review artifacts

The screenshots below are workspace-local evidence captured from the implemented UI. Select a caption or image to inspect the original PNG.

### Laravel Cloud — mobile, 320 px

[Collapsed Order card](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/cloud-mobile-320-order-collapsed.png>)

![Cloud mobile 320 px — collapsed Order card](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/cloud-mobile-320-order-collapsed.png>)

[Expanded Order options](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/cloud-mobile-320-order-options-expanded.png>)

![Cloud mobile 320 px — expanded Order options](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/cloud-mobile-320-order-options-expanded.png>)

### Laravel Cloud — mobile, 375 px

[Claim Experience rows without horizontal overflow](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/cloud-mobile-375-claim-experience.png>)

![Cloud mobile 375 px — Claim Experience](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/cloud-mobile-375-claim-experience.png>)

### Desktop

[Collapsed Order card](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/desktop-order-collapsed.png>)

![Desktop — collapsed Order card](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/desktop-order-collapsed.png>)

[Expanded Order options](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/desktop-order-options-expanded.png>)

![Desktop — expanded Order options](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/desktop-order-options-expanded.png>)

### Additional local narrow-screen evidence

- [Local mobile 320 px — collapsed Order card](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/mobile-320-order-collapsed.png>)
- [Local mobile 320 px — expanded Order options](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/mobile-320-order-options-expanded.png>)
- [Local mobile 320 px — Claim Experience](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/mobile-320-claim-experience.png>)
- [Local mobile 375 px — Claim Experience](</Users/rli/.codex/visualizations/2026/07/21/019f82a7-0719-7ba0-b041-f30bfa06eb7d/quick-generate-cycle-1/mobile-375-claim-experience.png>)

## Browser verification

The implementation was exercised locally and after deployment.

| View | Evidence |
|---|---|
| Cloud, 320 px, collapsed | Document client width and scroll width both 320 px |
| Cloud, 320 px, expanded | Disclosure expanded correctly; document remained 320 px wide |
| Cloud, 375 px, Claim Experience | Document client width and scroll width both 375 px; builder measured 307/307 px and step rows 273/273 px |
| Desktop | Collapsed and expanded states inspected; established desktop grid ratio retained |
| Templates toolbar | `clientWidth=363`, `scrollWidth=466`, `overflow-x:auto`; overflow degrades to scrolling |

## Automated verification

- Focused regression set: **115 tests passed across 6 files**.
- Full Cockpit frontend suite: **487 tests passed across 58 files**.
- Production frontend build: passed.
- `git diff --check`: passed.
- Laravel Cloud strict doctor: passed with exit code 0.
- Cloud package verification: `3neti/x-change v1.0.0-beta.241`, source `e4550138f293134f812cd94cd4513d43a113fe7b`.

The broader repository frontend run reported 996 of 1004 tests passing. The one Order-options-related Value Use expectation was corrected. The seven remaining failures were pre-existing and outside this cycle, in claim logo, destination, and countdown coverage; the scoped Cockpit suite is green.

## Release and deployment evidence

Package commits:

- [`0dcce878` — Declutter Quick Generate order options](https://github.com/3neti/x-change/commit/0dcce878)
- [`3592eca8` — Constrain Claim Experience on narrow screens](https://github.com/3neti/x-change/commit/3592eca8)
- [`e4550138` — Stack Rider design controls on mobile](https://github.com/3neti/x-change/commit/e4550138)

Release:

- [`v1.0.0-beta.241`](https://github.com/3neti/x-change/releases/tag/v1.0.0-beta.241)

Host integration:

- [`c852597e` — Upgrade Quick Generate Order options](https://github.com/3neti/x-change-sandbox/commit/c852597e)

Laravel Cloud:

- Environment: `x-change-testing/testing`
- Deployment: `depl-a28f0ef7-622b-4208-94d3-e17108ba893b`
- Result: succeeded
- Duration: 3 minutes 44 seconds

## Deliberate deviations and clarifications

1. The `min-w-0` correction was applied not only to the text wrapper named in the brief, but also to the relevant enclosing Claim Experience grid/detail boundaries. Browser inspection showed that fixing only the summary wrapper still allowed the grid's min-content width to expand to roughly 488 px.
2. The Rider Design action was also allowed to stack on narrow screens after the 320 px browser pass exposed the same overlap class there.
3. **Whole amount** is treated as the default Value Use state. Only Equal, Flexible, Scheduled, or Reusable Balance contributes to the Order options badge.
4. The desktop Order/preview column ratio was not changed.

## Review notes

- A default mobile claim requirement can legitimately make the collapsed badge show `1`; a blank or repeat-last Cloud state can show `0`. The badge reflects actual active configuration rather than a fixed visual default.
- Screenshot paths are local to this Codex workspace. The source, release, host, and Cloud links are independently inspectable.
- No unresolved UX ruling blocked this cycle. Wording remains **Order options**, using the existing disclosure iconography and a compact count badge.
