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

Issuance requires `Idempotency-Key`; `X-Correlation-ID` is recommended. Repeating the same key and payload returns the original result. Reusing the key with changed input is rejected.

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
