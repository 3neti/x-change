# /x/claim Endpoint — Claim Flow Map

**Version**: 1.0
**Last Updated**: 2026-05-09
**Audience**: Human developers + AI agents

This document maps the complete claim flow for x-change Pay Codes — from claim initiation to success page, including form-flow integration, the post-redemption pipeline, disbursement, and rollback behavior.

---

## Flow Diagram

```
┌─────────────────────── PHASE 1: x-change PACKAGE ────────────────────┐
│                                                                       │
│  Show.vue "Start Claim" button                                        │
│  or shared URL: /x/claim?code=XXXX                                    │
│       │                                                               │
│       ▼                                                               │
│  ClaimStartController::__invoke()                                     │
│       │ validates voucher (exists, not redeemed, not expired)         │
│       │ DriverService::transform(voucher) via YAML driver             │
│       │ FormFlowService::startFlow(instructions)                      │
│       ▼                                                               │
│  redirect to /form-flow/{flow_id}                                     │
│                                                                       │
└───────────────────────────────┬───────────────────────────────────────┘
                                │
┌──────────────────── PHASE 2: FORM-FLOW PACKAGE ───────────────────────┐
│                               │                                        │
│  FormFlowController::show() ◀─┘                                       │
│       │ resolves handler for current step                              │
│       │ calls handler->render() → Inertia page                        │
│       ▼                                                                │
│  ┌─ Steps (conditional, from YAML driver) ──────────────────────┐     │
│  │ 0. Splash.vue .............. (if splash_enabled)             │     │
│  │ 1. GenericForm.vue ......... wallet_info (always)            │     │
│  │ 2. KYCInitiatePage.vue ..... (if has_kyc)                    │     │
│  │    └─ KYCStatusPage.vue .... (polls until approved)          │     │
│  │ 3. GenericForm.vue ......... bio_fields (if any bio)         │     │
│  │ 4. OtpCapturePage.vue ...... (if has_otp)                    │     │
│  │ 5. LocationCapturePage.vue . (if has_location)               │     │
│  │ 6. SelfieCapturePage.vue ... (if has_selfie)                 │     │
│  │ 7. SignatureCapturePage.vue  (if has_signature)               │     │
│  └──────────────────────────────────────────────────────────────┘     │
│       │ each step: POST /form-flow/{flow_id}/step/{index}             │
│       │ when all done:                                                 │
│       ▼                                                                │
│  Complete.vue (summary + "Confirm Redemption" button)                  │
│       │ triggers on_complete callback                                  │
│       │ POST /x/claim/{code}/complete (CSRF-exempt)                    │
│                                                                        │
└───────────────────────────────┬────────────────────────────────────────┘
                                │
┌─────────────────────── PHASE 3: x-change PACKAGE ────────────────────┐
│                                │                                      │
│  ClaimCompleteController ◀─────┘                                      │
│       │ logs completion, returns JSON acknowledgment                  │
│                                                                       │
│  Complete.vue "Confirm Redemption" button                             │
│       │ POST /x/claim/{code}/submit                                   │
│       ▼                                                               │
│  ClaimSubmitController::__invoke()                                    │
│       │ retrieves collected data from form-flow session               │
│       │ flattens step data + applies field mappings                   │
│       │ builds payload (mobile, bank_code, account_number, inputs)    │
│       │ calls SubmitPayCodeClaim::handle()                            │
│       │   → ClaimExecutionFactory selects executor                    │
│       │   → RedeemPayCode::handle() (for disburseable vouchers)      │
│       │     → RedeemVoucher::run()                                    │
│       │       → VoucherObserver::redeemed()                           │
│       │         → HandleRedeemedVoucher (pipeline)                    │
│       │                                                               │
│       │ ┌─ Post-Redemption Pipeline ──────────────────────────┐      │
│       │ │ 1. ValidateRedeemerAndCash (voucher package)        │      │
│       │ │ 2. ValidateRedemptionContract (voucher package)     │      │
│       │ │ 3. DisburseCash (voucher package)                   │      │
│       │ │    → NetbankPayoutProvider::disburse()               │      │
│       │ │    → on success: WithdrawCash, record metadata       │      │
│       │ │    → on failure: record pending, pipeline continues  │      │
│       │ └─────────────────────────────────────────────────────┘      │
│       │ fires DisbursementRequested event                             │
│       │ clears form-flow session                                      │
│       ▼                                                               │
│  redirect to /x/claim/{code}/success                                  │
│       │                                                               │
│  ClaimSuccessPageController → claim/Success.vue                       │
│       │ shows rider message (markdown rendered)                       │
│       │ countdown redirect to /x/claim/{code}/redirect                │
│       ▼                                                               │
│  ClaimRedirectController                                              │
│       │ audit logs the redirect event                                 │
│       └─ 302 to rider.url (or back to success if none)               │
│                                                                       │
└───────────────────────────────────────────────────────────────────────┘
```

