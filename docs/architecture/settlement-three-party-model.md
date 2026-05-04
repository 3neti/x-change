# Settlement Three-Party Model

## Purpose

This document defines the generic domain model for settlement vouchers in x-change.

A settlement voucher is different from an ordinary disbursable voucher or ordinary collectible voucher. It represents a conditional claim where payment should move only after a required evidence envelope is fulfilled.

The model involves three primary roles:

```text
Service Provider      → issuer / claimant / settlement recipient
Beneficiary           → attestor / redeemer / proof participant
Payer                 → insurer / funding party / obligor
```

Canonical rule:

```text
The provider issues the settlement voucher.
The beneficiary redeems only to attest.
The payer pays only when the settlement envelope is ready.
The provider receives settlement.
```

---

## Generic Examples

### Healthcare Insurance

```text
Service Provider: Hospital
Beneficiary:      Patient / insured member
Payer:            PhilHealth / HMO / insurer
```

Example:

```text
Gross bill:                 ₱30,000
Beneficiary payable:         ₱10,000
Insurance settlement value:  ₱20,000
```

The hospital issues a settlement voucher for the covered amount.  
The patient redeems the voucher to attest that care was actually received.  
The insurer pays the hospital only after the settlement envelope is fulfilled.

---

### Motor Vehicle Insurance

```text
Service Provider: Repair shop
Beneficiary:      Car owner / insured party
Payer:            Insurance company
```

Example:

```text
Repair estimate:          ₱80,000
Owner participation:      ₱10,000
Insurance settlement:     ₱70,000
```

The repair shop issues or prepares a settlement voucher for the covered repair amount.  
The car owner redeems the voucher to attest that the repair work is real, authorized, and connected to the insured incident.  
The insurer pays the repair shop through the settlement voucher payment endpoint only after the required envelope is complete.

---

### Contracting / Subcontracting

```text
Service Provider: Contractor / subcontractor
Beneficiary:      Project owner / recipient / inspector / approving party
Payer:            Principal / lender / funder / government agency
```

Example:

```text
Work completed:       Milestone or service delivery
Beneficiary confirms: Work was received or inspected
Payer releases:       Contracted settlement amount
```

The subcontractor or service provider issues a settlement voucher.  
The beneficiary or approving party attests that the work, delivery, inspection, or milestone is valid.  
The payer releases funds only when the settlement envelope is fulfilled.

---

## Roles

### Service Provider

The service provider is the party that renders the service, performs the repair, delivers the work, or creates the reimbursable claim.

Examples:

```text
hospital
clinic
repair shop
contractor
subcontractor
developer
supplier
school
training provider
housing developer
```

The service provider may be:

```text
voucher issuer
claimant
settlement recipient
```

The service provider typically supplies claim details such as:

```text
invoice
statement of account
diagnosis or service report
repair estimate
work order
delivery receipt
inspection report
photos
supporting documents
```

The service provider receives payment once the payer settles the voucher.

---

### Beneficiary

The beneficiary is the party whose condition, identity, consent, receipt, or participation proves that the claim is legitimate.

Examples:

```text
patient
insured member
car owner
home buyer
project owner
recipient
student
borrower
authorized representative
```

The beneficiary redeems the settlement voucher only to attest facts such as:

```text
I exist.
I received the service.
I authorized the claim.
I saw or accepted the work.
I participated in the transaction.
I can provide identity, signature, selfie, location, or other required evidence.
```

In settlement flows:

```text
redemption is not payment
redemption is attestation
```

The beneficiary does not receive the settlement amount unless the specific business model explicitly says so.

---

### Payer

The payer is the party obligated to release funds once the envelope is complete.

Examples:

```text
insurance company
PhilHealth
HMO
principal contractor
government agency
housing loan provider
Pag-IBIG
bank
lender
employer
grantor
program sponsor
```

The payer pays only when the settlement envelope is ready.

The payer-side action is:

```text
settlement payment
collection
payment confirmation
```

not beneficiary redemption.

---

## Settlement Envelope

The settlement envelope is the evidence layer.

It may contain:

```text
beneficiary identity
beneficiary signature
beneficiary selfie
location evidence
service provider claim payload
invoice or billing details
repair estimate
medical records
inspection report
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

## Correct Generic Flow

```text
1. Service provider issues settlement voucher
2. Beneficiary redeems / attests
3. Service provider and/or beneficiary completes the settlement envelope
4. Settlement envelope is evaluated
5. Payer pays using the settlement voucher
6. Service provider receives settlement
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

The beneficiary’s redemption creates proof. It does not necessarily move money to the beneficiary.

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

### Withdrawal is not part of the core settlement story

Withdrawal belongs to cash-out or disbursement scenarios.

In a settlement flow:

```text
The beneficiary does not withdraw.
The service provider does not redeem its own voucher.
The payer pays.
The service provider receives.
```

A settlement voucher must therefore be blocked from ordinary claimant withdrawal or disbursement unless a specific settlement driver explicitly allows such behavior.

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

### Beneficiary attestation

```text
SubmitSettlementAttestation
→ SubmitPayCodeClaim
```

Meaning:

```text
Beneficiary redeems/attests.
No settlement funds are released to the beneficiary.
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
Payer pays the service provider, but only after the envelope is ready.
```

---

### Settlement envelope metadata

Patient/beneficiary attestation is persisted into:

```text
metadata.settlement_envelope.attestation
metadata.settlement_envelope.payload
```

This lets later readiness evaluation use persisted evidence, not only one-time request payloads.

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
Settlement vouchers cannot be disbursed to the claimant through ordinary withdrawal flow.
```

---

## Domain Rule

Use this sentence as the canonical model:

```text
A settlement voucher is issued by the service provider, attested by the beneficiary, and paid by the payer.
```

For healthcare:

```text
The hospital issues the settlement voucher.
The patient redeems it only to attest care.
The insurer pays against it only when the envelope is ready.
The hospital receives settlement.
```

For car insurance:

```text
The repair shop issues or prepares the settlement voucher.
The car owner redeems it only to attest the repair claim.
The insurer pays against it only when the envelope is ready.
The repair shop receives settlement.
```

For contracting:

```text
The service provider issues the settlement voucher.
The beneficiary or approving party attests delivery or completion.
The payer releases funds only when the envelope is ready.
The service provider receives settlement.
```

---

## Naming Rules Going Forward

Use these conventions:

```text
Use "redemption" only for generic voucher mechanics.
Use "attestation" for beneficiary-side settlement behavior.
Use "collection/payment" for payer-side settlement behavior.
Use "withdrawal/disbursement" only for cash-out or outward payout flows.
Use "service provider" instead of hospital when describing the generic role.
Use "beneficiary" or "attestor" instead of patient when describing the generic role.
Use "payer" or "obligor" instead of PhilHealth when describing the generic role.
```

---

## Why This Matters

This model prevents a single party from controlling the whole claim.

```text
Service provider declares the claim.
Beneficiary confirms the reality of the service or event.
Payer releases funds only after the envelope is ready.
```

This is the trust model.

It is also the reason settlement vouchers are more than payment codes.

They are programmable settlement instructions.
