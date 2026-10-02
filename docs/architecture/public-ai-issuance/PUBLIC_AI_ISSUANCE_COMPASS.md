# Public AI Issuance Compass

Last updated: 2026-10-02

## North Star

Every AI client can discover and estimate x-change On-Demand Issuance, while
the public web flow remains the universal fallback. Native financial mutation
is introduced only behind a separately accepted guest-authorization boundary.

## Current position

Status: **Gates 1–3 are release-reviewed locally. Native mutations remain out of scope.**

The shipped foundations already include the public Auto-Generate browser
surface, authoritative pricing, full-amount funding, provider verification,
order-bound Treasury holds, exactly-once issuance, recovery links, and signed
receipts.

## Settled decisions

1. Public AI issuance is a commissioned Commercial Principal service.
2. `/x/auto-generate` is the canonical public service page.
3. `/mcp/x-change` remains the institutional Partner MCP.
4. `/mcp/x-change/public` is a distinct public service.
5. Anonymous tools are read-only: discover, estimate, and prepare handoff.
6. Public MCP tools call x-change through a versioned public API transport.
7. There is no public MCP tool that directly issues a Pay Code.
8. Pay Code funding remains disabled.
9. Polling precedes MCP events.
10. Guest-authorized mutations are a later controlled gate.

## Immediate acceptance

- Browser and MCP estimates match the authoritative x-change pricing engine.
- Discovery and handoff create no orders, holds, Pay Codes, or provider calls.
- Public payloads contain no principal identifier, Treasury details, secrets,
  possession tokens, or claim evidence.
- The institutional Partner MCP remains unchanged.

## Evidence

- x-change exposes throttled discovery, estimate, and handoff endpoints.
- `/x/auto-generate` accepts only bounded server-validated amount/currency
  prefill and publishes canonical service metadata.
- x-mcp exposes a distinct anonymous read-only server with three tools.
- Public MCP tools explicitly declare read-only, non-destructive, idempotent,
  and bounded-world annotations.
- x-change focused backend contracts pass: 28 tests, 127 assertions.
- x-change frontend passes: 168 files, 1,168 tests.
- x-mcp passes: 20 tests, 69 assertions, including the institutional Partner
  MCP regression boundary.
- Strict Composer validation passes for both packages.
- No implementation path calls issuance, creates a funding order, calls a
  provider, or returns a possession token.

## Next gate

Prepare the reviewed local commits for proposed releases `3neti/x-change
v1.0.95` and `3neti/x-mcp v0.2.0`. Publication, tagging, host adoption, browser
verification, deployment, order creation, provider access, and money movement
remain separately authorized gates.

## Companion document

- Plan: `PUBLIC_AI_ISSUANCE_PLAN.md`
