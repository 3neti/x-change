# Paynamics Integration — Remaining Steps

**Date:** 2026-05-08
**Phases 1–6:** Complete (see `paynamics_integration_report.md`)
**Current phase:** OTP refactor for automated lifecycle scenarios

---

## Completed Phases (Summary)

- **Phase 1–2** — Env/config validated, API probe successful
- **Phase 3** — Consumer wallet `CNSTWLLT9GSPQ1` created, Level 2 KYC, funded ₱600 via InstaPay
- **Phase 4** — PTIINSTAPAY confirmed; bank_id requires MongoDB ObjectId (not display code)
- **Phase 5** — ₱10 + ₱50 cash-outs to GCash completed (with manual OTP)
- **Phase 6** — Reconciliation via `constellation:cash-out-status` works

---

## Phase 7 — OTP Refactor (CURRENT)

### Problem

The Paynamics cash-out API requires OTP for every transaction:
1. `POST /integration/corp_wallet/request_otp` → sends SMS OTP to wallet holder
2. `POST /integration/corp_wallet/withdraw_not_registered` → requires OTP code + same `request_id`

The current `ConstellationPayoutProvider.disburse()` calls step 2 directly, which fails.

### Design

Introduce an injectable `OtpResolver` contract so the OTP step is:
- **Testable** — can be faked/mocked in tests
- **Swappable** — different strategies for different environments
- **Lifecycle-aware** — the scenario runner can provide OTP via callback or queue

#### Contract (in emi-paynamics)

```php
interface ConstellationOtpResolver
{
    /**
     * Request and resolve an OTP for a cash-out.
     * Returns the OTP code string.
     */
    public function resolve(array $otpRequestPayload): string;
}
```

#### Implementations

- `InteractiveOtpResolver` — requests OTP via API, waits for input (CLI/console)
- `CallbackOtpResolver` — requests OTP via API, calls a callback/closure to retrieve it
- `ConfigOtpResolver` — reads OTP from config/env (for testing with Paynamics test OTPs)
- `NullOtpResolver` — returns empty string (for providers that don't need OTP, e.g. Netbank)

#### Changes to ConstellationPayoutProvider.disburse()

```php
public function disburse(PayoutRequestData $request): PayoutResultData
{
    // ... existing wallet/payload resolution ...

    // Step 1: Request OTP
    $otpPayload = [
        'account_id' => $accountId,
        'bank_account_no' => $request->account_number,
        'bank_id' => $this->resolveBankId($request->bank_code),
        'request_id' => $request->reference,
        'reason' => $this->resolveReason($request),
        'amount' => $this->normalizeAmount($request->amount),
    ];
    $otp = $this->otpResolver->resolve($otpPayload);

    // Step 2: Submit cash-out with OTP
    $payload['otp'] = $otp;
    $payload['meta_data'] = new \stdClass;
    $response = $this->createCashOutNonRegistered->handle($payload);
    // ... existing response handling ...
}
```

#### Changes to lifecycle scenario runner

The lifecycle runner needs to supply OTP during automated runs. Options:
- **Queue-based:** OTP request triggers SMS, a listener reads it from a webhook/callback
- **Polling-based:** OTP sent to phone, human enters it via CLI prompt during scenario
- **Test bypass:** Paynamics may provide a test OTP or waiver for automated testing

For initial implementation, use the **interactive CLI approach** during lifecycle runs — the runner pauses, prompts for OTP, then continues.

### Additional fixes for ConstellationPayoutProvider

1. Add `'meta_data' => new \stdClass` to the cash-out payload
2. Use MongoDB `id` for `bank_id` via `bank_map` config or a bank registry lookup
3. Ensure `request_id` stays within 36-char limit

---

## Phase 8 — Bank Map Configuration

Populate `config/constellation.php` `bank_map` with MongoDB ObjectIds for the banks used in lifecycle scenarios:

```php
'bank_map' => [
    'GXCHPHM2XXX' => '67cec0d9e5a2ea23098c3730', // GCash PTIINSTAPAY
    // Add more as needed from GetAllSupportedBanks::run()
],
```

Update lifecycle scenario defaults in `config/lifecycle-scenarios.php`:
```php
'bank_code' => env('XCHANGE_LIFECYCLE_BANK_CODE', 'GXCHPHM2XXX'),
```

---

## Phase 9 — Provider Switch & Smoke Test

Add to `.env`:

```env
XCHANGE_PAYOUT_PROVIDER="LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider"
```

Run smoke lifecycle:

```bash
php artisan xchange:lifecycle:run basic_cash \
    --timeout=1 \
    --poll=1 \
    --max-polls=1 \
    --accept-pending \
    --json
```

---

## Phase 10 — Full Lifecycle Parity

```bash
php artisan xchange:lifecycle:run divisible_open_three_slices_enforced_interval \
    --timeout=1 \
    --poll=1 \
    --accept-pending \
    --json
```

Validate: sequential payouts, provider references, reconciliation, pending-state handling, settlement continuity.

---

## Phase 11 — Revert (If Needed)

Remove `XCHANGE_PAYOUT_PROVIDER` from `.env` to switch back to Netbank.

---

## Retry Policy

| Operation | Max Attempts |
|---|---|
| Connectivity | 3 |
| Wallet lookup | 3 |
| OTP request | 2 |
| Payout | 2 |
| Lifecycle scenario | 2 |

**Philosophy:** Fail fast, analyze, then adjust. No blind retry loops.

---

## Operational Checklist

### Before running Paynamics lifecycle scenarios

1. Verify wallet balance: `php artisan constellation:wallet-balance CNSTWLLT9GSPQ1`
2. Ensure `CONSTELLATION_SETTLEMENT_WALLET_ID=CNSTWLLT9GSPQ1` in `.env`
3. Ensure `XCHANGE_PAYOUT_PROVIDER` points to `ConstellationPayoutProvider`
4. Ensure `constellation.bank_map` has the required bank mappings
5. Have phone available for OTP SMS on `639173011987`

### To top up the wallet

InstaPay bank transfer:
- **To:** Paynamics Technologies Inc
- **Account:** `201614462139`

### To revert to Netbank

Remove or comment out `XCHANGE_PAYOUT_PROVIDER` in `.env`. No other changes needed.

---

## Revenue Wallet (Deferred)

Not created yet. Only the settlement wallet is needed for disbursements. When revenue collection is needed, create a second consumer wallet with a different email and complete Level 2 KYC.
