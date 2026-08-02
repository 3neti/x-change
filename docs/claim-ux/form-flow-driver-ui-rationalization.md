# Claim Experience UI/UX Rationalization

## Purpose

This document rationalizes the current x-change claim experience across:

- `config/x-change.php`
- `.env` driven claim, rider, approval, and handler options
- the `voucher-redemption` form-flow driver
- the form-flow manager package
- the form-flow handler packages for KYC, location, OTP, selfie, and signature
- the claim experience compiler and rider runtime

The goal is to make the public claiming journey feel like one coherent product while preserving the current ownership boundaries:

- x-change owns the claim experience, voucher semantics, claim compiler, approval state, success state, rider handoff, and payout execution boundary.
- form-flow manager owns step orchestration and collected evidence transport.
- form-flow handlers own their own evidence capture capability.
- x-rider owns rider presentation primitives, not voucher or payout semantics.

## Current Human Journey

The intended redeemer journey is:

1. Redeemer opens `/x/claim`.
2. Redeemer enters a Pay Code.
3. x-change shows the voucher x-ray: amount, claim requirements, named slices, rider preview, and availability.
4. If a rider intro splash exists, the claim compiler marks x-rider as the splash owner.
5. Redeemer proceeds into the claim form or form-flow.
6. Form-flow collects the required evidence and payout destination.
7. Redeemer reviews/continues through claim confirmation.
8. x-change submits the claim for execution.
9. If Paynamics payout OTP is required, the redeemer sees an approval-waiting page. The OTP is issuer-side authorization and must not be requested from the redeemer.
10. Once accepted, the redeemer sees the success state, optional rider message, optional redirect countdown, and final rider URL handoff.

The intended issuer journey is:

1. Issuer generates a Pay Code from `/x/pay-codes` or Cockpit Quick Generate.
2. Issuer may configure rider message, rider splash, rider redirect URL, and claim requirements.
3. Issuer can inspect generated Pay Codes in `/x/pay-codes`.
4. If a Paynamics payout requires OTP, the registry marks the voucher as awaiting approval and exposes `/x/pay-codes/{code}/approval`.
5. Issuer enters the payout OTP in the issuer approval surface.
6. Issuer returns to the Pay Code detail or registry. The issuer should not be redirected through the redeemer success/rider URL journey.

## Current Source Of Truth

The claim compiler is already the correct center of gravity.

`ClaimExperienceCompiler` compiles voucher instructions into a `ClaimExperienceData` payload with:

- `entry.mode`
- `entry.initial_phase`
- `options.skip_consumed_splash`
- `options.show_redirect_countdown`
- `phases`
- `diagnostics.splash_owner`
- `diagnostics.redirect_owner`
- `diagnostics.form_flow_splash_policy`
- `diagnostics.form_flow_owner`

`ClaimExperiencePayload` is the adapter that reads and writes the compiled experience from the current storage location, usually:

```text
instructions.metadata.claim_experience
```

Frontend pages and form-flow handlers should consume this contract. They should not re-decide splash ownership, redirect ownership, or whether a consumed splash should be skipped.

## Config And Env Surface

The relevant x-change configuration surfaces are:

- `x-change.withdrawal.pipeline`
- `x-change.withdrawal.otp.driver`
- `x-change.claim_approval.otp.driver`
- `x-change.claim.public_*_middleware`
- `x-change.claim_preview.rider.message`
- `x-change.claim_preview.rider.url`
- `x-change.claim_preview.rider.redirect_timeout`
- `x-change.claim_preview.rider.splash_html`
- `x-change.claim_preview.rider.splash_timeout`
- `x-change.claim_preview.rider.og_source`
- `x-change.rider.features.message`
- `x-change.rider.features.splash`
- `x-change.rider.features.url`

The relevant form-flow and handler configuration surfaces are:

- `FORM_FLOW_ROUTE_PREFIX`
- `FORM_FLOW_SKIP_CONSUMED_SPLASH`
- `KYC_USE_FAKE`
- `HYPERVERGE_BASE_URL`
- `HYPERVERGE_APP_ID`
- `HYPERVERGE_APP_KEY`
- `HYPERVERGE_URL_WORKFLOW`
- `KYC_POLLING_INTERVAL`
- `KYC_AUTO_REDIRECT_DELAY`
- `OTP_LABEL`
- `XCHANGE_IDENTITY_OTP_DRIVER`
- `TXTCMDR_API_URL`
- `TXTCMDR_API_TOKEN`
- `OTP_MAX_RESENDS`
- `OTP_RESEND_COOLDOWN`
- `LOCATION_HANDLER_MAP_PROVIDER`
- `LOCATION_HANDLER_CAPTURE_SNAPSHOT`
- `LOCATION_HANDLER_REQUIRE_ADDRESS`
- `LOCATION_HANDLER_CACHE_DURATION`
- `VITE_OPENCAGE_KEY` / `OPENCAGE_API_KEY`
- `VITE_MAPBOX_TOKEN` / `MAPBOX_TOKEN`
- `GOOGLE_MAPS_API_KEY`
- `SELFIE_HANDLER_*`
- `SIGNATURE_HANDLER_*`

