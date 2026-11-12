# X-Change Partner API

The Partner API is the supported server-to-server boundary for Saras AI and other approved integrators. It is deliberately smaller than X-Change's internal lifecycle surface.

## Security model

X-Change uses OAuth 2.0 Client Credentials through Laravel Passport. An operator creates a client and binds it to exactly one issuer Account. The client receives a short-lived bearer token programmatically from `/oauth/token`; no human login or refresh token is involved.

A valid token is necessary but not sufficient. Every request is also constrained by:

1. the OAuth scope;
2. the active Partner API client record;
3. the token-bound issuer Account;
4. its server-side mandate for currencies, rails, amount ceilings, recipient binding, and aggregate limits;
5. the same pricing, Account-funds, Treasury, and issuance actions used by Cockpit;
6. mandatory idempotency for issuance.

The caller cannot submit `issuer_id`, issuer email, or issuer mobile. X-Change derives the issuer from the authenticated client. Pay Codes belonging to another Account return the same not-found response as an unknown code.

Shared-principal customer-funded orders have separate `funded-orders:create` and `funded-orders:read` scopes. The commissioned Commercial Principal is their issuer. Account-backed funded orders instead bind issuance to the Partner client's issuer Account and require both creation and issuance scopes.

## Activation

Public discovery is safe to expose before financial operations are enabled:

```env
XCHANGE_PARTNER_API_PUBLIC_DISCOVERY_ENABLED=true
XCHANGE_PARTNER_API_ENABLED=false
XCHANGE_PARTNER_API_ACCESS_CONTACT=api-access@example.test
```

The public surfaces are:

- `/.well-known/x-change-partner-api`
- `/.well-known/oauth-authorization-server`
- `/.well-known/oauth-protected-resource`
- `/api/partner/openapi.json`
- `/llms.txt`

Before setting `XCHANGE_PARTNER_API_ENABLED=true`, provide persistent Passport signing keys through `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`, or retain readable `storage/oauth-private.key` and `storage/oauth-public.key`. Then run:

```bash
php artisan optimize:clear
php artisan x-change:doctor --pre-install --strict
php artisan migrate --force
```

The doctor fails closed if Partner operations are enabled without OAuth signing keys.

New customer-funded orders also require `XCHANGE_PARTNER_FUNDED_ORDERS_ENABLED=true`. Its default is false. Existing client-owned orders remain readable when new placement is paused. The default unpaid-order limit is three per client, configurable with `XCHANGE_PARTNER_FUNDED_ORDERS_MAXIMUM_OPEN`.

## Customer-funded orders

An approved client with `funded-orders:create` posts one redeemable Pay Code instruction set to `/api/partner/v1/funded-orders`, adding `funding_method` (`qr_ph` or `bank_transfer`) and an `Idempotency-Key` header. Bank transfer also requires `payer_mobile` before instructions are returned. The response contains the order reference, exact amount, expiry, and selected payment instructions. The payer makes one exact payment; the scheduled funding verifier and issuance pipeline handle the rest. The client polls `/api/partner/v1/funded-orders/{reference}` with `funded-orders:read` until `pay_code.claim_url` is present or the order needs review. A paid order needing review must not be paid again.

The `phase` field summarizes progress as `payment_pending`, `payment_verified`, `funded_from_client_funds`, `issuance_pending`, `issued`, `review_required`, `cancelled`, or `expired`. `status` retains the detailed underlying order state. An issued phase requires a linked Pay Code; review-required and expired orders should be referred for recovery rather than paid again.

## Account-backed funding orders

`POST /api/partner/v1/account-funded-orders` is disabled by default. It requires `funded-orders:create` and `pay-codes:issue`, an `Idempotency-Key`, an `external_reference`, a voucher instruction, and `funding_method` (`qr_ph` or `bank_transfer`; the latter also needs `payer_mobile`). The authenticated client identifies the issuer Account. The order reserves existing Client Funds and requests the shortfall, rather than issuing under the public Commercial Principal. Pending reservations count toward `XCHANGE_PARTNER_FUNDED_ORDERS_MAXIMUM_OPEN`, including cases requiring review; matching replays remain available at the limit.

An expired or cancelled order retains its external reference and principal reservation while late payment and recovery are possible. Do not reuse its reference or pay it again; contact the operator for reconciliation. After the reconciliation window, an authorized operator may release the principal reservation using the recovery endpoint, with a reason and a provider evidence reference. This requires a recent successful no-payment check and no conflicting evidence. The original reference remains permanently reserved, and any later payment requires review. If the required check is unavailable or stale, the reservation stays in place.

Operators can use the Recovery workspace, or list candidates at `GET /x/operations/recovery/partner-reservations`, recheck provider evidence with `POST /x/operations/recovery/partner-reservations/{id}/verify`, and submit a reviewed decision to `POST /x/operations/recovery/partner-reservations/{id}/release` with `reason` and `evidence_reference`. These are authenticated browser-session routes protected by the host's `x-change.recovery.manage` gate and CSRF, not Partner API routes. The evidence reference is an operator's attestation; the system does not independently validate its contents. The no-payment check must have occurred after expiry and within the preceding 24 hours. An operator may recheck an older order; evidence discovered outside the automatic window goes to review rather than automatic issuance.

