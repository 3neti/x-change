# Voucher Claim Outcome Protocol

## Purpose

A Voucher describes one transferable entitlement. Its claim outcome describes
what consuming that entitlement does.

These are separate axes:

```text
Voucher kind       redeemable | payable | settlement
Claim outcomes     provider_disbursement | account_funding | future outcomes
Selection          claimant | server
Consumption        one_of
Claimant binding   unbound | recipient
Onboarding         never | if_required | required
```

`account_funding` is therefore a claim outcome, not a second code model and not
a Voucher kind. x-change does not maintain an `AccountFundingCode`.

Reviewed funding requests use the complementary collection direction: the
requester owns an ordinary `PAYABLE` Voucher and system Treasury is the allowed
payer. Its `x_change_account_funding` execution driver posts the completed
collection into the requester's Client Funds. Both paths remain normal Voucher
flows.

## Typed instruction contract

`VoucherInstructionsData::$claim` contains an optional
`ClaimInstructionData`:

```php
'claim' => [
    'outcomes' => [
        [
            'key' => 'account_funding',
            'pricing_profile' => 'account-funding-v1',
        ],
    ],
    'selection' => 'server',
    'consumption' => 'one_of',
    'default_outcome' => 'account_funding',
    'onboarding' => ['mode' => 'if_required'],
    'claimant' => [
        'mode' => 'recipient',
        'reference' => 'opaque-recipient-reference',
    ],
],
```

The Voucher package validates the claim policy when the DTO is built:

- at least one uniquely keyed outcome is required;
- selection is `claimant` or `server`;
- consumption is currently `one_of`;
- a default must name a declared outcome;
- recipient binding requires an opaque reference;
- onboarding policy is explicit and independent of outcome selection.

The contract is append-only and optional. Older Voucher payloads continue to
hydrate.

## Compatibility resolver

x-change resolves every Voucher to the typed policy without rewriting stored
instructions:

1. use `instructions.claim` when present;
2. otherwise map existing server-owned settlement metadata;
3. otherwise treat a legacy redeemable Voucher as
   `provider_disbursement`.

Compatibility inference is read-only. New issuance must write the typed claim
contract.

## Outcome dispatch

`DispatchVoucherClaimOutcome` is the single execution boundary. It:

1. locks the Voucher;
2. resolves its claim policy;
3. validates claimant or server selection;
4. verifies recipient binding before creating state;
5. persists one immutable outcome selection per Voucher;
6. delegates to a registered outcome handler;
7. returns the existing result on an identical replay;
8. rejects a different outcome after selection.

The initial handlers are:

- `provider_disbursement`, which delegates to the existing provider claim
  workflow;
- `account_funding`, which releases an existing Pay Code Reserve into claimant
  Client Funds and makes no provider call.

A unique database constraint enforces one selection per Voucher. Handler and
Treasury operation idempotency provide the second line of replay protection.

## Pay Code Funding

An issuer-created Pay Code is eligible for Account Funding only after issuance
has atomically established all of these facts:

1. the claim or compatible settlement policy declares `account_funding`;
2. the `account-funding-v1` pricing profile is used;
3. x-commerce applies the no-payout account-funding waterfall;
4. the exact principal moves from issuer Client Funds to Pay Code Reserve;
5. x-change writes a server-owned `treasury.account_funding` attestation and
   matching reservation descriptor.

Browser metadata is a request, never an eligibility attestation. Inspection
trusts only server-owned Treasury facts and returns an owner-bound,
short-lived token.

```text
issuer Client Funds
        ↓ issue
issuer Pay Code Reserve
        ↓ account_funding claim
claimant Client Funds
```

The claim changes neither provider Inventory nor provider liquidity and never
calls a payout provider.

## Reviewed funding request

The optional reviewed path is for a bank transfer, controlled cash handover,
precious metal, jewelry, vehicle, or another approved source:

