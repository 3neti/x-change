# Public AI Issuance Plan

## Objective

Make commissioned On-Demand Issuance discoverable by crawlers and AI clients
without creating another pricing, funding, Treasury, or Pay Code lifecycle.

The requester and AI client are not the issuer. The commissioned Commercial
Principal remains the issuer of record, x-change remains the execution
authority, and provider evidence remains the payment authority.

## Architecture

```text
Search or unintegrated AI -> /x/auto-generate
Read-only AI integration -> /mcp/x-change/public -> public issuance API
Institutional integration -> /mcp/x-change -> Partner API
```

x-change owns channel-neutral discovery, estimation, and handoff contracts.
x-mcp translates those contracts into MCP tools. Neither public discovery nor
the read-only MCP surface may invoke `GeneratePayCode`, create a funding order,
place a hold, or call a provider.

## Gate 1 — Channel-neutral read contracts

- Add typed public issuance discovery, estimate, and handoff DTOs.
- Add application services that use the existing pricing and public-instruction
  policies.
- Expose versioned, throttled, read-only JSON endpoints.
- Keep the existing browser controller and mutation lifecycle unchanged.

## Gate 2 — Universal web discovery

- Treat `/x/auto-generate` as the canonical service page.
- Support safe amount and currency prefilling.
- Publish canonical metadata and structured service data.
- Publish `/.well-known/x-change-service` and
  `/.well-known/x-change-public-mcp`.
- Keep the institutional `/.well-known/x-change-mcp` contract unchanged.

## Gate 3 — Read-only public MCP

- Register a separate `/mcp/x-change/public` server in `3neti/x-mcp`.
- Expose `discover_on_demand_issuance`,
  `estimate_on_demand_pay_code`, and `prepare_on_demand_handoff`.
- Mark every tool read-only and idempotent.
- Use the public x-change API as the sole transport authority.

## Deferred mutation gates

Native order creation, status access, cancellation, and issued-Pay-Code access
remain blocked until an ephemeral OAuth 2.1 guest grant is designed and
accepted. The grant must not provision a User, Account, wallet, Client Funds,
or Cockpit access. Possession tokens must never appear in model-visible tool
results.

## Release gate

1. [x] Test x-change contracts and public HTTP boundaries.
2. [x] Test x-mcp tools and confirm the Partner MCP remains unchanged.
3. [x] Publish x-change before x-mcp after explicit authorization.
4. [x] Upgrade the sandbox and verify browser plus MCP discovery after package
   publication.
5. [x] Deploy testing only after separate authorization.

Testing acceptance used `3neti/x-change v1.0.95`, `3neti/x-mcp v0.2.0`, and
the public browser handoff for PHP 25.00. Strict doctor passed 37 checks with
zero failures. No order or financial mutation was created.

The browser/API/MCP parameter contract is documented in
[Public AI Issuance Integration Guide](./PUBLIC_AI_ISSUANCE_INTEGRATION_GUIDE.md).

## Stop conditions

Stop rather than improvise if the work requires a second pricing engine,
direct AI access to `GeneratePayCode`, browser-controlled principal selection,
model-visible possession credentials, or a mutation without a separately
accepted authorization boundary.