These knobs are functional, but they are not yet presented as one claim experience profile. The next UI/UX tightening should make them feel intentional instead of incidental.

## Form-Flow Driver Inventory

The host currently has `config/form-flow-drivers/voucher-redemption.yaml`.

Its major steps are:

| Step | Handler | Human Purpose | UX Owner |
| --- | --- | --- | --- |
| `splash` | `splash` | Intro screen before evidence capture | Compiler decides whether x-rider or form-flow owns it |
| `wallet` | `form` | Amount, mobile, bank/wallet, account number | x-change semantics, form-flow rendering |
| `kyc` | `kyc` | Identity verification through HyperVerge | handler capability, x-change requirement |
| `bio` | `form` | Name, email, birth date, address, reference data | form-flow rendering |
| `otp` | `otp` | Redeemer phone verification | handler capability |
| `location` | `location` | Geolocation, address, map evidence | handler capability |
| `selfie` | `selfie` | Camera selfie evidence | handler capability |
| `signature` | `signature` | Canvas signature evidence | handler capability |

Important boundary:

The form-flow `otp` handler is redeemer-side phone verification. Paynamics payout OTP is issuer-side payout authorization. They must remain separate surfaces even if they eventually share visual components.

The redeemer handler uses the versioned txtcmdr challenge API. Opening or
rendering the OTP step has no delivery side effect: the claimant explicitly
selects **Send verification code**, and resend is a separate throttled action.
Successful verification returns structured proof to Form Flow; neither the
raw code nor a locally generated fallback reaches x-change. txtcmdr workers
must consume the dedicated `txtcmdr-otp` queue in the txtcmdr deployment.

## UX Findings

### 1. The Claim Experience Is Semantically Strong But Visually Fragmented

x-change now has a mature compiler contract for splash ownership, redirect ownership, named slice selection, approval waits, success rider stages, and claim walkthroughs.

The form-flow handlers, however, render as package-owned pages with their own layout choices:

- different page widths
- different background treatments
- different button placement
- different alert styles
- different permission help patterns
- mixed `Card` and raw Tailwind shells
- some debug UI in KYC pages
- some manually drawn SVG icons where shared icon components would be cleaner

This is expected for independently evolving packages, but the claim journey needs one product shell.

### 2. Splash Ownership Must Stay Compiler-Driven

The current compiler already prevents duplicate splash pages by setting:

```text
diagnostics.splash_owner = x-rider | form-flow
options.skip_consumed_splash = true | false
diagnostics.form_flow_splash_policy = skip_consumed | allow
```

This is the right model. UI code should not independently decide whether to show rider splash versus form-flow splash.

### 3. Success And Redirect Ownership Must Stay Compiler-Driven

The current success path supports rider message, redirect countdown, and rider URL handoff. The redirect must continue through the x-change redirect gate rather than direct arbitrary frontend navigation.

Issuer approval pages must not reuse the redeemer success/rider redirect UX after OTP submission.

### 4. Form-Flow Default Splash Can Interfere With Rider Intent

The YAML driver includes a default `splash` step. This is useful when no rider intro exists, but it becomes confusing if a rider splash already introduced the claim.

The compiler already has the correct skip policy. The UI rationalization should make that policy visible in tests and walkthrough artifacts.

### 5. Permission-Based Drivers Need A Shared Trust Pattern

Location, selfie, KYC, and signature all ask for sensitive input. Their pages should share a consistent trust pattern:

- what is being requested
- why it is needed
- who receives it
- how to retry or cancel
- what happens after successful capture

Today each driver explains this differently.

### 6. OTP Naming Needs Care

There are two OTP ideas in the system:

- redeemer OTP verification in form-flow
- issuer Paynamics payout OTP approval

The UI should name them differently:

- "Verify your mobile number" for redeemer OTP
- "Approve payout OTP" for issuer Paynamics authorization

This avoids teaching users the wrong mental model.

## Recommended UX Contract

Introduce a shared claim step shell that every claim-facing step can opt into.

The shell should standardize:

- page background
- max width
- header/title/subtitle
- Pay Code context
- amount or selected slice summary
- progress/step position when available
- primary and secondary actions
- loading state
- error state
- permission prompt copy
- privacy/support note
- diagnostic data in development only

Suggested conceptual API:

```ts
type ClaimStepTone = 'neutral' | 'sensitive' | 'success' | 'warning' | 'danger';

interface ClaimStepShellProps {
  title: string;
  description?: string;
  payCode?: string;
  amountLabel?: string;
  stepLabel?: string;
  tone?: ClaimStepTone;
  claimExperience?: Record<string, unknown> | null;
}
```

