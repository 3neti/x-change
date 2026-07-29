# Claim Experience Preview Service

The claim experience preview turns a `VoucherInstructionsData` contract into human-inspectable walkthrough artifacts. It is the reusable service behind issuer previews in `/x/cockpit/quick-generate`.

## Purpose

Issuers need to understand what the redeemer will see before a Pay Code is distributed. The preview service renders that journey without treating the preview as a real claim.

Default safety posture:

- no provider payout calls
- no claim submission
- no wallet debit through the funded `GeneratePayCode` path
- artifact caching enabled
- output written under `storage/app/x-change/claim-previews/{fingerprint}`

## Backend API

Use `LBHurtado\XChange\ClaimWalkthrough\ClaimExperiencePreviewService`.

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
        mobile: '09173011987',
        bankCode: 'GXCHPHM2XXX',
        accountNumber: '09173011987',
    ),
);
```

The result schema is:

```json
{
  "schema": "x-change.claim-experience-preview.result.v1",
  "status": "ready",
  "cache_hit": false,
  "reference": "optional artifact reference",
  "fingerprint": "sha256 fingerprint",
  "scenario": "claim_instructions_preview",
  "dry_run": false,
  "artifacts": {
    "root": "...",
    "storyboard_json": "...",
    "storyboard_html": "...",
    "storyboard_pdf": "...",
    "report_json": "...",
    "view_options": {
      "default": { "label": "Default PDF", "url": "file://..." },
      "html": { "label": "HTML storyboard", "url": "file://..." },
      "folder": { "label": "Artifact folder", "url": "file://..." }
    }
  }
}
```

## Cockpit Consumption

Quick Generate receives a read-model contract:

```json
{
  "schema": "x-change.cockpit.quick-generate-claim-preview.v1",
  "status": "registered",
  "route": "x-change.cockpit.quick-generate.claim-previews.store",
  "route_url": "/x/cockpit/quick-generate/claim-previews",
  "preview_cache": true,
  "money_movement": false
}
```

The Vue panel posts the current Quick Generate draft payload to `route_url`. The response provides direct view options for the PDF, HTML storyboard, and artifact folder.

## HTTP Endpoint

```text
POST /x/cockpit/quick-generate/claim-previews
```

The request uses the same `GeneratePayCodeRequest`-compatible payload as Quick Generate issuance, plus optional preview controls:

```json
{
  "cash": { "amount": 25, "currency": "PHP" },
  "inputs": { "fields": [] },
  "feedback": { "mobile": null },
  "rider": {
    "message": "Issuer message",
    "url": "https://example.test/rider",
    "splash": "<section>Issuer splash</section>"
  },
  "preview_profile": "issuer",
  "refresh_preview": false,
  "dry_run": false,
  "preview_mobile": "09173011987",
  "preview_bank_code": "GXCHPHM2XXX",
  "preview_account_number": "09173011987"
}
```

`dry_run=true` builds storyboard files without launching the browser. This is useful for fast automated tests. For real issuer preview artifacts, use the default `dry_run=false`.

## Internal Pieces

- `ClaimPreviewScenarioFactory` converts `VoucherInstructionsData` into the existing storyboard scenario shape.
- `ClaimPreviewVoucherPayloadFactory` creates an issuer-preview-safe Pay Code payload when browser capture needs a real code.
- `ClaimExperiencePreviewService` coordinates cache lookup, fixture issuance, recording, storyboard generation, and report normalization.
- `ClaimPreviewArtifactCache` stores and retrieves artifact metadata by fingerprint.

## Future Extraction

This service is intentionally shaped as a bridge toward a future generic preview package. The Laravel-specific parts are currently:

- authenticated issuer resolution
- `PayCodeIssuanceContract`
- `storage_path()`
- Eloquent `ClaimPreviewArtifact`
- Laravel cache/filesystem
- HTTP controller and cockpit read model

A future `LBHurtado\Preview` package should extract the artifact/result contracts and renderer orchestration while x-change keeps voucher-specific scenario compilation.
