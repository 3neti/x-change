# Future Extraction: 3neti/preview

## Decision Snapshot

The claim walkthrough/storyboard technology should eventually move out of `3neti/x-change` into a generic package:

```text
Package: 3neti/preview
Namespace: LBHurtado\Preview
```

Do not extract it into `3neti/x-ray` as the core owner. `x-ray` may consume or display preview artifacts later, but the recording/rendering/cache engine should be generic enough for other apps, including the future COMELEC app.

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

The extracted package should not know what a Pay Code is.

## Extraction Trigger

Do not extract immediately just because the direction is clear.

Extract when at least two of these are true:

- x-change has issuer-facing preview UI consuming the artifacts
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