---

## Phase 1: Claim Start

**Route**: `GET /x/claim?code=XXXX` → `ClaimStartController` (public, no auth)
**Location**: `packages/x-change/src/Http/Controllers/Web/Claim/ClaimStartController.php`

1. Validates voucher exists, is not redeemed, and is not expired
2. If invalid, renders `x-change/claim/Error.vue` with message
3. Calls `DriverService::transform($voucher)` — builds form-flow instructions from YAML driver
4. Calls `FormFlowService::startFlow($instructions)` — creates session-based flow
5. Redirects to `/form-flow/{flow_id}`

**Entry points**:
- Show.vue "Start Claim" button (for operators viewing their own vouchers)
- Shared URL `/x/claim?code=XXXX` (for claimants receiving a link)

---

## Phase 2: Form-Flow — Multi-Step Data Collection

**Ownership**: `3neti/form-flow` package. x-change has no control during this phase.

The YAML driver at `config/form-flow-drivers/voucher-redemption.yaml` controls which steps appear based on voucher instructions. Steps are conditional — only the wallet step is always shown.

### YAML Driver Configuration

**Reference ID**: `claim-{{ code }}-{{ timestamp }}`
**Callbacks**:
- `on_complete`: `{{ base_url }}/x/claim/{{ code }}/complete`
- `on_cancel`: `{{ base_url }}/x/claim`

### Steps

| # | Step | Handler | Condition | Collected Data Key |
|---|------|---------|-----------|-------------------|
| 0 | Splash | `splash` (built-in) | `splash_enabled` | `splash_page` |
| 1 | Wallet/Disbursement | `form` (built-in) | always | `wallet_info` |
| 2 | KYC Verification | `kyc` (plugin) | `has_kyc` | `kyc_verification` |
| 3 | Personal Info | `form` (built-in) | any bio field | `bio_fields` |
| 4 | OTP Verification | `otp` (plugin) | `has_otp` | `otp_verification` |
| 5 | Location Capture | `location` (plugin) | `has_location` | `location_capture` |
| 6 | Selfie Capture | `selfie` (plugin) | `has_selfie` | `selfie_capture` |
| 7 | Signature Capture | `signature` (plugin) | `has_signature` | `signature_capture` |

### Completion Detection

`Complete.vue` detects the claim flow by checking `reference_id.startsWith('claim-')`, extracts the voucher code from the pattern `claim-{CODE}-{timestamp}`, and shows the branded confirmation UI with "Confirm Redemption" button.

---

## Phase 3: Claim Execution

### Complete Callback

**Route**: `POST /x/claim/{code}/complete` (CSRF-exempt)
**Controller**: `ClaimCompleteController`

Server-to-server callback from form-flow. Logs the completion event and returns JSON acknowledgment. The actual collected data remains in the form-flow session.

### Claim Submit

**Route**: `POST /x/claim/{code}/submit`
**Controller**: `ClaimSubmitController`

1. Retrieves collected data from form-flow session (via `reference_id` or `flow_id`)
2. Flattens multi-step data into a single array
3. Applies field name mappings from `config('x-change.redemption.field_mappings')`
4. Builds claim payload: `mobile`, `country`, `bank_code`, `account_number`, `inputs`
5. Calls `SubmitPayCodeClaim::handle($voucher, $payload)`
6. `ClaimExecutionFactory` selects the appropriate executor (redeem for disburseable vouchers)
7. `RedeemPayCode` marks the voucher as redeemed, triggering the post-redemption pipeline
8. Clears form-flow session
9. Redirects to success page

### Post-Redemption Pipeline

Fires synchronously via the Voucher observer chain:

```
SubmitPayCodeClaim::handle()
  → RedeemPayCode::handle()
    → RedeemVoucher::run()
      → VoucherObserver::redeemed()
        → HandleRedeemedVoucher (pipeline)
```

Pipeline stages (from `config/voucher-pipeline.php`):

| # | Stage | What it does |
|---|-------|-------------|
| 1 | ValidateRedeemerAndCash | Ensures voucher has redeemer and Cash entity |
| 2 | ValidateRedemptionContract | Validates redemption rules (secret, mobile, etc.) |
| 3 | DisburseCash | Calls payout gateway. On success: withdraws from wallet, stores metadata. On failure: records pending status, pipeline continues |

### Disbursement Behavior

