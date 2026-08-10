# Hand-off: Payout Destination Icon Library → Claim UX Scaffolding

For the agent scaffolding the claim UX. This is a status brief on the icon
library just merged to `main` (commit "Add payout-destination icon library
for claim UX"), so you can consume it while building out claim screens
rather than rediscovering it.

## What exists now

A real icon library for every payout-route segment (`x-change → NetBank →
InstaPay → GCash → account`), already wired into the existing
`PayoutRouteDisplay.vue` component. You do not need to build icon sourcing
or normalization — it's done. You mainly need to *consume* it correctly in
any new claim screens/components.

### Data
- `resources/documents/payout-destination-icons.json` — the single source
  of truth. 129 entries keyed by SWIFT/BIC bank code, or synthetic codes
  `RAIL:INSTAPAY`, `RAIL:PESONET`, `PROVIDER:NETBANK`, `PROVIDER:PAYNAMICS`,
  `ORCHESTRATOR:XCHANGE` for the non-bank route segments. Each entry has
  `slug`, `assets` (png64/128/256 filenames), `source_url`, `source_type`,
  `confidence` (high/medium/low), `needs_legal_review`, `license_notes`.
- `resources/assets/images/payout-destinations/` — the actual normalized
  PNGs (square, transparent, safe-padded), published wholesale to
  `public_path('vendor/x-change/images/payout-destinations/...')` via the
  existing `x-change.assets` publication (same mechanism as the brand
  library — no new publish step needed).
- Coverage: 124 of 146 bank/EMI codes in `banks.json`, plus both rails and
  both providers. 22 codes have **no icon on purpose** (dead domains,
  scam/impersonation sites, bot-blocked with no fallback — never
  fabricated). Full list + 19 low-confidence flags in
  `docs/claim-ux/payout-destination-icon-library-report.md`.

### Code you can call directly
- **Frontend** (`resources/js/components/x-change/support/payoutDestinations.ts`):
  - `iconAssetForCode(code)`, `iconAssetForRail(rail)`,
    `iconAssetForProvider(provider)`, `orchestratorIconAsset()` — each
    returns a public asset path string or `null`.
  - `destinationInstitution(code).iconAsset` — icon for a bank/EMI code.
  - `payoutRouteIcons(input)` — returns one icon (or `null`) per segment,
    in the same order as the existing `payoutRouteSegments(input)`
    (orchestrator, provider, rail, institution, account-number-has-none).
  - `PayoutDestinationIcon.vue` — drop-in `<img>` renderer with graceful
    fallback to a lucide glyph on missing/broken asset. Props:
    `iconAsset`, `fallbackIcon` (a Vue component), `alt`, `sizeClass`.
- **Backend** (`src/Support/Claim/PayoutDestinationRegistry.php`):
  - `snapshot()` now additionally returns `icon_asset` (institution icon)
    and `route_icons` (array of 4: orchestrator/provider/rail/institution
    icons, parallel to the existing `route` array of labels).
  - `institution($bankCode)` now additionally returns `icon_asset`.
  - Both are purely additive — existing keys/behavior unchanged.
  - `PayoutDestinationIconCatalog::iconAssetForCode/Rail/Provider()` /
    `orchestratorIconAsset()` if you need to resolve icons from other PHP
    code (e.g. a new controller or Blade/GD renderer).

## Rules to follow when scaffolding new claim screens

1. **Icons supplement labels, never replace them.** Every icon-bearing UI
   element must keep its existing text label. This is a hard safety rule
   from the original brief and is now also asserted by
   `tests/Unit/Architecture/PayoutDestinationIconLibraryTest.php`.
2. **Always handle the null case.** ~15% of institutions have no icon by
   design — render the existing generic glyph (see
   `PayoutRouteDisplay.vue` for the pattern: `Send`/`Landmark`/`WalletCards`
   lucide fallbacks) rather than leaving a gap or breaking layout.
3. **No new remote/internet image URLs in the claim UI.** Only reference
   the packaged local assets under `/vendor/x-change/images/payout-destinations/`.
4. **Maya Wallet vs. Maya Bank and InstaPay vs. PESONet currently share one
   icon each** (no distinct official marks exist for either pair) — don't
   build UI that assumes the icon alone disambiguates them; keep relying on
   the text label.
5. **Every non-x-change icon is `needs_legal_review: true`.** If any new
   screen surfaces these icons in a marketing/public-facing context beyond
   the existing claim flow, flag it — none of this library has legal/brand
   sign-off yet.

## Reference example (already working)

`resources/js/components/x-change/PayoutRouteDisplay.vue` is the canonical
usage example: it computes `routeIcons` via `payoutRouteIcons(...)` and
pairs each entry positionally with `routeSegments` inside the `v-for`,
passing `routeIcons[index]` and a fallback icon into
`<PayoutDestinationIcon>`.

## Open follow-ups (not blocking, but good to know)

- 19 low-confidence assets and 22 missing institutions are listed in
  `docs/claim-ux/payout-destination-icon-library-report.md` — don't be
  surprised if a specific bank looks low-res or absent; it's tracked.
- `EAWRPHM2XXX` (Komo) and `UNODPHM2XXX` (UnionDigital Bank) currently
  reuse their parent bank's icon as a placeholder pending distinct sourcing.
- If scaffolding needs a new UI surface for the icon set (e.g. an admin
  picker, a bank-select dropdown with logos), reuse
  `iconAssetForCode`/`PayoutDestinationIcon.vue` rather than re-deriving
  paths manually — the `/vendor/x-change/images/payout-destinations/`
  path is duplicated in both the PHP catalog and the TS module and should
  stay the single convention.
