# Paynamics Constellation Integration Report

**Date:** 2026-05-08
**Status:** Disbursement validated — OTP refactor needed for automated lifecycle

---

## Completed Work

### Dependency & Package Wiring

- Added `3neti/emi-paynamics` as a path repository and dependency in `composer.json`
- Package installed via symlink from `~/PhpstormProjects/packages/emi-paynamics`
- `ConstellationServiceProvider` auto-discovered — all 90+ artisan commands registered

### Configuration

- `config/emi.php` — activated `paynamics` in the `payout_providers` map
- `config/x-change.php` — made `payout.provider` env-switchable via `XCHANGE_PAYOUT_PROVIDER` (defaults to Netbank, preserving current behavior)
- `config/constellation.php` — published to app config for `bank_map` and `rail_fees` customization
- `.env` — all `CONSTELLATION_*` credentials set, settlement wallet configured

### Validation Completed (Phases 1–6)

- **Phase 1** — env/config loads correctly
- **Phase 2** — `constellation:probe` confirms API connectivity, authentication, merchant identity, and 148 supported banks
- **Phase 3** — Consumer wallet created, Level 2 KYC approved, funded via InstaPay
- **Phase 4** — Supported banks validated; PTIINSTAPAY is the required disbursement method
- **Phase 5** — ₱10 and ₱50 cash-outs successfully disbursed to GCash via PTIINSTAPAY
- **Phase 6** — Reconciliation confirmed via `constellation:cash-out-status` — status retrievable by `request_id`

---

## Critical Findings

### 1. Consumer wallet required (not merchant)

Paynamics requires a **consumer wallet** with **Level 2 KYC** for InstaPay disbursements. The original merchant wallet (`CNSTWLLT5BPIUG`) could not disburse.

### 2. PTIINSTAPAY disbursement method

Paynamics instructed to use `PTIINSTAPAY` method, which uses SWIFT-style bank codes (e.g., `GXCHPHM2XXX` for GCash).

### 3. Bank ID is a MongoDB ObjectId

The `bank_id` field in the API requires the internal MongoDB ObjectId, **not** the display code shown by `constellation:supported-banks`.

Example for GCash:
- Display code: `GXCHPHM2XXX`
- Actual `bank_id` for API: `67cec0d9e5a2ea23098c3730`

The `constellation:supported-banks` raw response includes an `id` field with the correct value. The `code` field is for display only.

### 4. OTP is required for every cash-out

The cash-out flow requires two API calls:
1. **Request OTP** → `POST /integration/corp_wallet/request_otp` — sends SMS to wallet holder's phone
2. **Submit cash-out** → `POST /integration/corp_wallet/withdraw_not_registered` — requires the OTP + same `request_id`

This is a **blocker for fully automated lifecycle scenarios**. The `ConstellationPayoutProvider` currently calls `CreateCashOutNonRegistered` directly without OTP.

### 5. meta_data field is required

The cash-out API requires `"meta_data": {}` in the request body (empty object is acceptable). Missing it returns `GR027`.

### 6. request_id max length is 36 characters

UUID-based request IDs with prefixes (e.g., `CONR-{uuid}`) exceed the 36-char limit. The `ConstellationPayoutProvider` uses the voucher reference as the `request_id`, which should be fine, but this constraint must be respected.

### 7. Wallet funding via InstaPay

Wallets are funded via InstaPay bank transfer:
- **Receiving institution:** Paynamics Technologies Inc
- **Account number:** the wallet's `account_no` field

No cash-in API channels (GCash, Maya, etc.) are enabled for the merchant account.

---

## Wallet Inventory

### Consumer Settlement Wallet (ACTIVE — use this for disbursements)

| Field | Value |
|---|---|
| Wallet ID | `CNSTWLLT9GSPQ1` |
| Account ID | `CNSTCUSTTVR6B3` |
| Account No | `201614462139` |
| Wallet Type | Personal (Consumer) |
| Status | Active |
| KYC Level | 2 (APPROVED) |
| Email | `lbhurtado@gmail.com` |
| Mobile | `639173011987` |
| External UID | `xchange-settlement-consumer` |

### Merchant Wallet (CANNOT disburse — kept for reference)

| Field | Value |
|---|---|
| Wallet ID | `CNSTWLLT5BPIUG` |
| Account ID | `CNSTMRCHRKN6T4` |
| Account No | `754180803410` |
| Wallet Type | Business (Merchant) |
| Status | Active |
| KYC Level | 0.5 (APPROVED) |
| Email | `admin@disburse.cash` |
| External UID | `settlement-wallet` |
| Note | Cannot disburse via InstaPay — merchant wallets require OTP action not allowed (GR028) |

---

## Verified Transactions

| Request ID | Amount | Destination | Bank | Status |
|---|---|---|---|---|
| `CO26050816383378` | ₱10.00 | `09173011987` (GCash) | GXCHPHM2XXX | Cash Out Success |
| `CO26050816422043` | ₱50.00 | `09146743857` (GCash) | GXCHPHM2XXX | Cash Out Pending → Success |

Both used the OTP flow (request OTP → submit cash-out with OTP code).

---

## Bank ID Mapping (PTIINSTAPAY)

The `ConstellationPayoutProvider.resolveBankId()` uses `config('constellation.bank_map')` to map bank codes. For PTIINSTAPAY, the API requires MongoDB ObjectIds.

Key mappings discovered:

| Display Code | Bank Name | Disbursement Method | MongoDB bank_id |
|---|---|---|---|
| `GXCHPHM2XXX` | GXI (GCash) | PTIINSTAPAY | `67cec0d9e5a2ea23098c3730` |
| `GXI` | GCASH / G-Xchange Inc. | SBINSTAPAY | `654a0057ae26017cc1807e22` |
| `GXI` | GCASH / G-Xchange Inc. | SBPESONET | `654a0057ae26017cc1807e39` |

The full bank list can be fetched via:
```php
$result = GetAllSupportedBanks::run();
// Each bank: { id (MongoDB ObjectId), code (display), name, disbursement_method }
```

---

## Files Modified

| File | Change |
|---|---|
| `composer.json` | Added `emi-paynamics` path repo + dependency |
| `config/emi.php` | Uncommented paynamics in `payout_providers` |
| `config/x-change.php` | Made `payout.provider` env-driven via `XCHANGE_PAYOUT_PROVIDER` |
| `config/constellation.php` | Published from emi-paynamics package |
| `.env` | Added constellation credentials + `CONSTELLATION_SETTLEMENT_WALLET_ID=CNSTWLLT9GSPQ1` |

---

## Current Blocker — OTP Refactor

The `ConstellationPayoutProvider.disburse()` calls `CreateCashOutNonRegistered` directly. The Paynamics API requires OTP before every cash-out. This must be refactored to:

1. Add an OTP request step before disbursement
2. Make OTP delivery/verification a testable, injectable concern
3. Integrate OTP handling into the lifecycle scenario runner

See `paynamics_remaining_steps.md` for the refactor plan.
