# ADR-001: Dual-Ledger Provider Model

**Date:** 2026-05-08
**Status:** Accepted (with identified risks)
**Decision makers:** Lester Hurtado, Oz (AI agent)
**Context:** Paynamics Constellation integration into x-change alongside Netbank

---

## Summary

x-change now supports multiple payout providers (Netbank, Paynamics) switchable per-scenario or per-environment. Each external provider maintains its own settlement balance independently of x-change's internal wallet ledger. This document records the architectural implications, risks, and mitigation strategies for operating with dual (or multi) ledger systems.

---

## Context

### The Internal Ledger

x-change uses bavix/wallet as its internal ledger. When a Pay Code is issued:
1. The issuer's internal wallet is debited (issuance fee)
2. A voucher is created with a face value
3. On redemption, the voucher is claimed and a disbursement is initiated

The internal wallet is the **business ledger of truth** — it tracks who owes what, what's been issued, what's been claimed, and what fees were charged.

### The External Provider Ledger

Each payout provider has its own pool of funds:

**Netbank:**
- Source bank account (`113-001-00001-9`)
- Funded independently (bank transfers, corporate deposits)
- Balance managed entirely outside x-change
- No API to query the source account balance
- Fee: ₱15 per InstaPay transaction (configured in x-change pricing)

**Paynamics Constellation:**
- Settlement wallet (`CNSTWLLT9GSPQ1`)
- Funded via InstaPay to `201614462139` at Paynamics Technologies Inc
- Balance queryable via API (`constellation:wallet-balance`)
- Fee: ₱20 per PTIINSTAPAY transaction (charged by Paynamics from the wallet)
- OTP required per disbursement
- KYC Level 2 required on the wallet

### The Convergence Point

Both providers implement the same `PayoutProvider` contract:

```
PayoutProvider::disburse(PayoutRequestData): PayoutResultData
PayoutProvider::checkStatus(string): PayoutResultData
PayoutProvider::getRailFee(SettlementRail): int
```

Everything upstream of `disburse()` — voucher issuance, wallet debits, pricing, claim validation, withdrawal pipeline — is completely provider-agnostic. The provider is resolved from configuration and injected via the container.

---

## The Dual-Ledger Problem

### What It Is

Two independent balances exist that must both be sufficient for a disbursement to succeed:

```
Internal Wallet Balance ≥ voucher face value + fees
External Provider Balance ≥ disbursement amount + provider fees
```

These two balances are **not synchronized**. The internal wallet can show ₱100,000 available while the external provider has ₱0. Or vice versa.

### Why It Exists

The internal wallet serves a different purpose than the external balance:

- **Internal wallet** = business accounting (who paid what, revenue tracking, issuance debits)
- **External balance** = operational liquidity (actual money available for disbursement)

This separation is intentional — the internal wallet is a ledger, not a bank account. It tracks obligations and revenue, not cash.

### When It's a Problem

1. **Insufficient external funds:** Internal wallet approves a ₱1,000 disbursement, but the Paynamics wallet only has ₱50. The disbursement fails at the provider level after the internal wallet has already been debited during issuance.