```text
Account owner submits Funding Request
        ↓
requester-owned PAYABLE is created LOCKED
and its Pay Code is returned as the request reference
        ↓
maker verifies custody, settlement, and recognized value
        ↓
different checker approves
        ↓
system Account Funding Reserve moves to Pay Code Reserve
        ↓
PAYABLE becomes ACTIVE
        ↓
system Treasury pays the exact target once
        ↓
collection execution credits the owner's Client Funds
```

The browser requires only the requested PHP value. A message, source,
transaction reference, date, verification detail, and one PDF, JPEG, or PNG
Settlement Envelope attachment are optional. The server supplies neutral
defaults for omitted classification and narrative fields. None of those inputs,
the generated Pay Code, or sharing that Pay Code authorizes credit. Approval
succeeds only when the system Account already owns enough recognized Account
Funding Reserve on the selected provider connection.

The funding request stores a unique `voucher_id`. The former
`x_change_account_funding_codes` table is retired by a guarded forward
migration. An upgrade fails closed when legacy rows still exist so operators
must claim or cancel them before migration.

For database compatibility, the enum-backed stored status remains
`funding_code_issued`; package read models expose the canonical
`pay_code_issued` status.

Reviewed Vouchers use the existing `VoucherInstructionsData` extension points:
`voucher_type=payable`, an exact `target_amount`, exact-payment `rules`, and an
`execution` instruction using `x_change_account_funding`. The request creates
that Voucher immediately in `LOCKED`; maker-checker approval activates it after
reserving system Treasury value.

`CompleteVoucherCollection` is the shared finalization boundary for reviewed
Account Funding and provider-confirmed Voucher collections. It locks the
Voucher, enforces remaining-target rules, writes one `VoucherCollection`,
invokes the selected accounting posting, closes the Voucher, records a
sanitized x-journal entry, and publishes an owner-scoped Funding projection
event after commit. Database uniqueness and stable idempotency references make
replays non-monetary.

Checker acceptance dispatches one unique, non-overlapping
`PayApprovedFundingRequestJob`. The job invokes the same idempotent collection
boundary used by the operator command. A synchronous queue completes the credit
within the acceptance request; an asynchronous queue exposes the intermediate
**Adding funds** state until a worker completes it. Exhausted retries do not
invent credit or release the reservation: the request remains retryable, a
sanitized failure event and notice are recorded, and no exception message,
credential, provider account, or evidence content is broadcast.

The job always uses the package-owned `x-change-funding` queue so Account
Funding cannot sit behind broadcasting or unrelated application jobs. An
asynchronous deployment must supervise that queue explicitly:

```bash
php artisan queue:work --queue=x-change-funding
```

## System Account Funding Pay Code utility

`x-change:funding:issue-pay-code` has two deliberately separate modes.

With no positional Pay Code it remains the package-owned utility for issuing a
recipient-bound, redeemable Account Funding Pay Code from recognized system
Account Funding Reserve. It is preview-only unless `--commit` is present.

With a positional Pay Code it previews or retries an already reviewed
requester-owned PAYABLE. Normal Cockpit checker acceptance queues this payment
automatically; positional mode is the explicit operator recovery and
diagnostic surface:

```bash
php artisan x-change:funding:issue-pay-code FUND-XXXX
```

That command is a preview. It displays the immutable request, target, owner,
connection, current collection state, and proposed Treasury posting. It accepts
no amount or recipient override. To execute the one system Treasury payment:

```bash
php artisan x-change:funding:issue-pay-code FUND-XXXX \
    --commit \
    --json \
    --no-interaction
```

The positional mode rejects a code that is not a reviewed Account Funding
PAYABLE, is not maker-checker approved, names a different allowed payer, or
conflicts with direct-issuance options. Repeating the committed command returns
the existing collection and never posts a second credit.

For a guided local flow, run the command without options:

```bash
php artisan x-change:funding:issue-pay-code
```

The direct-issuance command prompts for the Treasury connection, recipient mode,
recipient, exact amount, expiry, and issuance confirmation. It generates a unique
idempotency reference and presents it as the default. Accepting the default
issuance confirmation produces a preview only; explicitly answer yes to reserve
system funds and issue the Pay Code. Evidence and authorization references are
requested only for a committed issuance. Production still requires all
production controls described below.

