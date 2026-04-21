# BANK_API_BLUEPRINT.md  
x-change Bank-Grade API Blueprint  
Version: 1.0 (Draft for Public Release)

---

## 1. Purpose

This document defines the external, bank-grade API contract for the x-change platform.

It is designed for:
- Banks  
- Electronic Money Issuers (EMIs)  
- Regulated financial integrators  

It establishes:
- Security profile  
- Resource model  
- Lifecycle semantics  
- Idempotency guarantees  
- Audit and reconciliation behavior  

---

## 2. Positioning

x-change is not just an API.

It is an API-first financial workflow platform that exposes:
- issuance (Pay Codes)
- redemption / disbursement
- wallet-backed value transfer
- reconciliation and audit trails

This blueprint defines how that system is safely exposed to financial institutions.

---

## 3. Security Profile

### 3.1 Target Standard

The API SHALL align with:

- FAPI 2.0 Security Profile
- OAuth 2.0 Security BCP
- OWASP API Security Top 10 (2023)

---

### 3.2 Transport Security

- All partner connections MUST use mutual TLS (mTLS)
- TLS 1.2+ minimum
- Client certificates REQUIRED for confidential clients

---

### 3.3 Client Authentication

Supported:
- mTLS client authentication (primary)
- Private key JWT (optional fallback)

---

### 3.4 Token Model

- OAuth 2.0 access tokens
- Sender-constrained tokens:
  - mTLS-bound tokens
  - DPoP-bound tokens (where applicable)

---

### 3.5 Request Integrity

Every request MUST include:
- Authorization token
- Correlation ID
- Idempotency key (for state-changing requests)

Optional (recommended):
- Signed payload (JWS)

---

### 3.6 Non-Repudiation

Every request is bound to:
- client certificate fingerprint OR key ID
- token subject
- request signature context

All requests are:
- logged
- timestamped
- immutable

---

## 4. API Architecture

The API is split into three domains:

### 4.1 Partner API

Resources:
/partners  
/credentials  
/consents  
/webhooks  

Responsibilities:
- client onboarding
- certificate registration
- token issuance
- webhook configuration
- partner-level audit

---

### 4.2 Transaction API

Resources:
/accounts  
/wallets  
/quotes  
/payments  
/disbursements  
/reconciliations  
/events  

Responsibilities:
- quote generation
- payment/disbursement creation
- status tracking
- lifecycle transitions
- reconciliation retrieval

---

### 4.3 Operational API

Resources:
/idempotency-keys  
/audit-trails  
/event-traces  
/disputes  
/health  

Responsibilities:
- idempotency lookup
- trace debugging
- dispute resolution
- audit inspection
- partner telemetry

---

## 5. Resource Model

### 5.1 Common Fields

{
  "id": "uuid",
  "external_reference": "string",
  "status": "string",
  "created_at": "ISO8601",
  "updated_at": "ISO8601",
  "version": "integer",
  "correlation_id": "string",
  "idempotency_key": "string",
  "audit": {
    "actor": "client_id",
    "auth_context": "mTLS|DPoP",
    "fingerprint": "string"
  }
}

---

## 6. State Machines

accepted → processing → succeeded → failed → pending_review → reversed

---

## 7. Idempotency Contract

Header:
Idempotency-Key: <unique-key>

Behavior:
- same key + same payload → replay original response
- same key + different payload → conflict (409)
- missing key → rejected (400)

---

## 8. Webhook Contract

Event structure:
{
  "id": "event_id",
  "type": "payment.succeeded",
  "timestamp": "ISO8601",
  "data": {},
  "correlation_id": "string",
  "signature": "string"
}

---

## 9. Reconciliation Model

Endpoints:
GET /reconciliations  
GET /reconciliations/{id}  
POST /reconciliations/{id}/resolve  

Principle:
Expose uncertainty, not hide it.

---

## 10. Audit Model

Every action produces:
- request_id  
- correlation_id  
- actor  
- certificate fingerprint  
- timestamp  
- payload hashes  

---

## 11. Error Model

{
  "error": {
    "code": "INVALID_REQUEST",
    "message": "Human-readable message",
    "details": {},
    "correlation_id": "string"
  }
}

---

## 12. Versioning Policy

/api/bank/v1/...

- additive changes allowed
- breaking changes require version bump
- deprecated fields remain readable

---

## 13. Implementation Strategy

1. Protocol first (DTO + OpenAPI)
2. Security shell (mTLS + OAuth)
3. Transaction exposure
4. Reconciliation
5. Certification

---

## 14. Design Principles

- Security is default
- Async-first
- Idempotency mandatory
- Auditability built-in
- Reconciliation first-class
- Contracts before controllers

---

## 15. Final Note

This blueprint defines a financial protocol surface.

Goal:
Make APIs certifiable, trustworthy, and bank-integrable.
