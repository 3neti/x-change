# Claim Success Redirect Path

## Purpose

This document maps the current claim success UI and redirect path before making redirect countdown behavior compiler-driven.

## Current Discovery

Search results show the success path is owned by:

- `src/Http/Controllers/Web/Claim/ClaimSubmitController.php`
- `src/Http/Controllers/Web/Claim/ClaimSuccessPageController.php`
- `src/Http/Controllers/Web/Claim/ClaimRedirectController.php`
- `resources/js/pages/x-change/claim/Success.vue`

## Current Flow

```text
ClaimSubmitController
    -> redirects to route x-change.claim.success

ClaimSuccessPageController
    -> renders x-change/claim/Success

Success.vue
    -> renders rider success content/stages

ClaimRedirectController
    -> resolves rider URL and performs final redirect
