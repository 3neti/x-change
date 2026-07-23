# Standing Funding Address Protocol

## Decision

A long-lived QR Ph destination can fund an x-change Account, but only when that exact provider destination is registered with one immutable purpose.

```text
provider + exact destination address → one purpose + one Account
```

The QR image is a transport artifact. Scanning it does not credit an Account. A settled provider transaction, independently observed at the registered destination, is the authority.

This protocol removes Pipedream and prohibits routing by payer mobile, mobile prefix, amount, time window, merchant name, FIFO order, or an “unmatched means top-up” fallback.

## Vocabulary

- **Standing Funding Address** — a reusable provider destination with an immutable purpose.
- **Account Funding Address** — a Standing Funding Address whose purpose is `account_funding`.
- **Funding Intent** — an expiring, exact-amount funding instruction whose purpose is `funding_intent`.
- **Payment Address** — a destination whose purpose is `payment`; its observations belong to a payment workflow and never become Account funding by default.
- **Provider Funding Observation** — immutable, normalized evidence read from the provider.
- **Account Funding Receipt** — x-change’s classified lifecycle record for one provider transaction at an Account Funding Address.
- **Recognition mode** — the policy controlling what happens after authoritative settled evidence passes every guard.

“Self top-up” is not used as a settlement classification. The payer and Account owner may differ. The destination binding—not payer mobile—determines the Account.

## Normalized Purpose Payloads

These payloads are x-change’s normalized protocol examples. They are not assumptions about a bank’s native webhook body.

### `account_funding`

Use for a long-lived address whose settled incoming transfers may become Account balance.

```json
{
  "schema": "x-change.standing-funding-address.v1",
  "provider": "netbank",
  "account_reference": "wallet:<account-uuid>",
  "purpose": "account_funding",
  "currency": "PHP",
  "recognition_mode": "observe_only",
  "reusable": true
}
```

The provider returns the address and QR. x-change encrypts the address, stores its SHA-256 classification hash, and binds it to the owner and Account.

### `funding_intent`

Use for one exact expected amount with an expiry.

```json
{
  "schema": "x-change.funding-intent.v1",
  "provider": "netbank",
  "account_reference": "wallet:<account-uuid>",
  "purpose": "funding_intent",
  "expected_amount_minor": 2500,
  "currency": "PHP",
  "expires_at": "2026-07-24T12:30:00+08:00",
  "one_time": true
}
```

The Funding Intent owns its deterministic VCA, exact-amount limit, dynamic QR, destination snapshot, verification lifecycle, and final settlement.

### `payment`

Use for a payment collection workflow whose beneficiary is not the funding Account.

```json
{
  "schema": "x-change.standing-funding-address.v1",
  "provider": "netbank",
  "account_reference": "merchant:<merchant-reference>",
  "purpose": "payment",
  "currency": "PHP",
  "recognition_mode": "observe_only",
  "reusable": true
}
```

A `payment` observation is classified and audited, then handed to the payment domain when that binding is implemented. It does not create an `AccountFundingReceipt`, recognize Account-funding Inventory, or credit an Account.

## Package Contract

`3neti/emi-core` owns:

- `FundingAddressPurpose`;
- `StandingFundingAddressRequestData`;
- `StandingFundingAddressData`;
- `StandingFundingObservationRequestData`;
- `StandingFundingAddressProvider`.

The contract can create a provider address and observe it. It cannot settle money or credit an Account.

`3neti/emi-netbank` implements the contract with:

- a configurable, purpose-separated 16-digit VCA;
- a provider-generated static/open-amount P2M QR;
- incoming VCA transaction-history queries;
- normalized observations with stable provider transaction identity;
- hashed destination and corporate-account references;
- no raw payer or account payload in x-change read models.

`3neti/x-change` owns:

- the encrypted Standing Funding Address registry;
- exact-destination classification;
- recognition modes and configurable limits;
- immutable Account Funding Receipts;
- suspense, Treasury Inventory recognition, and Account credit;
- webhook, operator, and schedule triggers;
- Cockpit authorization and safe presentation.

