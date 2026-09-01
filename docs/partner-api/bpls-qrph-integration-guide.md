# BPLS QR Ph Partner API Integration Guide

This guide describes how a BPLS application integrates with X-Change to issue payable Pay Codes and present QR Ph payment instructions.

The integration boundary is the X-Change Partner API. BPLS should not manage X-Change wallet IDs, collection wallet IDs, NetBank credentials, or QR provider credentials directly. BPLS sends the payable voucher intent; X-Change resolves the authenticated merchant, collection wallet, QR Ph provider configuration, reconciliation path, and ledger posting.

## Intended flow

1. BPLS obtains a short-lived OAuth bearer token with the Client Credentials grant.
2. BPLS issues a payable Pay Code through the Partner API.
3. X-Change returns the Pay Code and payment links for that authenticated merchant.
4. BPLS opens or displays `/x/pay/{code}` for the payer.
5. X-Change creates or reuses the provider payment instruction.
6. The payer pays using a QR Ph-compatible wallet or bank app.
7. X-Change reconciles the confirmed provider payment.
8. X-Change credits the merchant ledger and updates the Pay Code status.

The public payer route is:

```text
https://{x-change-host}/x/pay/{CODE}
```

Payable Pay Codes should not send the payer to `/x/claim/{CODE}`.

## Required Partner API scopes

BPLS credentials need these scopes:

```text
pay-codes:estimate
pay-codes:issue
pay-codes:read
pay-codes:pay
```

`pay-codes:pay` is required when BPLS creates payment instructions through the Partner API endpoint:

```text
POST /api/partner/v1/pay-codes/{code}/payment-attempts
```

If BPLS only opens the browser payment page, `/x/pay/{code}` can create the payment instruction through the web flow, but the same credential should still include `pay-codes:pay` so automated integration tests can exercise the full server-to-server path.

## Credential provisioning

Sandbox Partner API credentials are created by an authorized X-Change operator:

```bash
php artisan x-change:partner-api:client "BPLS QR Ph Sandbox" \
  --issuer=merchant@example.test \
  --issuer-column=email \
  --environment=sandbox \
  --scope=pay-codes:estimate \
  --scope=pay-codes:issue \
  --scope=pay-codes:read \
  --scope=pay-codes:pay \
  --currency=PHP \
  --rail=INSTAPAY \
  --maximum-amount-minor=200000 \
  --daily-principal-minor=500000 \
  --json
```

The command displays the client secret once. Store it immediately in the receiving application's secret manager. Do not commit it to source control, paste it into issue trackers, or persist it in package documentation.

The example limit above allows a single payable Pay Code up to PHP 2,000.00 and a daily aggregate principal limit of PHP 5,000.00.

Production credentials must go through the governed Partner API production mandate flow. Do not create production credentials with the sandbox command.

## Environment variables for BPLS

BPLS should store the assigned credentials as deployment secrets:

```env
XCHANGE_API_BASE_URL=https://x-change-testing-testing-uw1gvj.laravel.cloud
XCHANGE_TOKEN_ENDPOINT=https://x-change-testing-testing-uw1gvj.laravel.cloud/oauth/token
XCHANGE_CLIENT_ID=issued-client-id
XCHANGE_CLIENT_SECRET=issued-client-secret
```

For the Amelia/BPLS sandbox, the active cloud host is:

```text
https://x-change-testing-testing-uw1gvj.laravel.cloud
```

The live client secret is intentionally not documented here. Use the secured credential handoff or rotate the client if the secret is lost or exposed.

## Obtain a bearer token

```bash
curl -sS -X POST "$XCHANGE_TOKEN_ENDPOINT" \
  -H "Accept: application/json" \
  -d "grant_type=client_credentials" \
  -d "client_id=$XCHANGE_CLIENT_ID" \
  -d "client_secret=$XCHANGE_CLIENT_SECRET" \
  -d "scope=pay-codes:estimate pay-codes:issue pay-codes:read pay-codes:pay"
```

Expected response shape:

```json
{
  "token_type": "Bearer",
  "expires_in": 900,
  "access_token": "..."
}
```

Cache the token only until `expires_in`. Never place bearer tokens in URLs or browser-visible logs.

## Issue a payable Pay Code

Use a stable idempotency key per BPLS payment intent.

```bash
curl -sS -X POST "$XCHANGE_API_BASE_URL/api/partner/v1/pay-codes" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $XCHANGE_ACCESS_TOKEN" \
  -H "Idempotency-Key: bpls-20260901-000001" \
  -H "X-Correlation-ID: bpls-20260901-000001" \
  --data '{
    "voucher_type": "payable",
    "external_reference": "BPLS-20260901-000001",
    "target_amount": 1220.00,
    "cash": {
      "amount": 0,
      "currency": "PHP",
      "settlement_rail": "INSTAPAY",
      "validation": {
        "payable": "bpls"
      }
    },
    "inputs": {
      "fields": []
    },
    "feedback": {
      "email": null,
      "mobile": null,
      "webhook": null
    },
    "rider": {
      "message": "BPLS payment",
      "url": null,
      "splash": null
    },
    "metadata": {
      "source": "bpls",
      "purpose": "Business permit payment"
    }
  }'
```

