# Claim Experience Preview Service

The Claim Experience Preview turns the current `VoucherInstructionsData`
draft into a recipient-facing walkthrough inside
`/x/cockpit/quick-generate`. It lets an issuer inspect the claim journey
before issuing a Pay Code without creating an executable preview claim.

## Product contract

The Pay Code canvas has four views:

```text
Stamp → Design → Claim → Cost
```

**Claim** opens the **Claim Experience Preview**. The preview:

- preserves the same canvas footprint as Stamp, Design, and Cost;
- presents the journey in recipient order;
- uses a protected captured frame when one exists;
- shows only protected captured screens that demonstrate a recipient action;
- supports direct step selection and Previous/Next navigation;
- marks itself out of date when the Pay Code draft changes;
- provides protected PDF and HTML exports; and
- remains preview-only.

Opening Claim generates the first walkthrough on demand. It does not rerender
on every keystroke. After the draft changes, **Update Preview** performs the
explicit refresh. The old standalone preview panel does not coexist with this
canvas view.

The recorder resolves Node from the current runtime, Laravel Herd, and common
NVM locations. Deployments with a dedicated binary path may set
`XCHANGE_CLAIM_PREVIEW_NODE_BINARY`; the configured path takes precedence.

## Safety invariants

Preview execution is not issuance and is not a claim:

- no Account or Treasury debit;
- no Inventory or Position posting;
- no provider call;
- no claim submission;
- no voucher consumption;
- no feedback delivery; and
- no executable preview Pay Code left behind.

Browser recording is always forced to `submit_claim=false`. Temporary preview
Pay Codes carry the persistent marker
`metadata.custom.walkthrough.preview_only=true`. The claim execution guard
rejects a marked preview before executor or provider selection. The temporary
Pay Code is deleted after recording, including when capture fails.

This is defense in depth: the recorder does not submit, the marked Pay Code
cannot execute, and the fixture is disposed.

## Journey compilation

`ClaimPreviewScenarioFactory` compiles the draft through the same
`ClaimExperienceCompiler` used to understand the real claim experience. It
then produces a deterministic redeemer journey from the compiled stages:

```text
Entry
  → Rider introduction, when configured
  → Claimant inputs and validation
  → Named or interval slice selection, when configured
  → Outcome review
  → Completion
```

Open Graph and social-card inspection are not claim steps and are excluded
from this journey.

Each public step contains only:

- sequence;
- stable key;
- phase;
- title;
- plain-language description;
- actor;
- capture status; and
- a protected frame descriptor.

## Service API

The internal renderer is
`LBHurtado\XChange\ClaimWalkthrough\ClaimExperiencePreviewService`.

```php
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\ClaimWalkthrough\ClaimExperiencePreviewOptions;
use LBHurtado\XChange\ClaimWalkthrough\ClaimExperiencePreviewService;

$result = app(ClaimExperiencePreviewService::class)->renderFromInstructions(
    VoucherInstructionsData::createFromAttribs($instructions),
    new ClaimExperiencePreviewOptions(
        issuer: $issuer,
        baseUrl: config('app.url'),
        profile: 'issuer',
        dryRun: false,
        refresh: false,
    ),
);
```

The internal service result may contain private filesystem locations required
by the recorder and export builder. It must never be returned directly to a
browser.

## Browser-safe manifest

`ClaimPreviewWebManifestPresenter` converts the private result into:

```json
{
  "schema": "x-change.claim-experience-preview.manifest.v1",
  "status": "ready",
  "reference": "01...",
  "fingerprint": "sha256...",
  "generated_at": "2026-07-29T12:00:00+08:00",
  "cache_hit": false,
  "safety": {
    "preview_only": true,
    "interactive": false,
    "money_movement": false,
    "provider_calls": false,
    "claim_submission": false
  },
  "journey": {
    "step_count": 1,
    "steps": [
      {
        "sequence": 1,
        "key": "claim-entry",
        "phase": "entry",
        "title": "Open Pay Code",
        "description": "The recipient opens the claim.",
        "actor": "redeemer",
        "render_kind": "captured_frame",
        "status": "captured",
        "frame": {
          "url": "/x/cockpit/quick-generate/claim-previews/{reference}/frames/claim-entry",
          "mime_type": "image/png",
          "sha256": "sha256...",
          "width": 390,
          "height": 844
        }
      }
    ]
  },
  "exports": {
    "pdf_url": "/x/cockpit/quick-generate/claim-previews/{reference}/exports/pdf",
    "html_url": "/x/cockpit/quick-generate/claim-previews/{reference}/exports/html"
  }
}
```

The manifest must not contain a local path, storage disk, `file://` URL, shell
command, preview Pay Code, fixture form value, provider credential, or raw
recorder report.

## HTTP routes

All routes require the authenticated Cockpit boundary:

```text
POST /x/cockpit/quick-generate/claim-previews
GET  /x/cockpit/quick-generate/claim-previews/{artifact}
GET  /x/cockpit/quick-generate/claim-previews/{artifact}/frames/{step}
GET  /x/cockpit/quick-generate/claim-previews/{artifact}/exports/{format}
```

The POST accepts the same sanitized instruction shape as Quick Generate plus
`refresh_preview` and `preview_profile`. Browser callers do not supply fixture
mobile, account, provider, destination, or claim-submission values.

The artifact, frame, and export controllers return `404` unless the artifact
belongs to the authenticated owner. Frame names and resolved paths are
validated before any file response. Artifact responses are private and
`no-store`.

## Persistence and cache isolation

`ClaimPreviewArtifact` stores an owner morph and the preview fingerprint.
Uniqueness is owner-scoped. Identical drafts from different Account holders do
not share authorization or public references.

The cache stores the private artifact metadata needed to serve protected
files, but it does not persist the original request payload. The report stored
for later presentation is sanitized before persistence.

## Internal components

- `ClaimPreviewScenarioFactory` compiles the real claim experience into a
  deterministic walkthrough.
- `ClaimPreviewVoucherPayloadFactory` creates the marked, non-executable
  capture fixture.
- `ClaimPreviewExecutionGuard` blocks preview fixtures at the execution
  boundary.
- `ClaimPreviewVoucherDisposer` removes temporary preview Pay Codes.
- `ClaimExperiencePreviewService` coordinates cache, capture, storyboard, and
  report generation.
- `ClaimPreviewArtifactCache` stores owner-scoped private artifact metadata.
- `ClaimPreviewArtifactAccess` authorizes the owner and resolves safe files.
- `ClaimPreviewJourneyManifestFactory` creates the provider-neutral journey.
- `ClaimPreviewWebManifestPresenter` creates the only browser response shape.
- `CockpitClaimExperiencePreview.vue` renders the non-interactive walkthrough
  inside the canvas.

## Acceptance

The feature is complete only when tests prove:

- preview Pay Codes cannot reach claim execution;
- capture never submits a claim;
- temporary preview Pay Codes are removed on success and failure;
- the journey follows compiled claim stages;
- owner A cannot read owner B’s manifest, frames, or exports;
- traversal and unknown artifact paths are rejected;
- browser manifests contain no private paths or fixture values;
- the Claim view maintains canvas dimensions and supports keyboard-accessible
  navigation;
- changing the draft marks the current walkthrough stale;
- the standalone duplicate preview panel is absent; and
- desktop and narrow browser layouts remain usable.

## Future extraction

The result and journey contracts are intentionally provider-neutral. A future
`3neti/preview` package may own generic artifact orchestration while x-change
keeps voucher compilation, Cockpit authorization, and the claim-specific
journey.
