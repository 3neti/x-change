# Future Extraction: 3neti/preview

## Decision Snapshot

The claim walkthrough/storyboard technology should eventually move out of `3neti/x-change` into a generic package:

```text
Package: 3neti/preview
Namespace: LBHurtado\Preview
```

Do not extract it into `3neti/x-ray` as the core owner. `x-ray` may consume or display preview artifacts later, but the recording/rendering/cache engine should be generic enough for other apps, including the future COMELEC app.

`3neti/og-meta` should be treated as the future canonical Open Graph metadata and social image engine. Harden it before `x-change` consumes it directly.

The near-term direction is:

```text
x-change local OG preview contract
    ↓ aligns with
3neti/og-meta OgMetaData
    ↓ feeds
Quick Generate issuer preview
    ↓ feeds
Claim walkthrough Frame 0 / storyboard artifacts
```

Do not make storyboard generation depend on live external OG scraping. The storyboard should preview the issuer-configured metadata deterministically and may separately capture the final rider URL as a live browser frame when the scenario asks for it.

## Why Not x-ray

`x-ray` should remain an inspection and disclosure surface:

```text
What is this Pay Code / payload / envelope made of?
What does it imply?
What should a human inspect?
```

The preview engine answers a broader question:

```text
What will a human experience, step by step?
```

That includes browser recording, screenshots, PDF/HTML storyboard rendering, artifact caching, replay metadata, and future interactive preview surfaces. Those concerns should not make `x-ray` a broad utility package.

## Target Shape

`3neti/preview` should be framework-agnostic PHP first.

Core package responsibilities:

- preview scenarios
- preview frames
- preview runs
- deterministic fingerprinting
- artifact metadata
- artifact view options
- local artifact storage abstractions
- cache abstractions
- renderer contracts
- browser recorder contracts
- HTML/PDF storyboard renderers

Open Graph metadata should stay in `3neti/og-meta`, not `3neti/preview`.

`3neti/preview` may consume an OG metadata payload as just another preview frame input:

```text
PreviewFrame(type: "og-meta", payload: OgMeta-like array)
```

but it should not own crawler tags, social card image generation, resolver middleware, or `/og/{resolver}/{identifier}` image routes.

Laravel-specific pieces should be adapters, not core assumptions.

## Framework Boundary

The core package must not depend on Laravel concepts such as:

- Eloquent
- Artisan
- Laravel facades
- `config()`
- `storage_path()`
- `base_path()`
- Laravel cache
- Laravel filesystem
- Inertia
- Vue

Use interfaces for integration seams:

```php
namespace LBHurtado\Preview\Contracts;

interface ArtifactStore
{
    public function put(string $path, string $contents): void;
    public function exists(string $path): bool;
    public function url(string $path): ?string;
}

interface CacheStore
{
    public function get(string $key): mixed;
    public function put(string $key, mixed $value, int|\DateInterval|null $ttl = null): void;
}

interface Recorder
{
    public function record(PreviewScenario $scenario, PreviewContext $context): PreviewRun;
}

interface Renderer
{
    public function render(PreviewRun $run): PreviewArtifact;
}
```

## Suggested Package Layout

```text
src/
  Contracts/
    ArtifactStore.php
    CacheStore.php
    Fingerprinter.php
    Recorder.php
    Renderer.php

  Data/
    PreviewScenario.php
    PreviewFrame.php
    PreviewRun.php
    PreviewArtifact.php
    ViewOption.php

  Fingerprinting/
    Sha256PreviewFingerprinter.php

  Storage/
    LocalArtifactStore.php
    NullCacheStore.php
    FilesystemCacheStore.php

  Rendering/
    HtmlStoryboardRenderer.php
    PdfStoryboardRenderer.php

  Recording/
    ProcessBrowserRecorder.php

  Laravel/
    PreviewServiceProvider.php
    Commands/
    Models/
    Stores/
    config/preview.php
```

If OG preview frames become first-class in the extracted package, use an adapter boundary:

```php
namespace LBHurtado\Preview\Contracts;

interface OgMetaPreviewSource
{
    public function toPreviewPayload(): array;
}
```

The core package should avoid depending on `LBHurtado\OgMeta\Data\OgMetaData` directly. Laravel/x-change adapters may bridge from `OgMetaData` into `PreviewFrame` payloads.

If the Laravel bridge becomes large, split it later:

```text
3neti/preview
3neti/preview-laravel
```

## Composer Direction

The core package should require only PHP and framework-neutral dependencies.

Possible starting point:

```json
{
  "name": "3neti/preview",
  "autoload": {
    "psr-4": {
      "LBHurtado\\Preview\\": "src/"
    }
  },
  "require": {
    "php": "^8.2"
  },
  "suggest": {
    "illuminate/support": "Required for Laravel integration.",
    "symfony/console": "Required for Symfony console integration.",
    "symfony/process": "Useful for process-backed browser recording."
  }
}
```

Symfony compatibility should be preserved by keeping the core free of Laravel-only APIs.

## x-change Responsibilities After Extraction

`3neti/x-change` should keep the domain-specific adapter layer:

- Pay Code preview scenarios
- claim journey scenario definitions
- no-money fixture generation
- rider preview defaults
- provider/approval preview presets
- x-change console command wrappers
- x-change UI entry points
- Pay Code and claim-specific OG metadata resolvers
- mapping voucher/rider instructions into OG preview payloads

The extracted package should not know what a Pay Code is.

## OG Meta Hardening Plan