Notes:

- `external_reference` must be unique per BPLS transaction.
- `target_amount` is the amount to collect from the payer.
- For payable Pay Codes, `cash.amount` may be zero because the Pay Code represents a collection target rather than a pre-funded disbursement.
- BPLS should not send `metadata.collection_wallet_id`; X-Change resolves the merchant collection wallet from the authenticated Partner API client.
- The authenticated client determines the merchant/issuer. Do not send `issuer_id`, issuer email, or issuer mobile.

The response should include the Pay Code, consumer status, and payment links. The browser payment URL should resolve to:

```text
https://{x-change-host}/x/pay/{CODE}
```

## Create QR Ph payment instructions through the API

To create or replay provider payment instructions server-to-server:

```bash
curl -sS -X POST "$XCHANGE_API_BASE_URL/api/partner/v1/pay-codes/$PAY_CODE/payment-attempts" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $XCHANGE_ACCESS_TOKEN" \
  -H "Idempotency-Key: bpls-payment-attempt-20260901-000001" \
  -H "X-Correlation-ID: bpls-20260901-000001" \
  --data '{
    "provider": "netbank"
  }'
```

The response includes a payment attempt and, when available, QR Ph payload information:

```json
{
  "data": {
    "schema": "x-change.partner-payment-attempt.v1",
    "code": "ABCD",
    "consumer_status": "payable",
    "pay_url": "https://x-change-testing-testing-uw1gvj.laravel.cloud/x/pay/ABCD",
    "attempt": {
      "provider": "netbank",
      "qr_code": {
        "mime_type": "image/png",
        "base64_payload": "...",
        "embedded_amount": true
      }
    }
  }
}
```

If BPLS does not need to handle the QR payload directly, it can simply redirect or show the payer:

```text
https://{x-change-host}/x/pay/{CODE}
```

## Read Pay Code status

```bash
curl -sS "$XCHANGE_API_BASE_URL/api/partner/v1/pay-codes/$PAY_CODE" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $XCHANGE_ACCESS_TOKEN"
```

Expected status progression is generally:

```text
payable → processing → paid
```

Depending on provider timing, the Pay Code may remain payable while awaiting confirmed settlement. If a payment is visible at NetBank but not yet reflected in X-Change, inspect the verified settlement recovery worker and payment reconciliation logs before manually crediting funds.

## Test checklist

For the canonical BPLS payable test:

1. Issue a payable Pay Code for PHP 1,220.00.
2. Confirm the response points to `/x/pay/{code}`, not `/x/claim/{code}`.
3. Open the payer URL.
4. Confirm the QR Ph QR code renders.
5. Pay the QR using a compatible wallet or bank app.
6. Confirm the provider payment appears in NetBank.
7. Confirm X-Change recognizes the payment.
8. Confirm the merchant's Client Funds / ledger increases by the settled amount.
9. Confirm the Partner API read endpoint reports the final paid state.

## Troubleshooting

| Symptom | Likely cause | What to check |
| --- | --- | --- |
| OAuth token request fails | Wrong client ID, secret, scope, or disabled Partner API | Verify secret manager values, requested scopes, and `XCHANGE_PARTNER_API_ENABLED` |
| Issuance says amount exceeds mandate | Per-Pay-Code or daily mandate limit is too low | Check `maximum_amount_minor` and `daily_principal_limit_minor` on the Partner API client |
| Issuance asks for `metadata.collection_wallet_id` | Integration is bypassing the Partner API authority path or collection wallet resolution is incomplete | Confirm request uses bearer token and payable voucher type; escalate as an architecture mismatch |
| Response gives `/x/claim/{code}` | Payable link generation is wrong or caller is treating payable as redeemable | Use `/x/pay/{code}` and report the contract mismatch |
| `/x/pay/{code}` returns 404 | Payment web routes are not deployed or the Pay Code is not payable/owned by the expected issuer | Check route registration, deployment version, and Partner API read result |
| QR Ph does not render | Provider payment instruction creation failed | Check `/payment-attempts`, NetBank QR configuration, provider logs, and public payment page errors |
| NetBank shows payment but X-Change funds do not update | Reconciliation worker did not process or could not match the payment | Run the verified settlement recovery checks and inspect journal/outbox entries |

## Security rules

- Never persist client secrets in documentation.
- Never send bearer tokens in URLs.
- Use idempotency keys for all issuance and payment-attempt mutations.
- Rotate credentials immediately if the secret is exposed.
- Keep production access behind the governed production mandate flow.
