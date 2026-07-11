# Cockpit Wave 34B — Post-Issuance Navigation Read Model Contract

## Mission

Add an explicit operator-safe read-model contract for Quick Generate post-issuance navigation.

## Added

- `CockpitQuickGeneratePostIssuanceNavigationData`.
- `CockpitQuickGeneratePostIssuanceNavigationItemData`.
- `CockpitQuickGenerateReadModelData::post_issuance_navigation`.

## Contract

The contract can represent read-only links for:

- voucher detail;
- Distribution Workspace;
- future Explorer context links.

It also records that automatic redirects are disabled.

## Boundaries

This slice adds no UI rendering and no runtime mutation. It does not dispatch feedback, generate QR/short links, generate print artifacts, execute drivers, write journal entries, execute actions, mutate campaigns, call providers, move money, or expose unsafe payloads.

## Expected UI Result

No visible UI change until hydration and Vue adoption.

## Verification

- `php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/CockpitQuickGeneratePostIssuanceNavigationContractTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php --filter='quick generate|post issuance'`

## Next Slice

Cockpit Wave 34C — Quick Generate Result Handoff Hydration.
