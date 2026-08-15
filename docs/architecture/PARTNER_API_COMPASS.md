# Partner API Compass

## North Star

Approved external services can estimate, issue, inspect, and safely cancel Pay Codes without becoming X-Change operators or choosing another issuer Account.

## Implemented

- Legacy lifecycle API disabled by default and limited to local/testing even when explicitly enabled.
- OAuth 2.0 Client Credentials through Laravel Passport.
- One active Partner client mapped to one issuer Account.
- Short-lived bearer tokens, explicit scopes, throttling, and suspension/revocation enforcement.
- Server-owned issuer identity; caller-supplied issuer fields prohibited.
- Server-side mandate for currencies, rails, per-issuance limits, daily principal policy field, and unbound Pay Codes.
- Governed estimate and issuance through existing production actions.
- Mandatory issuance idempotency and optional correlation.
- Owner-scoped sanitized status and Treasury-safe cancellation.
- OAuth Authorization Server Metadata and Protected Resource Metadata.
- Sanitized Partner discovery, `llms.txt`, curated OpenAPI, and Postman collection.
- Conditional commissioning/doctor gate for OAuth signing keys.
- One-time operator credential provisioning command.
- HTTP-native lifecycle acceptance runner with a read-only default and explicit mutation confirmation.
- Client-and-operation-scoped issuance and cancellation idempotency, with an advertised Partner contract version and hash.

## Gates before Saras production credentials

- Add auditable client lifecycle operations: rotate secret, suspend, reactivate, revoke, and list sanitized clients.
- Standardize every Partner API error—including OAuth errors—into documented machine-actionable categories without weakening RFC compliance.
- Add delivery-status projection after its sanitized owner-scoped read model is finalized.
- Add webhook delivery for issuer-owned settlement events with signing, replay protection, retry semantics, and journal evidence.
- Run Cloud acceptance using a sandbox client and record the tested package/version manifest.
- Complete an independent security review and abuse/rate-limit exercise.

## Active AI/MCP wave

`3neti/x-mcp`, under the `LBHurtado\\XMcp` namespace, is the separate HTTP-only adapter over this Partner API. It does not depend on x-change internals. Its initial server exposes `inspect_capabilities`, `estimate_pay_code`, `issue_pay_code`, `get_pay_code`, and `cancel_pay_code`. Mutation tools require explicit confirmation and stable idempotency keys in their schemas.

MCP is a client/interface layer, never an alternate authority boundary.

## Public discovery policy

Public documentation is intentionally safe for prospective integrators to discover. It describes capabilities, authentication, schemas, and the access-request contact. It does not expose credentials, internal operator routes, Treasury details, raw voucher instructions, claim evidence, customer lookup, or legacy lifecycle operations. There is no self-service client registration.