The host owns runtime configuration, migrations, queues, scheduling, and published assets only. It contains no funding rule or documentation.

## Address Binding

The binding key covers:

```text
owner type/id
+ Account reference
+ provider
+ purpose
+ currency
```

Reopening the same binding returns the same address. A provider returning a different address for an existing binding fails closed; rotation must be an explicit future operation.

Separate purposes produce separate deterministic provider destinations. One destination cannot serve as both `account_funding` and `payment`.

### NetBank 16-digit address profile

NetBank’s profile is:

```text
five-digit alias + eleven-digit reference = sixteen-digit VCA
91500            + 09171234567            = 9150009171234567
```

The reference is selected by one configured scheme:

| Scheme | Default | Derivation | Posture |
|---|---|---|---|
| `netbank-mobile-v1` | local, development, testing | verified Philippine mobile in `09XXXXXXXXX` form | easier to understand and reproduce, but correlatable and subject to mobile-recycling/multi-Account constraints |
| `netbank-account-hmac-v2` | production | decimal rejection-sampled HMAC over immutable Account reference, purpose, key ID, and collision counter | opaque, purpose-separated, and required in production |

Production rejects `netbank-mobile-v1`, even when it is selected explicitly. HMAC v2 requires a dedicated key and key ID; it never falls back to `APP_KEY`.

The exact address, derivation scheme, key ID, collision counter, and reference length are persisted when the binding is first created. The encrypted persisted address is authoritative thereafter:

- reopening does not recompute from the current mobile, scheme, or key;
- changing or rotating the key affects new bindings only;
- a changed mobile cannot redirect an existing QR;
- an HMAC collision retries with the next counter and persists the successful counter;
- a mobile-derived collision fails closed because silently changing the mobile suffix would violate the scheme;
- legacy non-16-digit addresses are not silently replaced and must be retired through an explicit migration/rotation operation.

Persisted sensitive fields:

- provider address: encrypted;
- destination snapshot: encrypted;
- provider routing credential: encrypted by the destination model;
- exact destination lookup: SHA-256 hash;
- public read model: status, purpose, mode, limits, timestamps, and masked references only.

## Classification

An observation is eligible for classification only when it contains a normalized hashed destination:

```text
sha256:<64 lowercase hexadecimal characters>
```

x-change looks up:

```text
provider_code + funding_address_hash
```

Outcomes:

- one active match: use its immutable purpose and Account binding;
- no match: `unknown_funding_address` suspense;
- more than one match: fail closed as a registry invariant violation.

Currency, amount, provider status, payer identity, webhook fields, and transaction time never repair a missing destination match.

## Recognition Modes

### `observe_only`

Safe default. Settled provider evidence creates or updates a verified Account Funding Receipt, but no Inventory or Account balance changes.

### `supervised`

Settled evidence reaches `awaiting_approval`. The authenticated address owner may approve the already-verified receipt. The approval route accepts no amount, address, provider transaction, or destination input.

### `automatic`

Settled evidence proceeds directly to recognition after every destination, status, currency, amount, and limit guard passes.

Changing the configured default affects new address bindings only. The persisted mode is the authority for an existing address.

## Receipt Lifecycle

```text
provider observation
        │
        ▼
     observed
        │ settled + exact destination/currency + limits
        ├──────── observe_only ────────→ observed (verified)
        ├──────── supervised ─────────→ awaiting_approval
        └──────── automatic ──────────→ ready
                                             │
                                             ▼
                                          settled

any mismatch / ambiguity / denied guard ───→ suspense
post-settlement status change ─────────────→ suspense review
```

A provider transaction key is derived from provider code and provider transaction ID. Pending and settled observations for the same provider transaction converge on one receipt. Provider replays cannot create another receipt.

## Settlement Transaction

The ready receipt is locked together with its Standing Funding Address and latest provider observation. x-change rechecks:

- address status is active;
- address and receipt purpose are `account_funding`;
- provider is identical;
- provider status is `settled`;
- exact hashed destination matches;
- provider marked destination verification true;
- currency matches;
- net amount is positive;
- minimum, maximum, and daily limits pass.