Use `GET /api/partner/v1/funded-orders/{reference}` with `funded-orders:read` to poll its status. A 201 response creates an order; a matching replay returns 200. A changed request with the same key returns 409. Never pay an order again while verification is unresolved; `review_required` requires operator review. A payment to fund issuance is separate from a later customer's payment of a payable voucher.

The published OpenAPI artifact is generated from live Partner routes and the curated prefunded contract. In the package development environment, run `XCHANGE_PARTNER_API_ENABLED=true php vendor/bin/testbench x-change:partner-api:export-openapi`; add `--check` in CI to detect drift. Scramble is a development dependency, so production serves the generated artifact without running the generator.

## Provision a client

Provision credentials as an authorized deployment/operator action. This is not a public self-registration endpoint.

```bash
php artisan x-change:partner-api:client "Saras AI Sandbox" \
  --issuer=issuer@example.test \
  --issuer-column=email \
  --environment=sandbox \
  --scope=capabilities:read \
  --scope=pay-codes:estimate \
  --scope=pay-codes:issue \
  --scope=pay-codes:read \
  --scope=pay-codes:cancel \
  --currency=PHP \
  --rail=INSTAPAY \
  --maximum-amount-minor=500000 \
  --daily-principal-minor=2000000 \
  --json
```

The secret is displayed once. Put it in Saras's secret manager, never source control. Production credentials additionally require `--environment=production --confirm-production`.

## Obtain a bearer token

```bash
curl -X POST https://your-host.example/oauth/token \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=client_credentials' \
  --data-urlencode 'client_id=YOUR_CLIENT_ID' \
  --data-urlencode 'client_secret=YOUR_CLIENT_SECRET' \
  --data-urlencode 'scope=capabilities:read pay-codes:estimate pay-codes:issue pay-codes:read pay-codes:cancel'
```

Cache the returned token only until `expires_in`; request a new token after expiry. Never put bearer tokens in URLs or logs.

## Supported operations

| Operation | Scope | Mutation |
|---|---|---:|
| `GET /api/partner/v1/capabilities` | `capabilities:read` | No |
| `POST /api/partner/v1/pay-code-estimates` | `pay-codes:estimate` | No |
| `POST /api/partner/v1/pay-codes` | `pay-codes:issue` | Yes |
| `GET /api/partner/v1/pay-codes/{code}` | `pay-codes:read` | No |
| `POST /api/partner/v1/pay-codes/{code}/cancellation` | `pay-codes:cancel` | Yes |
| `GET /api/partner/v1/pay-codes/by-reference/{externalReference}` | `pay-codes:read` | No |
| `POST /api/partner/v1/account-funded-orders` | `funded-orders:create` **and** `pay-codes:issue` | Yes |
| `POST /api/partner/v1/funded-orders` | `funded-orders:create` | Yes |
| `GET /api/partner/v1/funded-orders/{reference}` | `funded-orders:read` | No |

All Partner calls use `Authorization: Bearer <access token>` and `Accept: application/json`. JSON requests use `Content-Type: application/json`. Issuance and both funded-order creation routes require an `Idempotency-Key`; direct issuance and account-funded orders also require an `external_reference`. The reference is unique within the client. An account-funded order supports one Pay Code per order.

### Three account-backed issuance journeys

Each journey begins by obtaining a client-credentials token with the required scopes and confirming the commissioned Account, mandate, supported rail and pricing. The following bodies are illustrative: use real recipient and destination information only in an authorized environment. Amounts are PHP major units.

```json
{
  "external_reference": "invoice-001",
  "cash": {"amount": 50, "currency": "PHP", "settlement_rail": "INSTAPAY", "validation": {"mobile": "09171234567"}},
  "inputs": {"fields": []},
  "feedback": {"mobile": null},
  "rider": {"message": null},
  "count": 1
}
```

This is a **disbursable** instruction: `cash.amount` is the value for the recipient to claim. For a **payable** instruction, use `"voucher_type": "payable"`, set `cash.amount` to `0`, and add `"target_amount": 100`. For a **collect-for-settlement** instruction, use `"voucher_type": "settlement"`, `cash.amount: 0`, `"target_amount": 100`, and `"execution": {"schema": "voucher.execution.v2", "driver": "default", "metadata": {"settlement_workflow": "collect_for_settlement"}}`. Settlement workflows which disburse first have a positive principal and their own collection terms; consult the instruction contract and commissioned configuration.

For each type:

