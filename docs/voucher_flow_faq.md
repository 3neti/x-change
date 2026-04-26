# Phase 1 Completion Test — Voucher Flow Questions

Phase 1 is done when every voucher scenario can answer these questions clearly and consistently.

## Voucher Flow Scenario Checklist

| Question | Purpose |
|---|---|
| Who initiates? | Identifies the actor starting the flow. |
| Who authorizes? | Identifies who must approve the movement or claim. |
| Who receives money? | Identifies the final beneficiary of the money movement. |
| Does money move inward, outward, or both? | Defines the financial direction. |
| Does cash need approval, OTP, or envelope? | Defines gating and compliance requirements. |
| What closes the voucher? | Defines lifecycle completion. |
| What do we call it in code? | Locks developer terminology. |
| What do users see in the UI? | Locks business-facing terminology. |

---

# Flow Matrix

## 1. Disbursable Voucher — Self Claim / Cash Out

| Question | Answer |
|---|---|
| Who initiates? | Claimant / redeemer |
| Who authorizes? | Voucher rules, optionally OTP/KYC/selfie/location/signature |
| Who receives money? | Claimant |
| Money direction | Outward |
| Approval / OTP / envelope | Optional, based on claim policy |
| What closes the voucher? | Full disbursement, expiry, or exhaustion of slices |
| Code name | `disbursable` |
| UI label | Cash Out Voucher |
| Pay Code role | Pay Code is presented to claimant to start cash-out flow |

---

## 2. Disbursable Voucher — Open-Slice Withdrawal

| Question | Answer |
|---|---|
| Who initiates? | Claimant / voucher holder |
| Who authorizes? | Voucher rules; interval policy; optional OTP/KYC/selfie/location |
| Who receives money? | Claimant or nominated bank/wallet account |
| Money direction | Outward |
| Approval / OTP / envelope | Optional OTP; open-slice interval enforced |
| What closes the voucher? | Remaining balance reaches zero, max slices reached, or expiry |
| Code name | `disbursable.open_slice` |
| UI label | Partial Cash Out Voucher |
| Pay Code role | Same Pay Code can support multiple partial withdrawals |

---

## 3. Disbursable Voucher — Ownership Claim / Gift Check

| Question | Answer |
|---|---|
| Who initiates? | Intended owner / recipient |
| Who authorizes? | Voucher rules; issuer-defined ownership requirements |
| Who receives money? | Nobody initially; ownership is bound |
| Money direction | No money movement yet |
| Approval / OTP / envelope | Optional OTP/KYC/selfie/location/signature |
| What closes the voucher? | Not closed; voucher becomes owner-bound |
| Code name | `disbursable.owner_claim` |
| UI label | Claim Voucher Ownership |
| Pay Code role | Pay Code lets recipient claim control of the voucher |

---

## 4. Disbursable Voucher — Delegated Spend / Request-to-Withdraw

| Question | Answer |
|---|---|
| Who initiates? | Requestor, merchant, classmate, or service provider |
| Who authorizes? | Voucher owner |
| Who receives money? | Requestor / merchant / nominated recipient |
| Money direction | Outward |
| Approval / OTP / envelope | Owner OTP or trusted-vendor policy; low-value no-OTP possible |
| What closes the voucher? | Balance reaches zero, spend limit exhausted, expiry, or owner revocation |
| Code name | `disbursable.delegated_spend` |
| UI label | Pay from Voucher |
| Pay Code role | Pay Code is presented by owner or scanned by merchant to request payment |

---

## 5. Collectible Voucher — Self Top-Up

| Question | Answer |
|---|---|
| Who initiates? | Voucher owner / payer |
| Who authorizes? | Payment rail and voucher collection policy |
| Who receives money? | Voucher-governed balance, then optionally owner wallet |
| Money direction | Inward |
| Approval / OTP / envelope | Usually no OTP; provider payment confirmation required |
| What closes the voucher? | Target amount reached, manual close, or expiry |
| Code name | `collectible.self_topup` |
| UI label | Top Up Voucher |
| Pay Code role | Pay Code generates payment QR/link for funding the voucher |

---

## 6. Collectible Voucher — Merchant / Bill Payment

| Question | Answer |
|---|---|
| Who initiates? | Payer / customer |
| Who authorizes? | Payer through payment rail |
| Who receives money? | Voucher issuer / merchant / biller wallet |
| Money direction | Inward |
| Approval / OTP / envelope | Provider confirmation; optional validation requirements |
| What closes the voucher? | Target amount reached, invoice paid, or expiry |
| Code name | `collectible.payment` |
| UI label | Pay In Voucher |
| Pay Code role | Pay Code represents the payment instruction or invoice reference |

