# Configure Pay Code claim presentation

The released baseline is x-change v1.0.248 with Form Flow v1.10.0.
The expanded contract described here is implemented in the local companion
package changes and has not been released. Its declared minimums are Form Flow
1.11, OTP 1.4, KYC 1.3, location 1.4, selfie 1.3 and signature 1.4. Release
those dependencies in order before releasing the adopting x-change version;
local acceptance uses ignored Composer path manifests with explicit dev aliases. It distinguishes
released YAML controls from remaining component-owned presentation.

## Ownership

- `3neti/form-flow`: portable presentation schema, resolver, shared screen,
  actions, status icons, and artwork components.
- Handler packages: OTP, KYC, location, selfie, and signature interactions and
  their own presentation integration.
- `3neti/x-change`: Pay Code defaults, domain drivers, outer claim pages,
  rider URL metadata, claim appearance bootstrap, and Claim-tab replay.
- Laravel host: installation, branding overrides, configuration and build.

Read `docs/PRESENTATION.md` in the [Form Flow package](https://github.com/3neti/form-flow)
for the schema and inheritance contract. That guide accompanies this work;
use the matching package checkout until its documentation update is published.
Handler README files describe their specific configuration and limitations.

## Locate the configuration

Package sources:

- [`guided-claim-v1.yaml`](../../config/form-flow-presentations/guided-claim-v1.yaml):
  appearance, status icons, artwork defaults.
- [`voucher-redemption.yaml`](../../config/form-flow-drivers/voucher-redemption.yaml):
  form-flow steps, conditions, fields, handler settings, presentation selection.
- [`campaign-payment-completion.yaml`](../../config/form-flow-drivers/campaign-payment-completion.yaml):
  campaign payment completion driver.

x-change registers `guided-claim-v1` in Form Flow configuration. Its effective
profile merges integration defaults, the host YAML file if present (otherwise
the package YAML), then any pre-existing registered profile overrides. Form Flow
then resolves manager defaults → selected profile → driver → named screen → step settings.

The normal host has copies under `config/form-flow-presentations/` and
`config/form-flow-drivers/`. The package owns shared defaults; a host-specific
customization must be intentional. Check registered PHP profile overrides when
a YAML edit appears to have no effect.

The older PHP `x-change.claim.experience_ui` configuration still supplies copy,
permission explanations and integration defaults. It is not replaced wholesale
by YAML. See [Claim UI Config Profile](claim-ui-config-profile.md).

## How to change the artwork presentation

Edit the package profile for ecosystem-wide defaults, or an intentional host
profile for that application's customization:

```yaml
schema: form-flow.presentation.v1
layout:
  artwork_aspect_ratio: natural
artwork:
  background:
    enabled: true
    opacity: 0.55
    blur: 18
    overlay: 0.8
  foreground:
    position: top
    top_spacing: 32
    gap: 20
    padding: 20
    max_width: 672
```

This is a profile file, so it does not need an enclosing `presentation` key.
`overlay: 0.8` is 80% overlay opacity; increasing it covers more of the
background. It does not mean 80% artwork visibility. The foreground preserves
the full artwork. The image-only backdrop is decorative; splash HTML is rendered
once. Rider URL artwork depends on resolved metadata; links without an image
use a text fallback.

## How to change palette, icons, or a step label

The profile supports `appearance.mode: light`, `dark`, or `system` and separate
six-digit hex palettes. The released Pay Code default is light regardless of
device appearance. Confirmation uses the `review` role; pending and completed
states use `pending` and `success`. Other status pages still have direct icons.

For a single form-flow step, edit its driver configuration:

```yaml
steps:
  wallet:
    config:
      presentation:
        actions:
          primary_label: Continue
```

This is an excerpt to merge into the existing wallet definition. Preserve its
handler, fields, and other configuration. Avoid a global primary label: it can
replace meaningful labels such as Capture Photo or Verify OTP. Use `actions.roles.capture`, `verify`, `send_code`, `retry`, or the appropriate
operation role for per-state labels. Components retain the actual operation.

## Publish and verify

For shared changes, edit/release the owning package first, update the host
Composer dependency, then publish and build from the host:

```bash
php artisan x-change:publish --scope=build --force --verify --no-interaction
npm ci --no-audit --no-fund
npm run build
php artisan x-change:doctor --assets --strict --no-interaction
```

Publication is mechanical and can overwrite published copies. Preserve and
review intentional host-specific overrides before a forced publish. Do not
hand-edit generated Vue copies or installed vendor source. A normal frontend
change does not require migrations or the broad installer.

x-change also exposes the `x-change-form-flow-drivers` vendor publication tag
for the domain drivers and profile. Form Flow's example-driver tag is not the
owner of Pay Code YAML. Prefer the unified x-change build workflow above.

## Check a new preview and a new flow

1. Open the shared Pay Code card in public auto-generate or cockpit issuance.
2. Add draft rider HTML or a Rider URL with artwork, then open Claim.
3. Generate/update the walkthrough after changing instructions or presentation.
4. Check entry, splash, details, confirmation, success, and any conditional
   handler, pending, error, approval or handoff states.
5. Check mobile widths, large text, device appearance, full foreground artwork,
   action reachability, and browser errors.

Active flows retain their frozen presentation. x-change remembers the profile
for a started Pay Code claim; previews also snapshot it and include presentation
in cache identity. Updating YAML should not restyle an existing active flow
mid-claim. A new preview/new controlled test flow is the acceptance target.
Use fixture-based lifecycle and browser tests; do not issue or claim real funds
merely to verify styling.

## Shared coverage and responsibilities

All audited claim shells now consume the shared policy, including outer entry,
confirmation, outcome, approval, authentication, error, payment and handoff
pages. Entry and confirmation panels and status panels are shared with replay;
form-flow previews continue to render the actual handler components.

The x-change registered screen keys are `entry`, `details`,
`personal_information`, `confirmation`, `success`, `pending`, `approval`,
`auth_required`, `error`, `payment`, `payment_handoff`, `rider_redirect`,
`rider_url`. Core and installed-handler keys are registered by their owners.
Pending outcomes on the success route select `pending`, not completed success.

Use the portable guide's tokens, notices, and `screens`/`actions.roles` examples.
Entry's approved larger centered mark is an explicit named default. Existing
light appearance and artwork defaults remain. Package versions are shared only
when enabled and only in local/testing, including named screen overrides.

Capture surfaces now use the policy; signature resizing retains drawn content.
Preview guards cover camera initialization/retake, signature input/submission,
KYC status polling/retry, location access, and completion/payment actions.
Preview rendering is not authorization to perform the corresponding action.

Detailed form composition, domain copy and information hierarchy remain in
components or existing domain configuration. YAML changes their supported
presentation controls; it does not describe arbitrary HTML page layouts or
external bank, KYC, browser-permission, or destination websites.

Keep validation, authentication, provider execution, retries, permissions and
financial lifecycle behavior in tested code. Presentation configuration must not
silently change what action a user is authorizing.

## Verification references

- `tests/Feature/Claim/ClaimPresentationTest.php`: profile delivery, snapshots,
  preview invalidation, and installed handler preview contracts.
- `tests/Unit/ClaimWalkthrough/ClaimExperiencePreviewServiceTest.php`: compiled
  journeys, preview caching and synthetic preview boundaries.
- `tests/browser/claim-presentation-contract.spec.mjs` and
  `tests/browser/claim-form-layout.spec.mjs`: branding, spacing, device
  appearance, presentation customization and artwork layouts.
- Form Flow `tests/Unit/FormFlowPresentationTest.php`: resolver precedence,
  rejected values, snapshots, artwork and diagnostics.

When closing the audit gaps, test a visibly different profile on each real
screen and replay, not only the unchanged defaults or serialized props.
