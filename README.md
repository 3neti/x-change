# x-change

A modular Laravel package for **Pay Code issuance**, **wallet-based transactions**, and **issuer onboarding**, designed with a clean API contract, DTO-driven architecture, and full test coverage.

---

# 🧩 Core Architecture

The system follows a layered, contract-first architecture:

HTTP → Request → Controller → Action → Service → External / Domain → DTO → API Response

---

# 📦 Key Components

## 1. Actions (Application Layer)

Actions orchestrate business logic.

Examples:
- GeneratePayCode
- EstimatePayCodeCost
- OnboardIssuer
- OpenIssuerWallet

### Responsibilities
- Coordinate services
- Normalize outputs into DTOs
- Enforce domain flow

### Rule
All actions MUST return DTOs (never raw arrays)

---

## 2. DTOs (Data Transfer Objects)

Located in:
src/Data/

Examples:
- GeneratePayCodeResultData
- PricingEstimateData
- OnboardIssuerResultData
- OpenIssuerWalletResultData
- DebitData
- WalletData
- IssuerData

### Purpose
- Define strict output contracts
- Provide serialization (toArray())
- Enable consistent API responses

---

## 3. Controllers (Transport Layer)

Controllers:
- Accept validated input
- Call Actions
- Return standardized API responses

---

## 4. ApiResponseFactory

Standardizes all responses:

{
  "success": true,
  "data": {...},
  "meta": {...}
}

---

## 5. Request Validation

Key Pattern:

| Case | Rule |
|------|------|
| Required object | required|array |
| Allow empty array | present|array |

---

## 6. Services

- PricingService
- IdempotencyService
- ApiResponseFactory

---

## 7. Contracts

- IssuerOnboardingContract
- WalletProvisioningContract
- AuditLoggerContract

---

## 8. Reports

- xchange-revenue-pending
- xchange-revenue-collections
- xchange-revenue-summary
- xchange-revenue-by-instruction

---

# 🔁 Flow Overview

Pay Code Generation → DTO → API  
Onboarding → DTO → API  

---

# 🔒 Idempotency

POST /pay-codes  
Same request → same response  
Different payload → 409  

---

# 🧪 Testing

55 passed  
0 failed  
2 skipped  
360 assertions  

---

# 🚀 Next Steps

- Transaction history
- Wallet API
- KYC integration

---

# 🧠 Mental Model

Controllers → JSON  
Actions → DTO  
Services → arrays  
Contracts → abstractions  
