# Canonical Stamp previews and Open Graph images

The Stamp tab in auto-generate and quick-generate displays a 1200 × 630 PNG
from the same composer used to create the issued Pay Code artifact. This
applies to installed system designs and custom Rider splash/URL artwork.
Configured issuance composer overrides are also honored; interaction bounds
are omitted when a custom composer does not supply the default renderer’s
geometry. Such composers receive an unsaved draft and must remain render-only.
There is no browser-rendered HTML or separately painted content over this
image. Transparent accessibility and QR interaction targets are permitted.

## What matches

Design revision, provider identity, copy, masked recipient, requirements,
artwork treatment and appearance use server interpretation. Browser dark
mode does not change image pixels. The draft uses `|| AA317 ||` and a QR
leading to the generic claim entry page; issuance uses the actual identifier
and claim destination. Expiry is resolved using the same explicit-date/TTL
semantics as issuance, including the existing 12-hour fallback when neither
is supplied. A relative expiry can change as issuance time changes.

The renderer, rather than arbitrary HTML/CSS, defines the final appearance.
Splash HTML is sanitized; the existing artwork resolver extracts supported
images and the composer lays out supported copy. CSS positioning, scripts
and arbitrary webpage layout are not reproduced. URL artwork remains subject
to the existing allowlist and availability policy. A remote source may change
between draft and issuance; the issued image is authoritative and frozen.

## Preview API

`POST /x/stamps/preview` accepts `{ "instructions": { ... } }` for canonical
drafts. The instruction object follows the existing issuance fields, but only
visual fields are consumed. Validation reuses the issuance Rider/requirement
rules, permits a zero-value draft, limits the payload to 256 KiB, and validates
amount, currency, provider and expiry. Client artifact descriptors, artwork
snapshots and provider reservations are ignored. The endpoint creates no
voucher, funding order, claim, payment or balance operation. Safe artwork
preparation may populate the existing artwork snapshot store.

The response contains `image` (a PNG data URI), `provider_identity`,
`marker_bounds` and `requirements`. Bounds support transparent interaction
and accessibility targets, not another visual composition. Responses are
`no-store`; the browser keeps a bounded page-memory cache for 60 seconds,
debounces requests and discards superseded responses. Failed refreshes retain
the previous image with an explicit stale/error notice and retry action.

The former flat `design_id`/`revision`/`slots` request remains supported for
compatibility. Consumers should pass canonical instructions to the shared
Pay Code canvas through `stampInstructions`; legacy consumers without this
property retain their existing rendering behavior.

## Issued images and metadata

Issued viewers use the stored, hash-verified PNG through the existing
share-card URL resolver. Open Graph metadata references that same endpoint;
immutable artifact URLs contain their content hash. Previously issued images
are not regenerated. Newly composed system Stamps use readable instruction
labels instead of internal keys in their footer. Existing legacy fallback behavior remains supported.

x-stamp owns portable design rendering. x-change owns voucher interpretation,
draft adaptation, issuance and artifact storage. `3neti/og-meta` is not needed
for this pipeline. Its existing `OgMetaData.imageUrl` interface can reference
the canonical artifact URL if a future host adopts that package; it should not
recompose the Stamp using its generic card renderer.

## Checking changes

Run the focused Stamp preview, share-card, artifact store, appearance and
amount formatter Pest tests with the package's 2 GiB memory limit. Run the
Stamp/LLM/issued-viewer/funding-dialog frontend tests. Parity tests compare
SHA-256 hashes of complete PNG bytes with identical resolved identifiers,
QR destinations and timing; they do not compare just selected pixels.

Publish package resources into an isolated sandbox host and run its build and
strict asset doctor before browser checks. Verify mobile and desktop in light
and dark mode. Use synthetic instructions and safe provider fixtures; do not
make real payments merely to check rendering.

Browser coverage lives in `tests/browser/stamp-preview.spec.mjs`. Use the existing
Playwright configuration with `WORKFLOW_BROWSER_SPEC=stamp-preview.spec.mjs`,
`WORKFLOW_BROWSER_HOST` pointing to a host with Playwright installed, and
`WORKFLOW_BROWSER_URL` pointing to an isolated HTTPS `.test` host. The fixture
substitutes only the page bootstrap; preview requests reach the real backend.
Every other POST is intercepted. It covers both public and cockpit surfaces at
390 and 1440 pixels, light/dark mode, and instruction-driven image updates.
It does not prove a paid issuance or a fully commissioned host.

## Implementation verification — 2026-10-20

Implemented on `feature/canonical-stamp-preview`, based on the shipped rider
release. Owning repository: x-change. x-stamp, x-stamp-designs and og-meta were
not changed; no Composer dependencies or lockfiles were changed in the package.

Changed runtime files:

- `src/Services/Stamp/StampPreviewComposer.php`: bounded draft adapter and configured composer delegation.
- `src/Http/Controllers/Web/StampPreviewController.php`: canonical instruction request alongside the existing flat API.
- `src/Services/Claim/GdRiderStampClaimShareCardRenderer.php`: shared draft composition and interaction geometry; readable footer labels and cache-version bump.
- `src/Services/Claim/ClaimShareCardAmountFormatter.php`: amount/currency from instructions for unsaved drafts.
- `src/Services/Claim/PayCodeStampIndicatorResolver.php`: readable instruction labels.
- `resources/js/cockpit/components/CockpitPayCodeCanvas.vue`: canonical PNG branch for instruction-backed previews.
- `resources/js/cockpit/components/CockpitSystemStamp.vue`: canonical request through the existing cache, debounce and response sequencing.
- `resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue`: shared reactive instruction projection.
- `resources/js/cockpit/payCodeStampPreview.ts`: optional canonical instruction input.

Changed/added tests: `tests/Feature/Claim/StampPreviewTest.php`,
`tests/Feature/Claim/StampPresentationTest.php`,
`tests/Unit/Services/PayCodeStampIndicatorResolverTest.php`,
`tests/frontend/cockpit/CockpitSystemStamp.test.ts`, and
`tests/browser/stamp-preview.spec.mjs`. This guide is the remaining changed file.

Verification results:

- Consolidated Pest run: **90 passed, 423 assertions**, covering preview, share card, artifact store, appearance, amount formatter and indicators.
- Subsequent configured-composer/default-renderer parity checks: **5 passed, 17 assertions**; one new override test, four previously covered cases. Total distinct Pest cases: **91**.
- Frontend: **52 passed in six files** (system Stamp, LLM workspace/action, issued dialog, funding dialog and designer).
- Chromium: **four passed**, public and cockpit components at 390/1440 px, each switching light/dark mode and updating Purpose. Screenshots inspected. Page bootstrap is synthetic; preview endpoint is real. Other POSTs are intercepted.
- Pint `--dirty --format agent`: passed. `git diff --check`: passed.
- Isolated sandbox: verified package publication, npm ci, production Vite build and strict asset doctor passed. Its Composer path override was removed, tagged dependencies and generated inputs restored, and the temporary Herd TLS/link removed.

Two old fixture failures were reproduced against unchanged release source and
corrected: the retired Valentine-only catalog expectation, and a settlement
indicator fixture missing its required collection wallet. No application
financial behavior was changed to accommodate those tests.

The existing shared sandbox was left untouched because it contains overlapping
work and a pre-existing local package override. Its generated frontend copies
were not overwritten. The isolated verification host has only untracked
published `public/vendor/` resources after restoration. No real payment, paid
claim, external social crawler, or production deployment was exercised. The
full package suite was not run. Nothing was pushed, tagged or deployed for this
change; only a local package commit is intended.