Before `x-change` consumes `3neti/og-meta`, harden the package as its own slice.

Current local package:

```text
/Users/rli/PhpstormProjects/packages/og-meta
Package: 3neti/og-meta
Namespace: LBHurtado\OgMeta
```

Known hardening items:

- Update Composer constraints for Laravel 13 / Illuminate 13 compatibility.
- Decide whether screenshot rendering is a hard dependency or an optional feature.
- If screenshot rendering remains optional, move `spatie/laravel-screenshot` to `suggest` or require it explicitly with clear install instructions.
- Add package tests around `OgMetaData`, resolver registration, image URL generation, and GD rendering.
- Add tests for cache-key/status behavior so social images refresh when status changes.
- Keep GD renderer as the deterministic local/default mode.
- Keep screenshot renderer behind explicit config because it may require external browser rendering credentials.
- Verify `/og/{resolverKey}/{identifier}` route behavior under Laravel 13.
- Verify middleware alias registration under Laravel 13.
- Document cache invalidation for regenerated social cards.

`x-change` should only add `3neti/og-meta` as a dependency after these are true:

```text
composer install works cleanly on Laravel 13
package tests pass
GD renderer works without external services
image route works in Herd/local app
resolver contract is stable enough for Pay Code previews
```

## x-change OG Integration Plan

After `3neti/og-meta` is hardened, add an x-change integration slice.

### 1. Add Pay Code OG resolver

Create an x-change resolver that maps voucher state into `OgMetaData`:

```text
active / available       → redeemable Pay Code preview
awaiting approval        → approval-pending preview
redeemed / disbursed     → completed preview
expired                  → expired preview
```

The resolver should use x-change vocabulary, not generic provider vocabulary.

Suggested fields:

```text
title       Pay Code {code}
description Amount, availability, and claim state
status      active | pending | redeemed | expired
headline    voucher code
subtitle    formatted amount
tagline     short human CTA/state text
typeBadge   cash | payable | collectible
payeeBadge  recipient/payee hint when safe
cacheKey    voucher code + meaningful version/status input
```

### 2. Share Quick Generate OG preview logic

Quick Generate currently owns local OG preview logic inside the cockpit submit panel. Extract that logic into a shared x-change module aligned with `OgMetaData`.

The shared contract should answer:

```text
Which rider source is powering the preview?
What title/description/reference should the issuer see?
Should splash HTML be rendered or summarized?
What payload would eventually become OgMetaData?
```

Quick Generate remains a local/sandboxed preview. It should not fetch external OG metadata or create social assets during ordinary form editing.

### 3. Add storyboard Frame 0

The claim walkthrough storyboard should include an initial OG/social preview frame before the human claim journey:

```text
Frame 0 — Social / OG preview
Frame 1 — Claim entry
Frame 2 — X-ray / rider splash
Frame 3 — Pre-claim rider splash
Frame 4 — Form-flow splash
Frame 5 — Payout form
Frame 6 — Filled payout form
Frame 7 — Confirmation
Frame 8 — Claim success with rider message
Frame 9 — Rider redirect handoff
Frame 10 — Rider URL
```

Frame 0 should render the same issuer-configured OG payload that Quick Generate shows. It should be cached with the same preview fingerprint inputs.

### 4. Keep final rider URL separate

The final rider URL frame is not the same as OG metadata.

Use this split:

```text
OG/social preview frame
    deterministic
    issuer configured
    cached with scenario fingerprint
    no external scrape required

Rider URL frame
    browser capture of final destination
    optional
    may involve network
    cached separately when URL and viewport match
```

This prevents a Spotify, merchant, or campaign page capture from being confused with the social card that appears when a Pay Code claim link is shared.

## Proposed Slice Order

1. Harden `3neti/og-meta` for Laravel 13.
2. Add x-change `OgMetaData` adapter/resolver for Pay Codes.
3. Extract Quick Generate local OG preview logic into a shared x-change module.
4. Add deterministic storyboard Frame 0 from the shared OG preview payload.
5. Add tests around cache fingerprints that include OG preview inputs.
6. Later, when preview APIs stabilize, extract browser/storyboard/artifact infrastructure into `3neti/preview`.

## Extraction Trigger

Do not extract immediately just because the direction is clear.

Extract when at least two of these are true:

- x-change has issuer-facing preview UI consuming the artifacts
- x-change has OG/social preview frames consuming the same metadata contract as `3neti/og-meta`
- onboarding or another package needs the same preview engine
- COMELEC/Symfony usage is ready to begin
- artifact profiles stabilize
- preview cache/fingerprint contract stops changing weekly
- browser recorder behavior is no longer experimental

## Current Source To Mine During Extraction

Current x-change implementation lives around:

```text
packages/x-change/src/ClaimWalkthrough/
packages/x-change/src/Console/Commands/Claim/ClaimWalkthroughCommand.php
packages/x-change/src/Models/ClaimPreviewArtifact.php
packages/x-change/scripts/claim-browser-walkthrough.mjs
packages/x-change/database/migrations/*claim_preview_artifacts*
```

Use these as the prototype, not as the final package API.

## Naming Notes

Preferred package:

```text
3neti/preview
```

Rejected or secondary names:

- `3neti/x-preview`: too tied to the x-* ecosystem; less useful for COMELEC/Symfony.
- `3neti/storyboard`: good artifact name, but too narrow for the package.
- `3neti/experience-preview`: descriptive, but heavier and wordier.

Use `Storyboard` as an artifact/renderer concept inside `Preview`, not as the package name.
