# Settlement Three-Party Model

## Purpose

This document defines the domain model for settlement vouchers in x-change.

Settlement vouchers are different from ordinary disbursable vouchers and ordinary collectible vouchers. They involve three parties:

```text
Hospital   → issuer / service provider / settlement recipient
Patient    → attestor / redeemer / proof-of-care participant
PhilHealth → payer / funding party
```

The key rule is:

```text
The patient redeems to attest.
PhilHealth pays to settle.
The hospital receives settlement.
```

---

## Core Example

A PhilHealth beneficiary undergoes a hospital procedure.

```text
Gross bill:                 ₱30,000
Patient payable after cover: ₱10,000
PhilHealth settlement value: ₱20,000
```

The hospital issues a settlement voucher for the PhilHealth-covered amount:

```text
Settlement voucher amount: ₱20,000
Settlement driver: philhealth-bst
```

The patient does not receive this ₱20,000.

Instead, the patient redeems the voucher to confirm that care was actually received.

---

## Roles

### Hospital

The hospital is the voucher issuer.

It creates the settlement voucher after rendering care and computing the covered claim amount.

The hospital may also attach clinical and billing information, such as:

```text
procedure details
diagnosis
prescriptions
x-ray or laboratory results
professional fees
hospital fees
claim documents
```

The hospital is also the settlement recipient once PhilHealth pays.

---

### Patient

The patient is the attestor.

The patient redeems the settlement voucher only to prove or acknowledge facts such as:

```text
I exist.
I received the care.
I consent to the claim.
I can provide identity, signature, selfie, location, or other required evidence.
```

In this flow:

```text
redemption is not payment
redemption is attestation
```

The patient does not receive the settlement amount.

---

### PhilHealth

PhilHealth is the payer.

PhilHealth pays only when the settlement envelope is ready.

The payer-side action is collection/payment confirmation, not patient redemption.

---

## Settlement Envelope

The settlement envelope is the evidence layer.

It may contain:

```text
patient identity
patient signature
patient selfie
location evidence
hospital claim payload
medical documents
authorization documents
manual checklist flags
payer validation flags
```

The envelope determines whether settlement is allowed.

For example, the `philhealth-bst` driver may require:

```text
payload_present
amount_verified
```

before the voucher becomes settleable.

---

## Correct Flow

```text
1. Hospital issues settlement voucher
2. Patient redeems / attests
3. Hospital and/or patient completes settlement envelope
4. Settlement envelope is evaluated
5. PhilHealth pays using the settlement voucher
6. Hospital receives settlement
7. Voucher is considered settled
```

---

## Important Distinctions

### Redemption is not payment

In ordinary voucher systems, redeeming often means receiving value.

In settlement vouchers:

```text
redeem = attest
```

The patient’s redemption creates proof. It does not move the settlement amount to the patient.

---

### Collection is settlement

The money-moving step happens when the payer confirms payment.

In x-change code, this is represented by:

```text
CollectVoucherFunds
```

For settlement flows, this means:

```text
CollectSettlementPayment
```

---

### Withdrawal is not part of the PhilHealth settlement story

Withdrawal belongs to cash-out or disbursement scenarios.

In the PhilHealth settlement flow:

```text
Hospital does not withdraw.
Patient does not withdraw.
PhilHealth pays.
Hospital receives.
```

A settlement voucher must therefore be blocked from ordinary claimant withdrawal or disbursement.

---

## Code Vocabulary

The package keeps generic action names for backward compatibility:

```text
SubmitPayCodeClaim
CollectVoucherFunds
Withdrawal*
```

Settlement-specific wrappers provide clearer domain language:

```text
SubmitSettlementAttestation
CollectSettlementPayment
```

These wrappers do not replace the generic engine. They clarify intent.

---

## Mapping to Code

### Patient attestation

```text
SubmitSettlementAttestation
→ SubmitPayCodeClaim
```

Meaning:

```text
Patient redeems/attests.
No settlement funds are released to the patient.
```

---

### Settlement payment

```text
CollectSettlementPayment
→ SettlementCollectionGate
→ CollectVoucherFunds
```

Meaning:

```text
PhilHealth pays hospital, but only after the envelope is ready.
```

---

### Settlement collection gate

```text
SettlementCollectionGate
```

Enforces:

```text
Settlement voucher collection is blocked until the envelope is ready.
```

---

### Withdrawal pipeline block

```text
BlockSettlementVoucherWithdrawalStep
```

Enforces:

```text
Settlement vouchers cannot be disbursed to the claimant.
```

---

## Domain Rule

Use this sentence as the canonical model:

```text
A settlement voucher is issued by the provider, attested by the patient, and paid by the payer.
```

For PhilHealth:

```text
The hospital issues the settlement voucher.
The patient redeems it only to attest care.
PhilHealth pays against it only when the envelope is ready.
The hospital receives settlement.
```

---

## Naming Rules Going Forward

Use these conventions:

```text
Use "redemption" only for generic voucher mechanics.
Use "attestation" for patient-side settlement behavior.
Use "collection/payment" for payer-side settlement behavior.
Use "withdrawal/disbursement" only for cash-out or outward payout flows.
```

---

## Why This Matters

This model prevents a single party from controlling the whole claim.

```text
Hospital declares the claim.
Patient confirms the reality of care.
PhilHealth releases funds only after the envelope is ready.
```

This is the trust model.

It is also the reason settlement vouchers are more than payment codes.

They are programmable settlement instructions.
