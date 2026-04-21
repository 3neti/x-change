# x-change

A modular Laravel platform for **Pay Code issuance**, **wallet-based transactions**, and **voucher lifecycle orchestration**, designed with a clean architecture, DTO-driven contracts, and full scenario-based test coverage.

---

## 🚀 Key Features

- Pay Code issuance with dynamic pricing  
- Wallet-based debit/credit flows  
- Scenario-driven lifecycle simulation engine  
- Multi-attempt contract validation (OTP, KYC, location, etc.)  
- Reconciliation-aware disbursement flows  
- JSON + CLI execution modes  
- Fully testable via Pest  

---

## 🔄 Lifecycle Engine

The Lifecycle Engine is a scenario-based simulation framework for:

- voucher issuance  
- redemption attempts  
- validation rules  
- disbursement + reconciliation  

### Run a scenario

```bash
php artisan xchange:lifecycle:run secret_required
```

### Run a specific attempt

```bash
php artisan xchange:lifecycle:run secret_required --only-attempt=wrong_secret_fails
```

### JSON output

```bash
php artisan xchange:lifecycle:run secret_required --json
```

---

## 🧠 Reconciliation Model

| Provider Status | System Status | Behavior |
|----------------|--------------|---------|
| completed      | succeeded    | finalized |
| failed         | pending      | requires review |
| unknown        | unknown      | investigation |

> Failed provider responses are NOT automatically finalized as failed.  
> They are preserved as **pending + needs_review** for safety.

---

## 🧪 Testing

Run full lifecycle suite:

```bash
./vendor/bin/pest packages/x-change/tests/Feature/Console
```

Coverage includes:

- contract scenarios  
- presence validation  
- KYC flows  
- reconciliation recording  
- reconciliation resolution  
- CLI + JSON execution  

---

## 🧩 Architecture

```
HTTP → Controller → Action → Service → Domain → DTO → Response
```

### Layers

**Actions**
- Orchestrate business logic  
- Return DTOs  

**DTOs**
- Strongly typed outputs  
- Serialization via toArray()  

**Services**
- Pricing  
- Idempotency  
- External integrations  

**Contracts**
- External abstraction layer  

---

## 🔒 Idempotency

Same request → same response  
Different payload → 409 conflict  

---

## 🎯 Design Principles

- Contract-first  
- Scenario-driven  
- Deterministic execution  
- Safety-first reconciliation  
- Test-backed guarantees  

---

## 🛣️ Roadmap

- Scenario grouping (`--group`)  
- Lifecycle UI dashboard  
- CI regression testing  
- Extract lifecycle engine as standalone package  

---

## 🧠 Mental Model

Controllers → JSON  
Actions → DTO  
Services → arrays  
Contracts → abstractions  