One database transaction then:

1. registers the provider’s Settlement Resource/Inventory if needed;
2. recognizes the verified net amount as Treasury Inventory;
3. credits the bound Account;
4. stores Inventory operation and wallet transaction references;
5. marks the receipt settled.

The receipt reference deterministically supplies Treasury and ledger idempotency. A failure rolls back both Inventory recognition and Account credit.

## Limits

Defaults are deliberately conservative and configurable in minor units:

```text
minimum per transfer: PHP 1.00
maximum per transfer: PHP 50,000.00
daily gross limit:    PHP 100,000.00
```

A limit breach enters suspense. Operators cannot override it by editing the receipt or submitting another amount.

## Trigger Convergence

```text
authenticated webhook hint ─┐
Check NetBank button ────────┼─→ unique Standing Address sync
package minute schedule ─────┘      │
                                    ▼
                          NetBank VCA history
```

- Webhook authentication permits intake and wakes bounded active addresses for that provider.
- **Check NetBank** submits only an acknowledgement. It supplies no transaction facts.
- `xchange:funding:sync-standing --provider=netbank --limit=100` queues active addresses.
- The package registers the command every minute with overlap protection.
- Jobs are unique per address, share an address lock, and use the provider verification rate limiter.

The host must run its queue worker and Laravel scheduler.

## NetBank Operational Flow

1. Open `/x/cockpit/funding`.
2. Choose **Create Account Funding QR**.
3. x-change resolves the operator’s current NetBank Funding Destination.
4. x-change derives a new 16-digit address under the configured scheme, or reopens the exact persisted address.
5. NetBank generates the static QR for the purpose-bound VCA.
6. A human scans it and enters the amount.
7. The payer authorizes the real QR Ph payment outside x-change.
8. Webhook, operator check, or schedule triggers NetBank history.
9. x-change records immutable observations and classifies the exact VCA.
10. The configured recognition mode observes, waits for approval, or settles.

The QR and full VCA are returned only from the private `no-store` endpoint. They are absent from the general Inertia read model.

## Reversals and Changed Provider Status

If a later provider observation changes a transaction that already produced a settled receipt, x-change opens `post_settlement_status_changed` suspense. It does not rewrite the receipt or silently debit the Account.

Existing Funding Intent recovery primitives remain the model for a future purpose-built Standing Address reversal action: reverse Treasury Inventory, recover available Account balance, freeze Issuance Capacity for any deficit, and retain immutable evidence. Until that action is implemented and approved, a Standing Address reversal is a blocked incident requiring controlled reconciliation.

## Paynamics and Future Providers

A Paynamics or future bank/EMI adapter implements `StandingFundingAddressProvider` and is registered under its own provider code.

It must:

- create or resolve a stable destination;
- preserve purpose separation;
- return a provider QR artifact when supported;
- query authoritative transaction history or ledger facts;
- normalize stable transaction ID, status, gross/fee/net amounts, currency, timestamps, and destination hash;
- omit secrets and raw payer data from read models;
- never credit an Account.

If a provider cannot prove an exact destination, its observation enters suspense. Reachability, wallet balance, payer mobile, or webhook delivery is insufficient.

## Configuration

```text
XCHANGE_STANDING_FUNDING_ADDRESSES_ENABLED
XCHANGE_STANDING_FUNDING_RECOGNITION_MODE
XCHANGE_STANDING_FUNDING_LOCK_SECONDS
XCHANGE_STANDING_FUNDING_LOCK_WAIT_SECONDS
XCHANGE_STANDING_FUNDING_SCHEDULED_SYNC_ENABLED
XCHANGE_STANDING_FUNDING_SCHEDULED_BATCH_SIZE
XCHANGE_STANDING_FUNDING_WEBHOOK_BATCH_SIZE
XCHANGE_STANDING_FUNDING_MINIMUM_AMOUNT_MINOR
XCHANGE_STANDING_FUNDING_MAXIMUM_AMOUNT_MINOR
XCHANGE_STANDING_FUNDING_DAILY_LIMIT_MINOR
```

