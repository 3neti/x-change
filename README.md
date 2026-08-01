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

The normal workflow is intentionally short:

```bash
php artisan x-change:setup
php artisan x-change:deploy production
```

See [DEPLOYMENT.md](./DEPLOYMENT.md) for the canonical local, Laravel Cloud,
automation, recovery, naming, and environment-contract runbook.

### Commissioning a fresh deployment

X-Change starts fail-closed. Until installation finishes, ordinary web, API,
claim, payment, authentication, and webhook traffic receives a neutral `503`
response. Laravel's `/up` endpoint remains available for liveness, while
`/x/ready` reports whether X-Change is operational.

The package does not accept credentials through the browser. The guided setup
may update a local `.env` only with explicit consent and an automatic backup;
production environments remain platform-managed. The lower-level server
workflow remains supported:

```bash
php artisan x-change:configure --profile=netbank
php artisan optimize:clear
php artisan x-change:doctor --pre-install --strict
php artisan x-change:install --force --no-interaction
php artisan x-change:doctor --strict
```

Set `XCHANGE_COMMISSIONING_ACCESS_TOKEN` to expose the detailed, read-only
operator checklist at `/x/commissioning/checklist`. The token is submitted by
POST, never placed in a URL, and rotating it invalidates existing checklist
sessions. When and only when `APP_ENV=local` and no token is configured, the
known development PIN `317537` is accepted for local setup convenience. It is
never accepted in production, staging, testing, or other environments. Never
deploy a live provider profile with `APP_ENV=local`; deployments must configure
a strong, unique token. Existing deployments may create their first manifest
only through:

```bash
php artisan x-change:commissioning:adopt \
  --confirm-existing-installation \
  --no-interaction
```

The adoption command refuses incomplete identity or Treasury topology. The
installation manifest contains only the profile, connection references,
package/manifest versions, completion time, and an HMAC fingerprint. It stores
no credential or provider response.

The protected checklist also records the runtime responsibilities that static
configuration cannot prove. For local development, keep these processes active:

```bash
php artisan queue:work database --queue=x-change-funding,x-change-feedback,default --sleep=3 --timeout=60
php artisan schedule:work
php artisan reverb:start # only when Reverb broadcasting is enabled
```

On Laravel Cloud, use Managed Queues or a Worker cluster, enable the Scheduler,
and attach managed WebSockets when needed. On Laravel Forge, configure the Queue
Worker, Laravel Scheduler, and optional Reverb integrations in the site UI.
Production does not run `schedule:work`, and managed Reverb does not run a
second application-owned `reverb:start` process.

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