This can start in x-change as a package-owned Vue component because x-change owns the full claim product surface. After the shape proves itself in storyboard QA, it can be pushed down into `form-flow-manager` as a generic `FormFlowStepShell`.

## Recommended Config Shape

Add a future x-change claim UI profile rather than scattering UI meaning across handlers:

```php
'claim_experience_ui' => [
    'shell' => [
        'brand_name' => env('XCHANGE_CLAIM_UI_BRAND_NAME', config('app.name')),
        'show_progress' => env('XCHANGE_CLAIM_UI_SHOW_PROGRESS', true),
        'max_width' => env('XCHANGE_CLAIM_UI_MAX_WIDTH', 'md'),
        'support_label' => env('XCHANGE_CLAIM_UI_SUPPORT_LABEL', null),
    ],

    'copy' => [
        'entry_title' => 'Claim Pay Code',
        'wallet_title' => 'Where should we send the money?',
        'confirmation_title' => 'Review your claim',
        'success_title' => 'Claim completed',
        'approval_waiting_title' => 'Awaiting payout approval',
        'issuer_otp_title' => 'Approve payout OTP',
    ],

    'permissions' => [
        'location' => [
            'why' => 'We need your location because this Pay Code requires location evidence.',
        ],
        'camera' => [
            'why' => 'We need a selfie because this Pay Code requires identity evidence.',
        ],
        'signature' => [
            'why' => 'We need a signature because this Pay Code requires signed confirmation.',
        ],
        'kyc' => [
            'why' => 'We need identity verification because this Pay Code requires KYC.',
        ],
    ],
]
```

This should be additive. Existing handler config remains the source of technical behavior. The new profile only supplies consistent claim-facing presentation.

## Implementation Slices

### Slice 1: Documentation And Storyboard Baseline

Use the claim walkthrough recorder as QA for:

- no rider
- rider message
- rider splash
- rider URL
- named slices
- Paynamics approval wait
- every form-flow handler individually

The output should include HTML and PDF artifacts under `storage/app/x-change/claim-previews`.

### Slice 2: Claim Step Shell In x-change

Create a package-owned claim shell and use it in x-change-owned claim pages first:

- `/x/claim`
- voucher x-ray
- named slice selector
- approval waiting
- claim success
- issuer approval

Do not modify handler packages yet.

### Slice 3: Form-Flow Core Shell Adapter

Wrap form-flow manager core pages:

- `Splash.vue`
- `GenericForm.vue`
- `Complete.vue`
- `MissingHandler.vue`

The wrapper should accept `claim_experience` when present, but still work for non-x-change form-flow use cases.

### Slice 4: Handler Page Harmonization

Bring each handler into the same shell:

- KYC initiate/status
- location capture
- OTP capture
- selfie capture
- signature capture

Keep driver-specific controls, but normalize the surrounding page frame, help text, button placement, cancel/retry behavior, and loading/error treatments.

### Slice 5: Config Cleanup

Normalize naming drift and document the final env surface.

Specific cleanup candidates:

- OTP README mentions TOTP period/digits, while the current config surface is primarily SMS provider, resend count, and cooldown.
- Location defaults differ between package default and host `.env`.
- Form-flow splash policy should remain visible through compiler diagnostics and tests.
- Rider preview envs should map clearly to issuer-facing preview behavior only, not runtime production defaults unless explicitly enabled.

### Slice 6: Package Extraction Decision

After storyboard QA proves the shell shape, decide what moves down:

- Generic shell primitives can move to `form-flow-manager`.
- x-change-specific copy, Pay Code context, payout approval semantics, and rider policy stay in x-change.
- Storyboard/preview technology can later move to `LBHurtado\Preview` / `3neti/preview`.

## Testing And QA

Programmatic checks should include:

- Claim compiler tests for splash ownership and redirect ownership.
- Feature tests for Paynamics approval waiting versus issuer OTP entry.
- Frontend tests for no duplicate splash rendering.
- Frontend tests for success rider message plus redirect countdown.
- Storyboard walkthroughs for no-money fixtures.
- Per-driver walkthroughs for KYC fake mode, location mocked browser permissions, OTP fake SMS mode, selfie mocked camera, and signature canvas capture.

The storyboard recorder should become the human QA layer: if two frames look the same, collapse them unless the UI visibly changes.

## Recommended Direction

Keep the first real implementation in x-change.

Reason:

- x-change owns the issuer and redeemer mental model.
- Paynamics approval semantics are x-change/product semantics, not generic form-flow semantics.
- The fastest way to improve the demo is to standardize x-change-owned claim pages first.
- Once the shape stabilizes, extract generic shell primitives into form-flow-manager and eventually into a broader preview/storyboard package.

Do not start by refactoring all driver packages at once. Use the storyboard output to prove one driver at a time.