1. `POST /pay-code-estimates` with the instruction and `pay-codes:estimate` returns a `data` pricing estimate. This validates the instruction; there is no separate Partner instruction-validation endpoint.
2. If the issuer has sufficient Client Funds, `POST /pay-codes` with `pay-codes:issue`, the instruction, `external_reference`, and `Idempotency-Key`. A successful creation returns `201` with `data.code` and links; an identical replay returns `200` and the original result.
3. If additional funds are needed, `POST /account-funded-orders` with the same instruction, a new stable `Idempotency-Key`, and `"funding_method": "qr_ph"` (or `"bank_transfer"` with `payer_mobile`). Creation returns `201` with `data.reference`, `data.phase`, `data.amount_minor`, `data.currency`, and available `data.funding` instructions. An identical replay returns `200`.
4. Fund the **issuance order** once using those instructions. Poll `GET /funded-orders/{reference}` with `funded-orders:read`. Wait through `payment_pending`, `payment_verified`, and `issuance_pending`; `issued` includes `data.pay_code.code` and its claim/payment links. `review_required`, `expired`, and `cancelled` require investigation: do not pay again.
5. Verify the issued record with `GET /pay-codes/{code}` or `GET /pay-codes/by-reference/{externalReference}`, using `pay-codes:read`. These resources expose supported status and links, not raw provider receipts or a transaction ledger.

The payment that funds **issuance** is separate from a later customer's payment of a **payable collection voucher**. Public `/x/auto-generate` screens and their session/CSRF-protected browser routes are not standalone Partner APIs. The shared-principal `/funded-orders` route retains its restriction on payable and settlement instructions; use the Account-backed route for those types.

`401` indicates invalid or inactive credentials, `403` missing authority, `404` an unavailable or non-owned resource, `409` a reference or idempotency conflict, `422` invalid instructions or mandate failure, and `429` throttling. A transport timeout does not establish failure: retry the same request with the same key and body, then poll the returned order or look up its reference. Provider-unavailable or review-required outcomes need reconciliation; do not create a new order just because a response is delayed.

Issuance requires `Idempotency-Key`; `X-Correlation-ID` is recommended. Repeating the same key and payload returns the original result. Reusing the key with changed input is rejected.

New issuance records retain a fingerprint of the complete request. A later request with the same external reference and a different idempotency key is replayed only when its instructions match. Older records without that fingerprint cannot be verified this way and may reject issuance retries after the upgrade. Look up the existing Pay Code by external reference rather than retrying issuance with a new key.

Cancellation also requires `Idempotency-Key`. Its replay identity is scoped to the authenticated Partner client and cancellation operation, so retrying an identical request cannot release principal twice. Partner idempotency storage is scoped by client and operation; identical raw keys used by different approved clients cannot collide or reveal another client's result.

Money in Partner status resources is expressed as integer `amount_minor` plus ISO currency. Instruction inputs continue to use human major units at this contract boundary, as declared by OpenAPI.

## Financial safety

Saras cannot create money or bypass X-Change by calling the API:

- its issuer Account is fixed by the OAuth client;
- issuance calls the production `EstimatePayCodeCost` and `GeneratePayCode` actions;
- insufficient Account funds or Treasury capacity rejects issuance;
- mandate ceilings can be stricter than the Account balance;
- another issuer's Pay Code is inaccessible;
- cancellation uses the existing transaction-locked Treasury terminal release;
- provider calls are not made during Pay Code issuance or cancellation;
- commercial issuance charges are not refunded by cancellation.

The old `/api/x/v1` lifecycle scaffold is disabled by default, restricted to local/testing when explicitly enabled, and is never a Partner API alternative.

## Acceptance runner

The default scenario obtains a real token and calls capabilities over HTTP without a financial mutation:

```bash
php artisan x-change:partner-api:run \
  --base-url=https://your-host.example \
  --client-id=YOUR_CLIENT_ID \
  --json
```

Omit `--client-secret` for a hidden prompt. The financial scenario estimates, issues, reads, then cancels an unclaimed Pay Code. It still retains commercial charges and therefore requires explicit authority:

```bash
php artisan x-change:partner-api:run \
  --base-url=https://your-host.example \
  --client-id=YOUR_CLIENT_ID \
  --scenario=issue-and-cancel \
  --amount=1.00 \
  --mobile=09171234567 \
  --confirm-financial-mutation \
  --json
```

The report schema is `x-change.partner-api-lifecycle-run.v1` and declares that transport was HTTP and no direct Action calls occurred.

## Tooling

- OpenAPI: `resources/api/x-change-partner-api.openapi.json`
- Postman: `resources/api/x-change-partner-api.postman_collection.json`
- BPLS QR Ph guide: `docs/partner-api/bpls-qrph-integration-guide.md`

The Postman collection contains placeholders only. Store real credentials in a private environment.

## Not exposed

The Partner API intentionally excludes wallet ledgers, raw voucher instructions, claim evidence, settlement envelopes, reconciliation controls, users/KYC lookup, event payloads, webhooks, dashboards, scenario administration, and operator/Treasury mutations. These remain internal, recipient-facing, or provider-facing surfaces.