---

## 7. Settlement Voucher — Loan Disburse Then Collect

| Question | Answer |
|---|---|
| Who initiates? | Lender / issuer |
| Who authorizes? | Borrower onboarding rules, lender policy, optional approval |
| Who receives money? | Borrower first; lender later through repayment |
| Money direction | Both |
| Approval / OTP / envelope | Optional KYC/OTP/envelope depending on loan policy |
| What closes the voucher? | Collection target fully paid or settlement manually closed |
| Code name | `settlement.disburse_then_collect` |
| UI label | Loan Settlement Voucher |
| Pay Code role | Same Pay Code anchors both disbursement and repayment references |

---

## 8. Settlement Voucher — Insurance / Evidence-Based Collection

| Question | Answer |
|---|---|
| Who initiates? | Hospital / provider |
| Who authorizes? | Patient evidence flow; insurer review or policy |
| Who receives money? | Hospital / provider |
| Money direction | Usually inward only, but settlement-capable |
| Approval / OTP / envelope | Envelope required; may require KYC, selfie, location, signature |
| What closes the voucher? | Insurer payment collected and envelope accepted |
| Code name | `settlement.collect_only_with_evidence` |
| UI label | Insurance Settlement Voucher |
| Pay Code role | Pay Code anchors the patient-facing proof and insurer-facing settlement claim |

---

## 9. Settlement Voucher — Collect Then Release / Escrow

| Question | Answer |
|---|---|
| Who initiates? | Buyer, payer, platform, or escrow creator |
| Who authorizes? | Settlement rules, envelope readiness, or platform approval |
| Who receives money? | Seller / service provider after release |
| Money direction | Both |
| Approval / OTP / envelope | Envelope or milestone approval required before release |
| What closes the voucher? | Funds released, refund processed, or dispute resolved |
| Code name | `settlement.collect_then_release` |
| UI label | Escrow Settlement Voucher |
| Pay Code role | Pay Code collects funds first, then governs later release |

---

## 10. Settlement Voucher — Bilateral Closeout

| Question | Answer |
|---|---|
| Who initiates? | Either party or platform |
| Who authorizes? | Settlement policy, envelope, and possibly both parties |
| Who receives money? | Depends on net settlement result |
| Money direction | Both |
| Approval / OTP / envelope | Usually envelope and approval required |
| What closes the voucher? | Net obligations are satisfied |
| Code name | `settlement.bilateral_closeout` |
| UI label | Settlement Voucher |
| Pay Code role | Pay Code is the shared reference for all settlement movements |

---

# Terminology Lock

## Code Terms

| Concept | Code Term |
|---|---|
| Outbound claim voucher | `disbursable` |
| Inbound payment voucher | `collectible` |
| Bilateral governed voucher | `settlement` |
| Voucher code shown externally | `pay_code` |
| Partial withdrawal | `open_slice` |
| Owner-bound voucher | `owner_claim` |
| Third-party request against voucher balance | `delegated_spend` |
| Evidence package | `settlement_envelope` |
| Payment into voucher | `collect` |
| Money out of voucher | `withdraw` |
| Final bilateral resolution | `settle` |

## UI Terms

| Code Term | UI Label |
|---|---|
| `disbursable` | Cash Out Voucher |
| `collectible` | Pay In Voucher |
| `settlement` | Settlement Voucher |
| `pay_code` | Pay Code |
| `open_slice` | Partial Cash Out |
| `owner_claim` | Claim Voucher |
| `delegated_spend` | Pay from Voucher |
| `settlement_envelope` | Proof Package |
| `collect` | Pay In |
| `withdraw` | Cash Out |
| `settle` | Settle |

---

# Phase 1 Done Criteria

Phase 1 is complete when:

1. Every scenario maps to one canonical flow type.
2. Every scenario has a clear initiator, authorizer, and money receiver.
3. Every scenario declares money direction: inward, outward, both, or none.
4. Every scenario declares its gates: OTP, approval, validation, envelope, or none.
5. Every scenario has a closing condition.
6. Every scenario has a code term.
7. Every scenario has a user-facing label.
8. Pay Code is consistently treated as the external presentation of a voucher, not the voucher itself.

At that point, implementation can proceed without terminology drift.