Interactive prompting is disabled for `--json` and `--no-interaction`. Those
automation paths retain the explicit option contract and never invent missing
economic inputs.

Preview an issuance:

```bash
php artisan x-change:funding:issue-pay-code \
    --amount=1000.00 \
    --recipient-id=5 \
    --connection=netbank-primary \
    --reference=manual-account-funding-user-5-20260726-001 \
    --evidence-reference=treasury-evidence-20260726-001 \
    --json
```

After checking the proposed Account Funding Reserve and Pay Code Reserve
balances, repeat the exact command with `--commit`:

```bash
php artisan x-change:funding:issue-pay-code \
    --amount=1000.00 \
    --recipient-id=5 \
    --connection=netbank-primary \
    --reference=manual-account-funding-user-5-20260726-001 \
    --evidence-reference=treasury-evidence-20260726-001 \
    --commit \
    --json
```

The command:

1. resolves the federated system principal from `3neti/wallet`;
2. selects one explicit active Treasury connection;
3. parses the exact amount without floating-point input arithmetic;
4. defaults to a recipient-bound Voucher;
5. reserves system Account Funding Reserve in system Pay Code Reserve;
6. returns the Pay Code and claim URL;
7. makes no provider call and does not change Provider Inventory; and
8. returns the same Voucher when the same reference and inputs are replayed.

The default interactive amount can be adjusted without changing the explicit
`--amount` option:

```dotenv
XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_INTERACTIVE_DEFAULT_AMOUNT=100.00
```

It does not mint funds, recognize a deposit, or bypass Treasury. Insufficient
system Account Funding Reserve fails closed. The reserve can only be created
from authoritatively reconciled opening value through the guarded Treasury
capitalization workflow; the issuance command cannot create it. `--bearer` is
an explicit, separately configured exception and is always rejected in
production.

The production path is disabled twice by default. Enabling it requires both:

```dotenv
XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_ENABLED=true
XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_ALLOW_PRODUCTION=true
```

A production commit additionally requires a recipient, `--confirm-production`,
`--evidence-reference`, and `--authorization-reference`. The default
authorization service validates these command and configuration controls.
Deployments that require maker-checker or an external approval system must bind
`SystemAccountFundingPayCodeAuthorizationContract` to their approval-aware
implementation before enabling production issuance.

Related controls:

```dotenv
XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_BEARER_ENABLED=false
XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_MAXIMUM_AMOUNT_MINOR=5000000
XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_TTL_SECONDS=604800
XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_ALLOWED_CONNECTIONS=netbank-primary
```

`MAXIMUM_AMOUNT_MINOR` is expressed in the connection currency's minor unit.
For PHP, the default `5000000` is ₱50,000. The command reference is an
idempotency key: keep it stable for retries and use a new reference only for a
genuinely new issuance.

## Account Funding Pay Code audit trail

The Account Funding Pay Code lifecycle records sanitized, append-only x-journal
entries after the corresponding database transaction commits:

- `account_funding.pay_code.issued`
- `account_funding.pay_code.inspected`
- `account_funding.pay_code.outcome_selected`
- `account_funding.pay_code.applied`
- `account_funding.pay_code.paid`
- `voucher.collection.completed`

These entries reference the canonical Voucher, claim, issuance, and Treasury
operation identifiers. They do not duplicate Treasury balances and never store
the raw Pay Code or inspection token. Fixed idempotency keys ensure an issuance
or claim replay resolves to the existing journal entry instead of creating
duplicate audit evidence. The Treasury Position operations and their underlying
ledger transfers remain the accounting authority.

## Cockpit

`/x/cockpit/funding` presents:

1. **Self Top-Up** for the reusable provider-authoritative QR address;
2. **Pay Code Funding** for applying an eligible Pay Code or requesting reviewed
   Account Funding.

The reviewed request surface starts with one required Amount field and an
optional Message. Proof and transfer details stay in a secondary disclosure.
Submission immediately shows the request amount and locked Pay Code, with local
copy controls for the code and a concise follow-up message. The durable request
history shows Pay Code, amount, status, requested time, funded time, and the
currently available control. It survives refresh because the table is a
database-backed read model rather than browser-local state.