**Design principle**: "Redemption is sacred" — bank/gateway failures do NOT revert the user's redemption.

| Failure | What happens | Voucher state |
|---------|-------------|---------------|
| Gateway timeout/error | Caught by DisburseCash. Recorded as `pending`. Pipeline continues. | **Redeemed** (stands) |
| EMI + PESONET mismatch | Exception thrown. Transaction rolls back. | **Unredeemed** (reverted) |
| Missing contact/cash | Pipeline stops. No disbursement attempted. | **Redeemed** (stands) |

Pending disbursements are resolved later via the reconciliation system (`x-change:reconcile-pending`).

### Success Page

**Route**: `GET /x/claim/{code}/success` → `ClaimSuccessPageController`
**Vue**: `pages/x-change/claim/Success.vue`

Displays:
- Success confirmation with voucher code and amount
- Rider message (markdown rendered via `marked`)
- Countdown redirect to rider URL

### Redirect

**Route**: `GET /x/claim/{code}/redirect` → `ClaimRedirectController`

Logs the redirect event via the audit logger, then 302s to `rider.url`. Falls back to the success page if no rider URL is configured.

---

## Route Reference

### Public Claim Routes (no auth)

| Route | Method | Controller | Vue Page |
|-------|--------|-----------|----------|
| `GET /x/claim?code=X` | `__invoke` | `ClaimStartController` | Error.vue (on failure) |
| `POST /x/claim/{code}/complete` | `__invoke` | `ClaimCompleteController` | — (JSON callback) |
| `POST /x/claim/{code}/submit` | `__invoke` | `ClaimSubmitController` | — (redirect) |
| `GET /x/claim/{code}/success` | `__invoke` | `ClaimSuccessPageController` | `claim/Success.vue` |
| `GET /x/claim/{code}/redirect` | `__invoke` | `ClaimRedirectController` | — (HTTP redirect) |

### Form-Flow Package Routes (vendor)

| Route | Controller | Vue Page |
|-------|-----------|----------|
| `GET /form-flow/{flow_id}` | `FormFlowController::show()` | Handler-dependent |
| `POST /form-flow/{flow_id}/step/{index}` | `FormFlowController::updateStep()` | — (redirect to next) |

---

## Handler Packages

| Handler | Package | Required env vars |
|---------|---------|------------------|
| `form` | `3neti/form-flow` (built-in) | — |
| `splash` | `3neti/form-flow` (built-in) | `SPLASH_ENABLED` |
| `kyc` | `3neti/form-handler-kyc` | `HYPERVERGE_*` |
| `otp` | `3neti/form-handler-otp` | `ENGAGESPARK_*` |
| `location` | `3neti/form-handler-location` | `VITE_OPENCAGE_KEY`, `VITE_MAPBOX_TOKEN` |
| `selfie` | `3neti/form-handler-selfie` | — |
| `signature` | `3neti/form-handler-signature` | — |

---

## Key Files

### Package Controllers
- `packages/x-change/src/Http/Controllers/Web/Claim/ClaimStartController.php`
- `packages/x-change/src/Http/Controllers/Web/Claim/ClaimCompleteController.php`
- `packages/x-change/src/Http/Controllers/Web/Claim/ClaimSubmitController.php`
- `packages/x-change/src/Http/Controllers/Web/Claim/ClaimSuccessPageController.php`
- `packages/x-change/src/Http/Controllers/Web/Claim/ClaimRedirectController.php`

### Package Actions (reused, not modified)
- `packages/x-change/src/Actions/Redemption/SubmitPayCodeClaim.php`
- `packages/x-change/src/Actions/Redemption/RedeemPayCode.php`
- `packages/x-change/src/Actions/Redemption/PreparePayCodeRedemptionFlow.php`

### Vue Pages
- `packages/x-change/resources/js/pages/x-change/claim/Error.vue`
- `packages/x-change/resources/js/pages/x-change/claim/Success.vue`

### Host App Config
- `config/form-flow-drivers/voucher-redemption.yaml` — YAML driver with x-change callbacks

### Vocabulary

| Public (UI/API) | Internal (services/DTOs) |
|-----------------|-------------------------|
| Claim | redeem / withdraw |
| Pay Code | voucher |
| Cash Out | disburse |

---

## What This Flow Does NOT Handle (Yet)

- Divisible voucher withdrawal (partial slices)
- Pay flow (inward collection)
- Settlement flow (bilateral)
- Settlement envelope finalization
- `/disburse` compatibility alias
- Code entry page (claimants receive direct links)

These can be added behind the same `/x/claim` surface by extending `ClaimExecutionFactory`.