NetBank also requires its funding API/token endpoints, OAuth credentials, corporate account, five-digit VCA alias, reference key, QR endpoint, and merchant fields. The Cockpit control fails closed unless every prerequisite is present, including:

```text
NETBANK_FUNDING_CORPORATE_ACCOUNT_NUMBER
NETBANK_FUNDING_CORPORATE_ACCOUNT_NAME
NETBANK_FUNDING_VCA_ALIAS
NETBANK_FUNDING_STANDING_ADDRESS_SCHEME
NETBANK_FUNDING_VCA_REFERENCE_LENGTH
NETBANK_FUNDING_STANDING_HMAC_KEY_ID
NETBANK_FUNDING_STANDING_HMAC_KEY
```

Recommended production configuration:

```dotenv
NETBANK_FUNDING_STANDING_ADDRESS_SCHEME=netbank-account-hmac-v2
NETBANK_FUNDING_VCA_REFERENCE_LENGTH=11
NETBANK_FUNDING_STANDING_HMAC_KEY_ID=v2-2026-01
NETBANK_FUNDING_STANDING_HMAC_KEY=base64:<dedicated-secret-of-at-least-32-bytes>
```

Keep the HMAC key stable and in managed secret storage. Back it up under the same recovery policy as provider credentials. Rotating it is safe for persisted addresses, but restoring a database without its matching address records and key history can orphan old QR destinations.

The provider-issued VCA alias token is deliberately **not** required for the shared reusable QR because this flow does not register or mutate a VCA. It remains mandatory for one-time Funding Intents and for dedicated destinations that use registered VCA operations. It must never be guessed, exposed in the UI, or recorded in documentation.

## Browser Acceptance

The package UI is accepted at desktop and mobile widths in two explicit states:

- **Ready:** the create/reopen control may call NetBank and return the private, `no-store` QR response.
- **Not configured:** the control is disabled before any provider call when a required credential or corporate-account fact is absent.
- **Mobile not verified:** a new `netbank-mobile-v1` address is blocked until the operator mobile is verified.
- **Legacy address requires retirement:** a persisted non-16-digit address remains untouched and cannot be reopened through the new profile.

Acceptance verifies no page-level horizontal overflow, contained activity tables, responsive controls, and no browser console errors. A real scan and payment remains a separately authorized live UAT gate.

### Configurable scheme wave acceptance — 2026-07-24

- `emi-core` standing request serialization and compatibility: 8 tests, 85 assertions;
- `emi-netbank` reusable/funding adapter derivation and failure paths: 41 tests, 178 assertions;
- `x-change` persistence, collision, destination, and Cockpit behavior: 21 tests, 149 assertions;
- complete x-change frontend suite: 81 files, 581 tests;
- package asset diagnostics and production build passed;
- the sandbox migration persisted the derivation scheme, key ID, counter, and reference length;
- desktop at 1440×1000 and mobile at 390×844 had no document-level horizontal overflow;
- Cockpit displayed the development scheme warning and produced a provider-generated static QR for a 16-digit `netbank-mobile-v1` address;
- reload changed the action from create to reopen, proving the binding persisted;
- **Check NetBank** completed with zero matching receipts and left Internal Balance unchanged;
- no browser console warning/error was recorded.

No scan or real-money payment was performed. The sensitive QR was hidden after acceptance. Production HMAC behavior, key rotation, collision retry, mobile collision rejection, and the one-time alias-token boundary are covered by automated tests.

## Rollout Gates

1. Start with `observe_only`.
2. Apply package migrations and publish package assets.
3. Confirm the address is purpose-bound and absent from general page props.
4. Scan with a small human-authorized amount.
5. Compare the sanitized receipt with NetBank history.
6. Exercise webhook, **Check NetBank**, and schedule convergence.
7. Prove replay produces one receipt, one Inventory operation, and one Account credit.
8. Test below-minimum, above-maximum, currency mismatch, unknown destination, provider outage, and post-settlement status change.
9. Move to `supervised`.
10. Enable `automatic` only after operational review and live UAT.

No automated test or Codex workflow initiates a real-money payment.
