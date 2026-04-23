# x-change

**Programmable Disbursement Infrastructure (PDI)** for banks and EMIs.

x-change is an API-first financial infrastructure platform that enables institutions to issue, claim, and disburse **Pay Codes** — bank-issued digital settlement instruments backed by deposits.

---

## 🧾 In Plain Terms

x-change allows a bank to turn money into a **secure, digital claim code**.

> Think of it as **cash-in-an-envelope — but digital, traceable, and programmable.**

Instead of:
- handing out physical cash
- requiring recipients to have bank accounts

A bank can:
- generate a Pay Code
- send it via SMS, QR, or print
- allow the recipient to claim funds securely

---

## 💡 Why This Exists

Banks traditionally earn from **interest on deposits**.

But modern financial platforms earn from **transactions**.

x-change enables banks to:

> **convert deposits into programmable payment instruments that generate transaction activity**

This transforms banks from:
- passive deposit holders

into:
- **active transaction platforms**

---

## 🏦 What is a Pay Code?

A Pay Code is a:

> **bank-issued digital settlement instruction represented as a code**

It is always:
- backed by real funds
- issued from a bank account
- redeemable through controlled workflows

A Pay Code can be used to:
- transfer money
- distribute funds
- settle payments
- enable merchant transactions

---

## 🔄 Core Lifecycle

```text
Onboard → KYC → Wallet → Fund → Issue Pay Code → Claim → Redeem → Disburse
```

This lifecycle is enforced by the system:

- issuance is **wallet-backed**
- wallet is **KYC-gated**
- pricing is **mandatory**
- disbursement is **auditable**

---

## 💰 Revenue Model (For Banks)

x-change enables multiple revenue streams:

### Transaction Fees
- issuance fees
- redemption fees
- settlement fees

### Merchant Processing
- accept Pay Codes as payment
- minimal infrastructure required

### Float
- unredeemed Pay Codes retain deposit value
- banks benefit from float

### Platform Licensing
- banks can expose Pay Code APIs to partners

---

## 🏦 Real-World Use Cases

### Government Distribution
- subsidies
- disaster relief
- social benefits

### Remittance
- domestic and international transfers
- cash pickup via code

### Payroll & Gig Economy
- pay workers without requiring bank accounts

### Insurance & Claims
- controlled, identity-verified payouts

### Corporate Disbursements
- refunds
- incentives
- reimbursements

---

## ⚙️ Core Capabilities

### Pay Code Issuance
- Generate deposit-backed vouchers
- Embed rules, pricing, and validation

### Claim & Redemption Engine
- Multi-step flows (OTP, KYC, location, selfie, signature)
- Contract-based validation

### Programmable Rules
- expiration
- geolocation
- identity requirements
- merchant restrictions

### Disbursement Orchestration
- bank and EMI integration
- settlement rail routing

### Pricing Engine (First-Class)
- tariff-based pricing
- component-level fees (KYC, OTP, etc.)

---

## 🧩 Architecture

```
API → Action → Service → Domain → DTO → Response
```

### Design Principles
- API-first
- Contract-driven
- Deterministic execution
- Safety-first financial handling

---

## 🔄 Claim Flow

```
POST /pay-codes/{code}/claim/start
POST /pay-codes/{code}/claim/complete
POST /pay-codes/{code}/claim/submit
```

---

## 🔒 Contract Model

- `inputs.fields` → what must be collected
- `validation.*` → what must be true

This ensures:
- auditability
- compliance
- deterministic outcomes

---

## 🧪 Testing

Run lifecycle scenarios:

```bash
php artisan xchange:lifecycle:run secret_required
```

Run tests:

```bash
./vendor/bin/pest
```

---

## 🚀 Deployment Model

x-change is designed for:

- **bank-hosted deployment**
- integration with internal systems
- API exposure to partners

No central x-change server is required.

---

## 🔐 License

This software is **proprietary**.

Use requires a commercial agreement with 3neti.

📧 licensing@3neti.com

---

## 🧠 Strategic Positioning

x-change enables banks to:

- reclaim transaction flows from external networks
- expand merchant acceptance at low cost
- digitize cash-based ecosystems
- extend services to underserved populations

---

## 📌 Summary

x-change is a **Programmable Disbursement Infrastructure platform** that transforms deposits into **active, programmable settlement instruments**, enabling banks to generate transaction revenue, expand payment reach, and control financial flows.

---
