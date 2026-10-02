# Public AI Issuance Integration Guide

## Purpose

This guide is the contract for AI clients, crawlers, and application developers
that want to discover or prefill x-change On-Demand Pay Code Issuance.

The public integration is deliberately split into two stages:

1. Read-only discovery, estimation, and browser handoff.
2. Human review and authorization inside x-change.

The first stage never creates a funding order, requests payment, places a
Treasury hold, calls a provider, or issues a Pay Code.

## Authority model

| Actor | Authority |
| --- | --- |
| Requester | Chooses the proposed amount and reviews the resulting instruction |
| AI client | Discovers, estimates, and prepares a browser handoff |
| Commissioned Commercial Principal | Issuer of record |
| x-change | Pricing, funding, Treasury, and issuance authority |
| Provider evidence | Payment-settlement authority |

An AI client must not claim that it issued a Pay Code or confirmed payment.

## Public endpoints

| Surface | Endpoint | Effect |
| --- | --- | --- |
| Browser | `GET /x/auto-generate` | Opens the public issuance editor |
| Service discovery | `GET /.well-known/x-change-service` | Returns availability, limits, pricing links, and funding capabilities |
| Public MCP discovery | `GET /.well-known/x-change-public-mcp` | Returns the public MCP endpoint and tool catalog |
| Public issuance API | `GET /api/x/v1/public-issuance` | Returns the channel-neutral discovery contract |
| Estimate | `POST /api/x/v1/public-issuance/estimate` | Returns the authoritative price without creating an order |
| Handoff | `POST /api/x/v1/public-issuance/handoff` | Returns a safe prefilled browser URL without creating an order |
| Public MCP | `/mcp/x-change/public` | Exposes the same read-only contracts as MCP tools |

## Browser query parameters

The public browser URL currently accepts exactly two prefill parameters:

| Query parameter | Format | Example | Meaning |
| --- | --- | --- | --- |
| `amount` | Major currency units, one to seven whole digits, with zero to two decimal places | `25`, `25.5`, `25.50` | Proposed Pay Code principal |
| `currency` | Optional enabled ISO currency code; defaults to `PHP` | `PHP` | Proposed principal currency |

Example:

```text
https://host.example/x/auto-generate?amount=25.00&currency=PHP
```

The server normalizes the example to an amount of `25.00` and currency `PHP`.
The configured minimum and maximum remain authoritative. At the current
defaults, the principal must be between `100` and `100000` minor units, or
between PHP 1.00 and PHP 1,000.00.

Invalid, negative, unsupported, or out-of-range values do not prefill the
editor. A query string is only a proposal; it is not a Voucher instruction,
frozen order, payment request, or issuance authorization.

### Voucher-instruction parameters are not public URL parameters

The following examples are **not** accepted public query parameters:

| Desired instruction | Do not append |
| --- | --- |
| Recipient or Pay To | `pay_to`, `recipient`, `recipient_reference`, `mobile`, `email` |
| Purpose | `purpose`, `description`, `memo` |
| Rider message | `rider_message`, `message` |
| After-claim destination | `rider_url`, `redirect_url`, `callback_url` |
| Feedback destination | `feedback_mobile`, `feedback_email`, `feedback_webhook` |
| Claim inputs or evidence | `claim_requirements`, `fields`, `evidence`, `selfie`, `location`, `signature` |
| Verification | `verification`, `otp`, `kyc` |
| Expiration | `expires_at`, `expires_in`, `ttl` |
| Saved configuration | `template`, `template_id`, `campaign`, `campaign_id` |
| Financial authority | `commercial_principal`, `issuer`, `funding_method`, `paid` |

AI clients and developers must not invent aliases for these fields. Unknown
query parameters are outside the contract and must not be treated as accepted
Voucher instructions.

After opening the handoff, the person may add supported instructions in the
x-change editor. x-change then validates, prices, freezes, and presents the
complete instruction before payment authorization. The server—not the URL or
AI client—selects the commissioned Commercial Principal.

## API and MCP input parameters

The read-only estimate and handoff contracts use minor currency units:

```json
{
  "amount_minor": 2500,
  "currency": "PHP"
}
```

| Field | Type | Required | Meaning |
| --- | --- | --- | --- |
| `amount_minor` | integer | Yes | Proposed principal in minor units; `2500` means PHP 25.00 |
| `currency` | string | Yes | Enabled uppercase ISO currency code |

The MCP tools expose the same fields:

- `estimate_on_demand_pay_code`
- `prepare_on_demand_handoff`

`discover_on_demand_issuance` accepts no arguments.

The API/MCP field is `amount_minor`; the browser query parameter is `amount`.
Do not place `amount_minor=2500` in the browser URL and do not send
`amount=25.00` to the JSON estimate or handoff endpoint.

## Example AI interaction

1. Call `discover_on_demand_issuance`.
2. Confirm the service is available and the requested amount is within the
   returned limits.
3. Call `estimate_on_demand_pay_code` with `amount_minor` and `currency`.
4. Present the authoritative principal, fees, and total to the person.
5. Call `prepare_on_demand_handoff` with the same values.
6. Open or return the URL without adding unsupported query parameters.
7. Tell the person that x-change will require review and payment authorization
   before it creates an order or issues a Pay Code.

For PHP 25.00 in the accepted testing configuration, the handoff result is:

```text
/x/auto-generate?amount=25.00&currency=PHP
```

The authoritative estimate may include service and instruction fees. Clients
must display the returned total rather than calculate it independently.

## Safety requirements for AI clients

- Never select or accept a Commercial Principal from model input.
- Never describe a handoff URL as an issued Pay Code.
- Never infer payment from a person's acknowledgement or screenshot.
- Never add `paid`, provider-transaction, Treasury, or possession-token values
  to a URL or model-visible response.
- Never calculate fees independently of the authoritative estimate.
- Never retry a future mutation with a new idempotency key merely because a
  response was delayed.
- Keep the institutional `/mcp/x-change` Partner surface separate from the
  anonymous `/mcp/x-change/public` surface.

## Current version and acceptance evidence

This contract was accepted on the Laravel Cloud testing environment with:

- `3neti/x-change v1.0.95`;
- `3neti/x-mcp v0.2.0`;
- public MCP protocol `2025-06-18`;
- three anonymous read-only tools;
- strict doctor result `37 passed, 0 failed`; and
- a browser handoff visibly prefilling PHP 25.00 without creating an order.

Native order creation, funding selection, payment-status access, cancellation,
issued-Pay-Code retrieval, and MCP events remain deferred mutation gates.
