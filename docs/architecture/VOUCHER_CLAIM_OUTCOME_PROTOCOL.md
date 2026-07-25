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
a Voucher kind. x-change does not maintain an `AccountFundingCode`. Reviewed
funding requests issue ordinary `LBHurtado\Voucher\Models\Voucher` records with
a recipient-bound, server-selected `account_funding` outcome.

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
maker verifies custody, settlement, and recognized value
        ↓
different checker approves
        ↓
system Client Funds move to Pay Code Reserve
        ↓
recipient-bound Voucher is issued
        ↓
Account owner claims the account_funding outcome once
        ↓
reserved value moves to the owner's Client Funds
```

The browser supplies a requested value and supporting description only. It
cannot authorize credit. Approval succeeds only when the system Account already
owns enough recognized Client Funds on the selected provider connection.

The funding request stores a unique `voucher_id`. The former
`x_change_account_funding_codes` table is retired by a guarded forward
migration. An upgrade fails closed when legacy rows still exist so operators
must claim or cancel them before migration.

For database compatibility, the enum-backed stored status remains
`funding_code_issued`; package read models expose the canonical
`pay_code_issued` status.

Reviewed Vouchers use a Treasury-backed issuance path. It creates the Voucher
and its typed instructions without minting a second Cash entity or debiting the
system compatibility wallet. The Treasury reservation is the sole monetary
backing.

## Cockpit

`/x/cockpit/funding` presents:

1. **Self Top-Up** for the reusable provider-authoritative QR address;
2. **Pay Code Funding** for inspecting and adding any eligible Pay Code.

The reviewed request is secondary inside Pay Code Funding. When approved, the
owner-only read model displays the complete Pay Code so the intended recipient
can claim it. General Cockpit projections do not expose claimant references,
Treasury Position references, evidence, or provider account details.

## Security boundaries

- Webhooks and uploaded narratives are evidence, not monetary authority.
- The maker who verifies backing cannot approve the request.
- Reviewer access is fail-closed and configured explicitly.
- Recipient-bound claims compare opaque, server-derived claimant references.
- Claim requests accept no amount, currency, destination, account, or recipient.
- Account Funding makes zero provider calls.
- Provider disbursement remains subject to its existing validation and approval
  controls.
- File attachments remain disabled until private storage, validation, malware
  quarantine, access logging, retention, and legal-hold controls exist.

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
   Client Funds;
9. one reviewed request issues one real Voucher;
10. only the bound Account owner can claim it;
11. the Cockpit exposes Pay Code vocabulary and a compact owner-only action;
12. focused backend and frontend suites pass.

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
