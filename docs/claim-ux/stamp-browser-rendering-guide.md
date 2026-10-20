# Optional browser Stamp rendering trial

Ownership: x-stamp owns portable profiles and Chromium capture; x-change owns
instruction projection, sanitization, trusted provider identity, QR destinations,
profile freezing, immutable artifacts and OG metadata. x-stamp-designs owns
artwork. The default remains GD at 1200×630. This feature is a local trial, not a
Cloud rollout.

## Deployment configuration

After installing x-stamp 1.4 and optional `spatie/browsershot:^5.4`, configure a
compatible Node/Puppeteer/Chrome runtime. Profiles are `standard` (GD 1200×630),
`browser` (Chromium 1200×630), and `browser-hd` (Chromium 2400×1260).

```dotenv
STAMP_RENDERING_PROFILE=standard
STAMP_RENDERING_ALLOWED=standard,browser,browser-hd
STAMP_RENDERING_COMPARISON_ENABLED=true
STAMP_NODE_BINARY=/absolute/path/to/node
STAMP_NODE_MODULE_PATH=/absolute/path/to/node_modules
STAMP_CHROME_PATH="/absolute/path/to/chrome"
```

Only enable the comparison flag locally. `/x/stamps/rendering-comparison` returns
404 outside local environments and while disabled. It uses the real guest preview
endpoint and synthetic instructions; it cannot issue vouchers. It reports PNG
dimensions, bytes and composition time (asset snapshot preparation is excluded).
It allows downloads and an optional HTML rider splash. Production should keep
`STAMP_RENDERING_ALLOWED=standard` until the runtime and operating cost are accepted.

The requested profile is `metadata.custom.stamp_rendering.profile`. x-change
validates deployment-allowed profiles and replaces this object with a bounded,
signed snapshot during instruction materialization before funding. The shared
normalizer carries the snapshot into issuance. Active signed snapshots survive
changes to the allowed/default configuration; keep the application signing key
stable. Legacy vouchers without a snapshot use standard rendering. Client preview
input cannot inject signatures, artifacts, snapshots, executables or browser flags.

## Composition and immutability

Preview and issued artifacts use the same composer. HD rasterizes HTML/SVG at 2×;
it does not upscale the complete GD PNG. QR source, QR backing and identifier are
rendered natively at the selected density; geometry remains logical 1200×630.
Provider logos and existing badge artwork retain their source resolution.

Browser custom artwork currently supports one snapshotted image in sanitized HTML
splash. It uses x-rider sanitization and x-change's existing allow-listed asset
snapshot pipeline, then replaces the image source with verified inline bytes.
Static template image layers are replaced; provider, amount, code, requirements,
recipient, expiry and QR remain in the frame. Automatic/splash-derived overlay
copy is suppressed so artwork text appears once. Explicit custom/message/URL copy
is retained. Existing GD behavior is unchanged. Browser URL artwork and plain or
Markdown splash artwork are rejected rather than silently rendered differently.
Use system artwork or HTML splash for this trial. Artwork should leave room for the retained frame, especially the QR; arbitrary positioned text can overlap that frame. Complex HTML outside the bounded
utility stylesheet may differ from a full app page.

Chromium has no page JavaScript or external network access, uses an explicit light
canvas, disables animation, waits for image/font readiness and has a bounded
20-second timeout (configuration supports 1–60). Failures propagate without a GD
fallback. Asset acquisition happens server-side under existing constraints.

Standard artifacts retain their v1 descriptor. Browser artifacts use v2 with
profile, driver, scale and physical dimensions. Reads validate stored bytes against
the stored descriptor, independently of current deployment defaults. Existing
immutable PNGs are reused. OG image width/height follows the artifact descriptor;
legacy dynamic images retain 1200×630 metadata.

## Verification

- x-stamp: `composer test`; set `STAMP_BROWSER_MODULE_PATH` and
  `STAMP_BROWSER_CHROME` to include Chromium integration tests.
- x-change: focused `StampRenderingProfilesTest`, `StampPreviewTest`,
  `RiderStampArtifactStoreTest`, `Base64PngClaimUrlQrRendererTest`. Install optional
  Browsershot in the test runtime to exercise the real-browser cases; otherwise
  those cases explicitly skip.
- UI: `tests/browser/stamp-rendering.spec.mjs` checks the local comparison,
  native dimensions, downloads and mobile/dark-mode layout. The existing
  `stamp-preview.spec.mjs` covers public/cockpit preview updates and theme stability.
- Publish package resources, run `npm ci --no-audit --no-fund`, `npm run build`,
  then `php artisan x-change:doctor --assets --strict --no-interaction`.

Do not enable browser profiles on Cloud without provisioning the runtime and
measuring resource usage/concurrency there. Local timing does not measure Cloud
latency or Chrome peak memory. No default, release, payment or deployment changes
are implied by running this trial.

## Local trial results (2026-10-20)

Verified using Browsershot 5.4.1, Node 22.21.1 and local Google Chrome. One warm
synthetic Steampunk run measured approximately 408 ms / 1.0 MB for GD,
1645 ms / 1.1 MB for browser 1×, and 1598 ms / 3.2 MB for browser 2×. These are
single local observations, not production benchmarks or service targets.

The portable suite passed 48 tests. The initial focused x-change regression set
passed 71 tests / 344 assertions; added composed-HD QR decoding and byte parity
checks passed afterward. Metadata, share-card and normalizer coverage also passed
after correcting one independently reproduced baseline assertion: metadata tests
now exclude inline image URLs in the head rather than rejecting legitimate claim
UI data in the body. Six Playwright checks passed for public/cockpit previews at
390/1440px, themes, profile downloads and the Planetary Rose HTML composition.
The published sandbox passed the strict asset doctor. The full package suite and
Cloud runtime were not exercised. Chrome peak memory was not measured.

Implementation lives on local `feature/browser-stamp-profiles` branches in both
packages; x-change targets the upcoming x-stamp 1.4 API. Release x-stamp first
before publishing x-change. No tags, pushes, Packagist publication or Cloud changes
were performed for this trial.