The complete code remains owner-visible only while it is actionable; terminal
history masks it. **Pending review** means no value moved. **Ready for
acceptance** means maker evidence was recorded. **Adding funds** means the
checker reserved value and the unique System Treasury payment is queued.
**Funded** means one collection and one Account credit completed. Realtime
owner-scoped events refresh the history and Funding position after each
transition, with existing polling as a fallback.

Reviewers can download sanitized evidence through an authenticated `no-store`
endpoint. General Cockpit projections and broadcasts do not expose storage
paths, claimant references, Treasury Position references, raw evidence,
messages, Pay Codes, or provider account details.

`/x/cockpit/quick-generate` is the issuance-side entry point. Its prominent
**Recipient receives** control emits the typed claim instruction:

- **Cash payout** writes `provider_disbursement`;
- **Account funds** writes an `account_funding`-only policy, forces a whole
  amount, and disables payout-only rail, fee, and slicing controls.

For a verified mobile recipient, the server replaces the browser value with an
opaque claimant reference. `CASH` remains an intentionally unbound bearer
claim. Mixed payout-and-funding issuance remains unavailable until the
execution-cost reserve exists.

After Account Funding issuance, the result card shows the Pay Code and links to
`/x/cockpit/funding?mode=pay_code`. The Pay Code is never placed in the URL.
Funding opens directly on **Pay Code Funding**, where the authenticated claimant
can inspect and apply it through the existing owner-authorized claim flow.

## Security boundaries

- Webhooks and uploaded narratives are evidence, not monetary authority.
- The maker who verifies backing cannot approve the request.
- Reviewer access is fail-closed and configured explicitly.
- Recipient-bound claims compare opaque, server-derived claimant references.
- Claim requests accept no amount, currency, destination, account, or recipient.
- Positional PAYABLE execution accepts no amount, recipient, Account, or
  connection override.
- Account Funding makes zero provider calls.
- Provider disbursement remains subject to its existing validation and approval
  controls.
- Funding Request attachments use a private configured disk, strict
  PDF/JPEG/PNG MIME and size validation, SHA-256 hashes, authenticated
  owner/reviewer access, and `no-store` delivery. The Settlement Envelope
  scanner extension remains the quarantine boundary when a malware scanner is
  configured.

## Mixed outcomes and execution-cost reserve

A Voucher may declare several outcomes under `one_of`, but x-change currently
rejects issuance that combines `provider_disbursement` and `account_funding`.
That is intentional.

Before mixed issuance can be enabled, issuance must reserve:

```text
principal
+ maximum execution cost across all offered outcomes
```

After the immutable outcome selection:

- provider disbursement consumes its actual provider cost;
- account funding releases the unused execution-cost reserve;
- the non-selected outcome can never execute.

Until this first-class execution-cost reserve is implemented across
`3neti/wallet`, `3neti/x-commerce`, and `3neti/x-change`, rejecting an unpriced
dual outcome prevents under-reserved provider payouts.

## Runtime configuration

```dotenv
# Comma-separated authenticated reviewer IDs. Empty is fail-closed.
XCHANGE_FUNDING_REQUEST_REVIEWER_IDS=

# Seven days by default.
XCHANGE_REVIEWED_FUNDING_PAY_CODE_TTL_SECONDS=604800

# Private evidence intake.
XCHANGE_FUNDING_REQUEST_ATTACHMENTS_ENABLED=true
XCHANGE_FUNDING_REQUEST_EVIDENCE_DISK=local
XCHANGE_FUNDING_REQUEST_ENVELOPE_DRIVER=account-funding-review
```

`XCHANGE_ACCOUNT_FUNDING_CODE_TTL_SECONDS` remains a deprecated fallback for
one compatibility window.

All workflow rules, routes, read models, UI, tests, and documentation live in
`3neti/x-change`. The host supplies environment values, runs migrations and
publishes package assets.

## Acceptance

The minimum proof is:

1. typed DTO serialization and invariant tests pass;
2. legacy Vouchers resolve without persisted mutation;
3. one Voucher can persist only one outcome selection;
4. outcome selection and handler replays are idempotent;
5. recipient binding fails before state is written;
6. unsupported and mixed unpriced outcomes fail closed;
7. eligible Account Funding moves one exact reserve with no provider call;
8. reviewed approval requires maker-checker separation and recognized system
   Account Funding Reserve;
9. one reviewed request issues one real Voucher;
10. only system Treasury can pay the approved requester-owned PAYABLE;
11. the Cockpit exposes Pay Code vocabulary without an owner claim action;
12. Quick Generate emits the typed outcome and hands Account Funding issuance
    to the Funding workspace without exposing the Pay Code in navigation;
13. private evidence is hash-addressed and owner/reviewer scoped;
14. collection, journal, Treasury posting, and Echo effects occur once on
    replay;
15. checker acceptance queues one unique System Treasury payment and exposes an
    honest intermediate state while an asynchronous worker is pending;
16. exhausted retries preserve the reservation and record only sanitized
    retry-required evidence;
17. focused backend and frontend suites pass.

### Implemented acceptance — 2026-07-25

- Voucher contract tests passed for serialization, compatibility, and
  constructor-level invariants.
- x-change funding, policy, dispatch, authorization, maker-checker,
  recipient-binding, idempotency, and documentation tests passed.
- The focused Cockpit Funding component suite passed.
- Package-owned Cockpit assets matched the published host projection.
- The production build completed successfully.
- Signed-in browser acceptance passed at the normal desktop viewport and at
  `390 × 844`.
- At both widths, the Pay Code Funding panel, Pay Code input, reviewed-request
  disclosure, and request list remained inside the viewport with no horizontal
  overflow.
- No stale `Account Funding Code` wording appeared in the rendered page.
- The application emitted no browser-console error. Observed warnings belonged
  to unrelated Chrome extensions.

### Quick Generate handoff acceptance — 2026-07-26

- Quick Generate rendered the **Recipient receives** selector at the normal
  desktop viewport and at `390 × 844`.
- **Account funds** remained selected and disabled payout rail and open-slice
  controls at both widths.
- Neither Quick Generate nor Funding produced horizontal overflow.
- `/x/cockpit/funding?mode=pay_code` opened **Pay Code Funding** directly with
  its Pay Code input visible; the generated Pay Code was not present in the URL.
- The application emitted no browser-console error.

### Reviewed funding browser lifecycle acceptance — 2026-07-27

- An authenticated Account owner requested exactly `PHP 17.00` from the
  **Pay Code Funding** workspace. The browser immediately displayed one pending
  reviewed request and its masked Pay Code (`••••VCVB` after completion).
- A distinct maker recognized the backing against `netbank-primary`; the
  authenticated checker then selected **Approve and fund Account**.
- The request advanced through `submitted → awaiting_approval →
  funding_code_issued → completed`. While the asynchronous payment was queued,
  the owner saw the honest intermediate state **Adding funds**.
- One worker consumed the dedicated `x-change-funding` queue and invoked the
  normal Account Funding collection handler. The Voucher closed with exactly
  one `PHP 17.00` collection and no provider payout or provider API call.
- Client Funds moved from `PHP 159.00` to `PHP 176.00`. System Account Funding
  Reserve moved from `PHP 505.02` to `PHP 488.02`, and system Pay Code Reserve
  returned to zero.
- A fresh browser visit retained **Funded**, the masked terminal code, and the
  refreshed `PHP 176.00` header. Polling supplied the fallback update while
  Reverb was not running.
- Replaying the completed payment job left the collection count at one and all
  Treasury positions unchanged.
- The Funding Request event stream contains one event for each transition. The
  x-journal contains one sanitized `account_funding.pay_code.paid` entry and
  does not persist the raw Pay Code.
- Production deployments using an asynchronous queue must keep an
  `x-change-funding` worker active. With the synchronous queue driver, checker
  acceptance completes the same idempotent payment inline.