2. **Fee drift:** x-change charges ₱15 (Netbank's fee) but Paynamics charges ₱20. If the provider is switched, the pricing model is wrong — the issuer is undercharged by ₱5 per transaction.

3. **Balance opacity:** Netbank's source account balance is invisible to x-change. There's no pre-flight check. Paynamics at least exposes the balance via API.

4. **Refund asymmetry:** If a Paynamics disbursement fails, the ₱20 fee may or may not be refunded by Paynamics, but the internal wallet has already recorded the issuance debit. Manual reconciliation is needed.

5. **Multi-provider drift:** If both providers are active simultaneously (different scenarios using different providers), tracking which external balance corresponds to which internal transactions becomes complex.

---

## Current Mitigations (Already In Place)

### 1. Provider-level reconciliation
`DisbursementReconciliation` records every disbursement attempt with provider reference, status, amount, and raw response. This provides an audit trail for manual reconciliation.

### 2. Status polling
The `LifecycleDisbursementPoller` checks provider status after each disbursement, catching failures early. The `checkStatus()` contract is provider-agnostic.

### 3. Centralized settlement wallet
Both providers use a single centralized settlement account (not per-user wallets). This limits the divergence to one external balance per provider.

### 4. Provider switching is explicit
Providers are switched via `--provider`, scenario config, or `.env` — never implicit. The active provider is logged in the output.

---

## Risk Assessment

### Low Risk (Current State)

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| External balance runs out | Medium | Medium | Manual monitoring, top-up procedures documented |
| Fee mismatch between providers | Low | Low | Currently only one provider active at a time |
| OTP expiry during lifecycle | Medium | Low | Retry manually; OTP waiver pending with Paynamics |

### Medium Risk (Near-Term Production)

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Disbursement fails after internal debit | Medium | High | Needs pre-flight balance check or compensating transaction |
| Provider fee not reflected in pricing | High | Medium | Needs provider-aware pricing model |
| Reconciliation drift over time | Medium | Medium | Needs automated reconciliation job |

### High Risk (Future Multi-Provider)

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Per-user Paynamics wallets | Low | Very High | Out of scope — explicitly deferred |
| Cross-provider refund/reversal | Low | High | No mechanism exists today |
| Split disbursements across providers | Low | Very High | No orchestration exists today |

---

## Recommended Mitigations

### Policy Mitigations (No Code Required)

**P1. Single active provider per deployment.**
Don't run Netbank and Paynamics simultaneously in production. Use one as primary, the other as fallback. The `--provider` option is for testing and certification, not production multi-provider routing.

**P2. Pre-fund external balance with buffer.**
Maintain external provider balance ≥ 2x expected daily disbursement volume. Monitor weekly.

**P3. Fee alignment review on provider switch.**
Before switching the production provider, review and update `x-change.pricelist.cash.amount` to reflect the new provider's fee structure.

**P4. Daily reconciliation review.**
Review `disbursement_reconciliations` table daily for `needs_review = true` or `status = unknown` entries.

### Code Mitigations (Short-Term)

**C1. Pre-flight balance check for Paynamics.**
Before `disburse()`, query the settlement wallet balance and refuse if insufficient. Paynamics exposes this via API; Netbank does not.

```php
// In ConstellationPayoutProvider::disburse()
$balance = $this->getWalletBalance($walletId);
$required = $amount + $this->estimateFee();
if ($balance < $required) {
    throw new InsufficientProviderBalanceException($balance, $required);
}
```

Estimated effort: Small (1 method addition to ConstellationPayoutProvider)

**C2. Provider-aware fee in `getRailFee()`.**
Ensure `getRailFee()` returns the actual provider fee, not a hardcoded value. Netbank and Paynamics charge different fees. This is already partially done — both providers read from their own config. But the x-change pricelist should reference `getRailFee()` dynamically rather than a static config value.

Estimated effort: Medium (pricing service change)

**C3. Add `SystemReadiness` check before lifecycle run.**
The `ConstellationSystemReadiness` already checks wallet existence and status. Wire it into the lifecycle engine as a pre-flight check.

Estimated effort: Small (engine hook)

**C4. Reconciliation alert for stale pending disbursements.**
Add a scheduled command that flags disbursements stuck in `pending` status for more than N minutes. Both providers can have delayed settlements, but indefinitely pending transactions need human review.

Estimated effort: Small (scheduled command)

### Design Mitigations (Medium-Term)

**D1. Provider balance as a system health metric.**
Expose provider balance in a dashboard or health check endpoint. For Paynamics, this is a simple API call. For Netbank, this would require API support from Netbank or manual entry.

**D2. Compensating transactions on disbursement failure.**
When a disbursement fails after the internal wallet was debited (during issuance), automatically credit the issuer's wallet back. This requires careful idempotency handling to avoid double-crediting.

**D3. Provider-specific pricing tiers.**
Allow the pricelist to vary by provider:

```php
'cash.amount' => [
    'price' => 1500, // default (Netbank)
    'provider_prices' => [
        'paynamics' => 2000, // ₱20 for Paynamics
    ],
],
```

This way the issuer is charged correctly regardless of which provider is active.

**D4. Abstract the "settlement pool" concept.**
Introduce a `SettlementPool` interface that both the internal wallet and external provider balance can implement. This allows pre-flight checks against either ledger without coupling to a specific provider.

```php
interface SettlementPool
{
    public function availableBalance(): Money;
    public function canDisburse(Money $amount): bool;
    public function identifier(): string;
}
```

### Architectural Mitigations (Long-Term, Out of Scope)

**A1. Per-user Paynamics wallets.**
Instead of a centralized settlement wallet, each user gets their own Paynamics wallet. This eliminates the dual-ledger problem but introduces massive complexity (KYC per user, wallet lifecycle management, balance sync). Explicitly deferred per the integration strategy document.

**A2. Event-sourced ledger.**
Replace bavix/wallet with an event-sourced ledger that records provider-specific events (disbursement requested, provider confirmed, provider failed, refund issued). This provides perfect auditability but is a large architectural change.

**A3. Provider-agnostic escrow model.**
On voucher issuance, escrow the disbursement amount in the provider's system (e.g., Paynamics pre-transfer). On claim, settle the escrow. This ensures the external balance is reserved before the voucher is created. Requires deep provider integration and doesn't work for providers without escrow APIs.

---

## Decision

**Accept the dual-ledger model as-is for now.** It is operationally equivalent to how Netbank has always worked — the external balance is an infrastructure concern, not a business logic concern.

**Implement policy mitigations P1–P4 immediately** (no code required).

**Prioritize code mitigations C1 and C4** as the first engineering improvements — pre-flight balance check and stale reconciliation alerting provide the highest safety margin for the least effort.

**Defer design and architectural mitigations** until production volume justifies the complexity.

---

## Appendix: Provider Comparison Matrix

| Dimension | Netbank | Paynamics Constellation |
|---|---|---|
| Settlement model | Bank source account | Constellation consumer wallet |
| Balance visibility | No API | API available (`wallet-balance`) |
| Fee per transaction | ₱15 (InstaPay) | ₱20 (PTIINSTAPAY) |
| Fee deduction | External (not from wallet) | From wallet balance |
| OTP required | No | Yes (per transaction) |
| KYC requirement | None (bank account) | Level 2 on wallet |
| Funding method | Bank transfer | InstaPay to Paynamics Technologies Inc |
| Status check | `checkDisbursementStatus()` | `getCashOutByRequestId()` + `getTransactionByRequestId()` |
| Wallet type | N/A (bank account) | Personal (Consumer) |
| Escrow/pre-transfer | Not supported | Supported (but not used) |
| Postback/webhook | Supported | Supported (notification_url) |
| Bank ID format | Short codes (BDO, UBP) | MongoDB ObjectIds via bank_map |
| Request ID max length | No known limit | 36 characters |
| `meta_data` required | No | Yes (empty object) |

---

## References

- `docs/todo/paynamics_integration_report.md` — Full integration report
- `docs/todo/paynamics_remaining_steps.md` — Remaining operational steps
- `docs/todo/initial_paynamics_integration.md` — Original strategy document
- `config/emi.php` — Provider registry
- `config/constellation.php` — Paynamics configuration
- `src/Lifecycle/Scenarios/LifecycleScenarioEngine.php` — Provider resolution logic
