# Account Funding Code Protocol

## Purpose

An Account Funding Code is a one-time, recipient-bound instruction for moving
already-recognized and reserved value into an x-change Account. It is for
funding sources that cannot safely use the automated QR Ph path, such as a large
bank transfer, controlled cash handover, precious metal, jewelry, a vehicle, or
another approved asset.

It is not a payout Pay Code. Claiming it does not call NetBank, Paynamics, or
another payout provider. It cannot move money out of the provider account.

## Cockpit experience

`/x/cockpit/funding` presents two primary paths:

1. **QR Ph Self Top-Up** uses the immutable Account Funding Address and
   provider-authoritative transaction history.
2. **Account Funding Code** begins a controlled request and ends with a
   one-time Account allocation.

Exact provider Funding Intents and the rollback-only simulation remain available
as secondary advanced tools. Provider credentials remain in Cockpit Accounts;
they are never displayed or edited on the Funding page.

## Lifecycle

```text
Account owner submits Funding Request
        ↓
maker independently verifies custody, settlement, and recognized value
        ↓
request awaits a different checker
        ↓
checker reserves already-recognized system Client Funds
        ↓
recipient-bound Account Funding Code is issued
        ↓
Account owner claims once
        ↓
reserved value moves to the owner's Client Funds
```

The request lifecycle is:

```text
submitted
→ under_review / needs_information
→ awaiting_approval
→ funding_code_issued
→ completed
```

Terminal request states are `rejected`, `withdrawn`, and `expired`. The code has
its own `issued`, `claimed`, `expired`, or `revoked` lifecycle.

## Monetary authority

The browser supplies only a requested value. It never supplies credit authority.
A receipt, screenshot, narrative, appraisal, or transaction reference is
supporting evidence only.

The maker records a recognized value only after the relevant external control
has passed:

- a bank transfer must be matched against authoritative bank or EMI history;
- cash requires controlled custody acceptance and a durable receipt;
- property requires custody, title/source review, independent valuation, and an
  approved legal and liquidity policy.

Approval still cannot manufacture value. Code issuance succeeds only if the
configured system Account already owns enough recognized Client Funds on the
selected Treasury connection. Those funds are transferred into its reserve
before the code exists.

This first implementation uses the existing system Account `pay_code_reserve`
Treasury Position as the compatible reserve primitive. Every movement is tagged
with purpose `account_funding_code`, and the code explicitly disables provider
payout. A future wallet accounting wave may introduce a dedicated
`account_funding_reserve` purpose without changing the request or claim
contract.

## Maker-checker and access

Funding reviewers are fail-closed. Configure explicit user identifiers:

```dotenv
XCHANGE_FUNDING_REQUEST_REVIEWER_IDS=1,2
```

An empty value gives no user access to the operator queue. The maker who records
the backing evidence cannot approve that same request. A different configured
reviewer must approve it.

Owner rules:

- users see only their own requests, notices, and claimable codes;
- a claim is bound to the original requester type, identifier, and Account;
- the code secret, Account reference, requester notes, and review notes are not
  exposed in the general Cockpit read model.

Reviewer rules:

- only configured reviewers see the operator queue;
- approval accepts no amount, currency, evidence, provider transaction, or raw
  payload from the browser;
- the immutable value and evidence prepared by the maker are used;
- system-user resolution is an accounting identity and does not grant human
  review access.

## Idempotency and accounting

Funding Request creation hashes the owner and client idempotency key and
fingerprints the request type, Account, requested value, and currency.

Code issuance has one database row per Funding Request. Replays return the same
code. The Treasury reservation and claim use stable operation and idempotency
references derived from the Funding Request reference.

Claiming:

1. locks the code and Funding Request;
2. verifies the bound recipient and expiry;
3. releases the exact reserve amount into the bound Account Client Funds
   Position;
4. marks the code and request completed;
5. returns the existing result on replay.

Provider Inventory does not change during the reserve or claim because this is a
reallocation of value already recognized inside the same provider connection.
No payout provider is invoked.

## In-app notices

`x_change_funding_request_notices` is the package-owned durable in-app notice
projection. A code-ready notice includes a safe claim action reference, not the
code secret or evidence. Notification delivery is not monetary authority and a
delivery failure cannot undo a committed reservation or claim.

## Attachment boundary

File uploads are deliberately disabled in this slice:

```php
config('x-change.funding.requests.attachments_enabled') === false
```

The form accepts structured descriptions, dates, and transaction or custody
references. Enabling attachments requires a separate security slice with:

- private encrypted storage and opaque paths;
- MIME, extension, and size validation;
- checksum and malware quarantine;
- metadata stripping where applicable;
- authorized temporary access and access logging;
- retention, purge, and legal-hold policies.

Until every control exists, the API rejects an `attachment` field.

## Runtime configuration

```dotenv
# Comma-separated authenticated user IDs. Empty is fail-closed.
XCHANGE_FUNDING_REQUEST_REVIEWER_IDS=

# Seven days by default.
XCHANGE_ACCOUNT_FUNDING_CODE_TTL_SECONDS=604800
```

The host owns environment values, migrations, queues, and published assets. All
workflow rules, routes, read models, UI, tests, and documentation remain in the
`3neti/x-change` package.

## Acceptance

The minimum acceptance proof is:

1. submitting a request does not change any Account;
2. an unconfigured user cannot see or act on the review queue;
3. the maker cannot approve their own backing review;
4. code issuance fails without recognized system Client Funds;
5. one request produces one code and one reservation;
6. only the intended Account owner can claim;
7. replaying issuance or claim creates no second movement;
8. the claim makes zero payout-provider calls;
9. the Funding page renders the two primary paths at desktop and mobile widths.

### Implemented acceptance — 2026-07-25

The package workflow passed its focused funding, authorization, lifecycle, and
documentation tests. The Funding page passed its focused component suite and
the published Cockpit assets matched package source.

Signed-in browser acceptance confirmed:

- `Self Top-Up` and `Account Funding Code` are the only primary funding paths;
- provider instructions and simulations remain inside the advanced disclosure;
- the request panel and modal have no horizontal overflow at desktop width;
- at `390 × 844`, the modal remains inside the viewport and neither the modal
  nor the page scrolls horizontally;
- the modal states that a request cannot credit an Account and that files are
  not yet accepted;
- no application console errors were produced during the acceptance flow.
