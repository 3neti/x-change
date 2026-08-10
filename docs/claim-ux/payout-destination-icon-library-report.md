# Payout Destination Icon Library — Coverage Report

Generated 2026-08-10. Source of truth: `resources/documents/payout-destination-icons.json`. Assets: `resources/assets/images/payout-destinations/`.

## Summary

- **129 metadata entries** covering: x-change itself, both settlement rails (InstaPay, PESONet), 2 settlement providers (NetBank, Paynamics), and **124 of the 146** bank/EMI codes in `resources/documents/banks.json`.
- **22 codes** have no icon (left out of the metadata entirely — no fabricated art). See "Missing institutions" below.
- Confidence breakdown across all 129 entries: **78 high**, **32 medium**, **19 low**.
- Every entry except `ORCHESTRATOR:XCHANGE` (x-change's own brand-library mark) is flagged `needs_legal_review: true`, since every bank/EMI/provider/rail mark is a third-party registered trademark fetched from a public favicon/site asset, not a licensed brand asset. **None of this library should be used in production marketing materials without a human legal/brand review pass.**

## How this was built

- **Tier 0/1 (curated by hand)**: x-change's own mark (reused from the existing brand library), both rails, NetBank, Paynamics, GCash, Maya Wallet, Maya Bank, GrabPay, ShopeePay, Coins.ph, and the ~18 largest banks (BDO, BPI, Metrobank, UnionBank, RCBC, Security Bank, Chinabank, PNB, LandBank, DBP, AUB, EastWest, CIMB, Tonik, SeaBank, GoTyme, UNObank), plus their subsidiary BIC records.
- **Tier 2 (parallel crawl)**: the remaining ~114 long-tail banks/EMIs were split into 8 batches and researched in parallel by child agents, each resolving an official domain, fetching the best available official asset (site logo → apple-touch-icon → favicon → favicon-service proxy, in that preference order), and normalizing it into the shared `<slug>-{64,128,256}.png` convention.
- All assets are square, transparent-background PNGs with safe padding, produced by `scripts/payout-destinations/normalize-icon.sh` (ImageMagick).

## Missing institutions (22)

No icon exists for these codes — the claim UI will fall back to the existing generic institution glyph and text label for them. Each was investigated and rejected/excluded for a concrete reason (dead domain, bot-blocked site with no real fallback, parked/squatted domain, scam/impersonation site, or no discoverable web presence at all):

- **AIIPPHM1XXX** — Al-Amanah Islamic Bank: no fetchable image anywhere (HTML shells and blank proxy results only).
- **BFSRPHM2XXX** — Banana Fintech / BananaPay: domain parked/squatted, redirects to an unrelated site.
- **BIUUPHM1XXX** — Binangonan Rural Bank / BRBDigital: domain no longer resolves (NXDOMAIN).
- **BKKBPHMMXXX** — Bangkok Bank: only a generic file-type icon retrievable, not the bank's mark.
- **RBSMPHMMXXX** — Rural Bank of San Medjugorje: no website found.
- **RLSKPHM1XXX** — Rural Bank of Lebak (Sultan Kudarat): no website found.
- **RUBCPHM2XXX** — Rural Bank of Bacolod City: no website found.
- **RUDIPHM1XXX** — Rural Bank of Digos: only discoverable domain is a spam/template site with fabricated content; rejected.
- **RUMTPHM2XXX** — Bank of Montalban: no website found.
- **RUPZPHM2XXX** — Rural Bank of La Paz: no website found.
- **RURUPHM2XXX** — Rural Bank of Rosario (LU): listed domain is dead (NXDOMAIN).
- **RUSYPHM2XXX** — Rural Bank of Sagay: only discoverable domain is an impersonation/scam site; rejected.
- **SHBKPHMMXXX** — Shinhan Bank: no PH-specific site; global SPA exposes no favicon.
- **SUSVPHM1XXX** — Sun Savings Bank: site only serves the generic default WordPress favicon.
- **TAGCPHM2XXX** — Tagcash: favicon resolves to an unrelated monogram; no static logo asset exists otherwise.
- **TYBKPHMMXXX** — Yuanta Savings Bank: fully behind a Cloudflare bot-challenge; no reliable asset obtainable.
- **UCPBPHMMXXX** — United Coconut Planters Bank: merged into LandBank in 2022; site is now a dead redirect stub with no logo. (Note: its still-active subsidiary **UCSVPHM1XXX**, UCPB Savings Bank, *was* sourced successfully.)
- **ZBTEPHM2XXX** — Zybi Tech / JuanCash: TLS handshake failures on every attempt. Also: BSP Circular CL-2026-013 (Mar 2026) reportedly revoked this entity's EMI/VASP/OPS licenses — may warrant a separate "deprecated" flag independent of the icon gap.
- **DMBNPHM1XXX** — "DM Bank": could not identify a real, distinct Philippine institution matching this name/BIC at all; recommend a manual BSP lookup.
- **KARUPHM1XXX** — Bangko Kabayan: site blocks automated requests (HTTP 406); no usable fallback.
- **MOMLPHM2XXX** — Money Mall Rural Bank: site serves only a placeholder/under-construction page.
- **NSPRPHM1XXX** — Bangko Nuestra Senora del Pilar: no website found (RBAP directory lists "n/a").

## Questionable / low-confidence assets (19)

These have an icon, but it is either very low-resolution, a placeholder/reused parent-brand mark, or otherwise uncertain — flagged for design/legal follow-up before relying on them for production marketing:

- **AUBKPHMMXXX** (AUB), **EWBCPHMMXXX** (EastWest), **DUMTPHM1XXX** (Dungganon Bank) — small (16px) abstract marks; visual match to the real brand could not be confidently verified.
- **CTCBPHMMXXX** (CTBC), **PHSBPHMMXXX** (Philippine Savings Bank / PSBank), **PHTBPHMMXXX** (Philtrust Bank), **PSCOPHM1XXX** (Producers Savings Bank), **UOVBPHMMXXX** (UOB Philippines), **WEDVPHM1XXX** (Wealth Development Bank), **ISTHPHM1XXX** (ISLA Bank), **LESIPHM1XXX** (Legazpi Savings Bank / BPI LSB) — only a small (16–79px) source asset was obtainable; recommend re-sourcing a sharper official asset.
- **QCDFPHM1XXX** (Queen City Development Bank) — favicon is an abstract geometric mark whose match to the brand couldn't be fully confirmed.
- **UNOBPHM2XXX** (UNObank) — direct fetch blocked (403); fallback is a generic placeholder, not a distinct mark.
- **LAUIPHM2XXX** (SeaBank) — resolves to an "M" mark hosted on sister-brand MariBank infrastructure rather than a distinct SeaBank wordmark.
- **EAWRPHM2XXX** (Komo / East West Rural Bank), **UNODPHM2XXX** (UnionDigital Bank) — placeholder reuse of the parent bank's mark; both are distinct digital sub-brands that deserve their own logo in a future pass.
- **PAPHPHM1XXX** (Maya Wallet), **MYDBPHM2XXX** / **MYYAPHM2XXX** (Maya Bank) — Maya serves the *same* tiny (17px) favicon site-wide for both the Wallet and Bank products. **There is no official asset that visually distinguishes Maya Wallet from Maya Bank.** The claim UI currently relies on the text label (always rendered alongside the icon) to carry this distinction. Recommend commissioning distinct badges/overlays if stronger visual differentiation is required.

## Also worth follow-up (not "low confidence" but noted by crawlers)

- **RAIL:INSTAPAY / RAIL:PESONET** both use PPMI's (Philippine Payments Management, Inc.) single operator mark — there is no separate official InstaPay vs. PESONet badge. Recommend commissioning distinct rail badges for production.
- **ONNRPHM1XXX** (BDO Network Bank) and **BPDIPHM1XXX** (BPI Direct BanKo) reuse their parent bank's mark as subsidiaries; **CHSVPHM1XXX** (China Bank Savings) likewise reuses the parent Chinabank mark. All three may warrant distinct sub-brand marks in a future pass.
- **COUKPHM1XXX** was sourced under its current successor brand "Top Bank Philippines, Inc." (Country Builders Bank rebranded in 2024); the BIC record's legal name still reads "COUNTRY BUILDERS BANK,INC."
- **ROBPPHMQXXX** (Robinsons Bank) fully merged into BPI in July 2026; its wordmark was recovered from the Wayback Machine since the live site is decommissioned.
- **HBPHPHMMXXX** (HSBC Savings Bank Phils.) ceased operations in Oct 2023 and merged into HSBC Philippines; it reuses the parent HSBC mark.
- **RSNAPHM2XXX** rebranded to "SNR Bank" in 2024; sourced under the current brand.
- **ICBCPHMMXXX**: despite the "ICBC"-looking prefix, this BIC actually maps to Mega International Commercial Bank (Taiwan), *not* China's ICBC (`ICBKPHMMXXX`, sourced separately) — verify this mapping if it looks surprising downstream.

## Vue component

`PayoutDestinationIcon.vue` renders the resolved icon image (falling back to the existing neutral lucide glyph if an asset is missing or fails to load) and is wired into `PayoutRouteDisplay.vue`'s route-segment pills alongside the existing text labels — icons supplement the labels and never replace them.

## Recommended next steps

1. Route this report and the `needs_legal_review: true` entries through brand/legal before any production marketing use.
2. Commission distinct marks for: InstaPay vs. PESONet, Maya Wallet vs. Maya Bank, and the flagged digital sub-brands (Komo, UnionDigital Bank).
3. Manually re-source the 19 low-confidence assets from official press kits where possible.
4. Periodically re-run the crawl for the 22 missing institutions, since several were blocked by transient bot protection (Cloudflare/406/403) rather than a genuine absence of a logo.
