# Pay Code and Account Funding Code Protocol

## Purpose

The Funding workspace accepts two distinct no-payout instruments:

1. an eligible **Pay Code** that its issuer explicitly created for Account
   Funding; and
2. a reviewed **Account Funding Code** that the system issues after a
   maker-checker process verifies externally supplied value.

Both instruments move already-recognized and reserved value into an x-change
Account. Neither is a payout Pay Code. Claiming either one does not call
NetBank, Paynamics, or another payout provider and cannot move money out of a
provider account.

## Cockpit experience

`/x/cockpit/funding` presents two primary paths:

1. **Self Top-Up** uses the immutable Account Funding Address and
   provider-authoritative transaction history.
2. **Pay Code Funding** accepts an eligible Pay Code, displays a sanitized
   amount-and-expiry preview, and adds the reserved value to Client Funds after
   explicit confirmation.

The reviewed request form is secondary inside Pay Code Funding. It is used only
when the user first needs the system to verify a bank transfer, controlled cash
handover, precious metal, jewelry, vehicle, or another approved source and
issue a recipient-bound Account Funding Code.

Exact provider Funding Intents and the rollback-only simulation remain
available as secondary advanced tools. Provider credentials remain in Cockpit
Accounts; they are never displayed or edited on the Funding page.

## Eligible Pay Code contract

A general Pay Code is never eligible merely because a caller labels it as
Account Funding. Issuance must complete all of these controls atomically:

1. the instruction explicitly selects the `account_funding` settlement
   destination and `account-funding-v1` pricing profile;
2. x-commerce applies `pay-code-account-funding-waterfall`, which contains no
   provider-transfer-cost allocation;
3. x-change moves the exact Pay Code principal from the issuer's Client Funds
   Position to the issuer's Pay Code Reserve;
4. x-change writes a server-owned `treasury.account_funding` attestation and a
   matching reservation descriptor on the voucher.

The browser-supplied instruction is a request, not an eligibility attestation.
Inspection trusts only the server-owned Treasury metadata. Legacy Pay Codes,
payout-only Pay Codes, named-slice Pay Codes, codes with provider cost, codes
without a matching reserve, expired codes, and redeemed codes fail closed.

Inspection uses a short-lived, owner-bound opaque token. The Funding read model
shows only the code hint, amount, currency, expiry, and safe status. It never
exposes the full code, Treasury Position references, provider account details,
commercial allocations, or operation references.

Claiming an eligible Pay Code:

```text
issuer Client Funds
        ↓ issue
issuer Pay Code Reserve
        ↓ claimant confirms in Funding
claimant Client Funds
```

The claim locks the voucher, re-evaluates eligibility, releases the exact
reserve once, creates one `VoucherClaim` with settlement mode
`account_funding`, and marks the voucher redeemed. Provider Inventory is
unchanged and no payout provider is called. A unique claim scope and stable
Treasury operation reference make replays a no-op.

## Lifecycle

The optional reviewed Account Funding Code lifecycle is:

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

## Reviewed-code monetary authority

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

## Reviewed-code maker-checker and access

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

## Reviewed-code idempotency and accounting

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

1. a payout-only or caller-tagged Pay Code fails closed;
2. eligible Pay Code issuance posts zero provider cost and one exact reserve;
3. inspection discloses only the sanitized preview;
4. claiming moves the reserve to claimant Client Funds exactly once;
5. Pay Code Funding changes neither provider Inventory nor provider liquidity;
6. submitting a reviewed request does not change any Account;
7. an unconfigured user cannot see or act on the review queue;
8. the maker cannot approve their own backing review;
9. reviewed-code issuance fails without recognized system Client Funds;
10. one reviewed request produces one code and one reservation;
11. only the intended Account owner can claim the reviewed code;
12. replaying either claim creates no second movement;
13. both claim paths make zero payout-provider calls;
14. the Funding page renders both primary paths at desktop and mobile widths.

### Implemented acceptance — 2026-07-25

The package workflow passed its focused funding, authorization, lifecycle, and
documentation tests. The Funding page passed its focused component suite and
the published Cockpit assets matched package source.

Earlier signed-in browser acceptance confirmed the reviewed-code foundation:

- `Self Top-Up` and the funding-code path are the primary funding paths;
- provider instructions and simulations remain inside the advanced disclosure;
- the request panel and modal have no horizontal overflow at desktop width;
- at `390 × 844`, the modal remains inside the viewport and neither the modal
  nor the page scrolls horizontally;
- the modal states that a request cannot credit an Account and that files are
  not yet accepted;
- no application console errors were produced during the acceptance flow.

The Pay Code Funding expansion was accepted on the same date:

- `Pay Code Funding` replaces `Account Funding Code` as the primary tab label;
- `Fund with Pay Code`, the code input, and `Check Code` appear before the
  optional reviewed request;
- an unknown code returns a compact, sanitized unavailable state;
- the reviewed Account Funding Code form remains collapsed by default;
- the page has no horizontal overflow at the normal desktop viewport or at
  `390 × 844`;
- the mobile input and action remain inside the viewport;
- no application console errors were produced during the acceptance flow.
