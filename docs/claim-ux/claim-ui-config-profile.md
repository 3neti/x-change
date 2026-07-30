# Claim UI Config Profile

## Purpose

`x-change.claim.experience_ui` is the public claim presentation profile.

It is not a provider configuration surface, not a Cockpit configuration surface,
and not a settlement behavior surface.

Use it for redeemer-facing and issuer-approval-facing copy, presentation
variant selection, and permission explanations.

## Config Shape

```php
'claim' => [
    'experience_ui' => [
        'variant' => env(
            'XCHANGE_CLAIM_UI_VARIANT',
            env('FORM_FLOW_UI_VARIANT', 'default'),
        ),
        'show_progress' => (bool) env('XCHANGE_CLAIM_UI_SHOW_PROGRESS', true),
        'support_label' => env('XCHANGE_CLAIM_UI_SUPPORT_LABEL'),
        'layout' => [
            'density' => env('XCHANGE_CLAIM_UI_DENSITY', 'compact'),
            'capture_surface' => env('XCHANGE_CLAIM_UI_CAPTURE_SURFACE', 'edge_to_edge'),
            'minimize_scroll' => (bool) env('XCHANGE_CLAIM_UI_MINIMIZE_SCROLL', true),
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
            'location' => ['why' => 'This Pay Code requires location evidence.'],
            'camera' => ['why' => 'This Pay Code requires selfie evidence.'],
            'signature' => ['why' => 'This Pay Code requires signed confirmation.'],
            'kyc' => ['why' => 'This Pay Code requires identity verification.'],
        ],
    ],
],
```

## Environment Variables

```env
XCHANGE_CLAIM_UI_VARIANT=default
XCHANGE_CLAIM_UI_DENSITY=compact
XCHANGE_CLAIM_UI_CAPTURE_SURFACE=edge_to_edge
XCHANGE_CLAIM_UI_MINIMIZE_SCROLL=true
XCHANGE_CLAIM_UI_SHOW_PROGRESS=true
XCHANGE_CLAIM_UI_SUPPORT_LABEL=

XCHANGE_CLAIM_UI_ENTRY_TITLE="Claim Pay Code"
XCHANGE_CLAIM_UI_WALLET_TITLE="Where should we send the money?"
XCHANGE_CLAIM_UI_CONFIRMATION_TITLE="Review your claim"
XCHANGE_CLAIM_UI_SUCCESS_TITLE="Claim completed"
XCHANGE_CLAIM_UI_APPROVAL_WAITING_TITLE="Awaiting payout approval"
XCHANGE_CLAIM_UI_ISSUER_OTP_TITLE="Approve payout OTP"

XCHANGE_CLAIM_UI_LOCATION_WHY="This Pay Code requires location evidence."
XCHANGE_CLAIM_UI_CAMERA_WHY="This Pay Code requires selfie evidence."
XCHANGE_CLAIM_UI_SIGNATURE_WHY="This Pay Code requires signed confirmation."
XCHANGE_CLAIM_UI_KYC_WHY="This Pay Code requires identity verification."
```

## Variant Semantics

`default` is the stable fallback and should remain production-safe.

`compact` is for tighter screens where the claim should feel denser without
changing the workflow.

`immersive` is for claim steps where the input surface should dominate the
viewport, such as signature, selfie, and location.

## Layout Semantics

`density=compact` is the QA default for keeping payout fields and handler
screens closer to a one-page mobile review.

`capture_surface=edge_to_edge` tells form-flow drivers to prefer wider
signature, camera, selfie, and location surfaces.

`minimize_scroll=true` expresses the public claim QA goal that first-time
redeemers should not need unnecessary vertical travel to understand or submit a
claim.

## Current Wiring

`FormFlowClaimWorkflowMutator` forwards the configured variant to the
x-change-owned wallet/form step as:

```php
$step['config']['ui_variant']
```

It also forwards layout guidance as:

```php
$step['config']['ui_layout']
```

The handler packages also accept `ui_variant`, but broader driver-wide adoption
should be guided by storyboard QA rather than enabled blindly.

## Boundary

Do not put Cockpit-specific copy, Campaign worksheet import copy, provider
credentials, payout routing, or Treasury labels in this profile.

Do not use this profile to decide claim execution, Paynamics OTP approval,
voucher validity, or form-flow handler conditions.
