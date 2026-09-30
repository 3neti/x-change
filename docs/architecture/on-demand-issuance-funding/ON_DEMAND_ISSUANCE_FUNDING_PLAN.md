# On-Demand Issuance Funding Plan

Last updated: 2026-09-30

## Objective

Allow a host application to fund one frozen Pay Code instruction at issuance
time using an exact-amount bank transfer, QR Ph payment, or—after a later
controlled gate—another Pay Code.

On-Demand Issuance Funding is an application-wide feature. It must not create
a second issuance engine, another Bavix wallet, an open-amount payment request,
or a browser-authorized financial shortcut.

`GeneratePayCode` remains the only Pay Code issuance authority. The new
workflow prepares and funds an immutable order before calling that authority.

## Application-wide policy

The package configuration target is:

```php
'issuance_funding' => [
'on_demand' => [
    'enabled' => env('XCHANGE_ON_DEMAND_ISSUANCE_ENABLED', false),
    'basis' => env('XCHANGE_ON_DEMAND_FUNDING_BASIS', 'full_amount'),
    'fixed_qr_ph' => [
        'enabled' => env('XCHANGE_ON_DEMAND_FIXED_QR_PH_ENABLED', false),
        'netbank_mode' => env('XCHANGE_ON_DEMAND_NETBANK_QR_MODE', 'direct_qr'),
    ],
    ],
],
```

The shared beta target is:

```dotenv
XCHANGE_ON_DEMAND_ISSUANCE_ENABLED=true
XCHANGE_ON_DEMAND_FUNDING_BASIS=full_amount
```

The package default remains disabled for backward compatibility. When the
feature is enabled, `full_amount` is the default basis.

NetBank fixed QR has two explicit modes. `direct_qr` reuses the proven `/x/pay`
instruction service: it derives a unique order-bound destination and embeds the
leased amount in the QR without requesting token registration or an exact
provider limit. `registered_vca` uses NetBank pre-transaction validation,
registration, and the provider-side exact limit. Mode selection is explicit;
failure never triggers an automatic fallback.

Supported bases:

| Basis | Existing Client Funds | On-demand payment |
| --- | --- | --- |
| `full_amount` | Ignored and left untouched | Complete instruction cost |
| `shortfall` | Atomically reserved for the instruction | Cost less the reserved contribution |

The policy belongs to the application, not a user, Account, campaign, or
template. Every funding order snapshots the effective policy so a later
configuration change cannot alter an in-progress economic instruction.

## Authoritative amount

The server calculates the payment requirement from the accepted pricing
snapshot. Vue never calculates or edits the authoritative amount.

Example:

| Component | Amount |
| --- | ---: |
| Pay Code principal | PHP 50.00 |
| Service costs | PHP 3.00 |
| Instruction cost | PHP 53.00 |

For `full_amount`, the on-demand amount is PHP 53.00 even when the Account has
existing Client Funds.

For `shortfall`, existing Client Funds are first reserved. If PHP 30.00 is
available, the on-demand amount is PHP 23.00.

## Payment-method order

The On-Demand Issuance modal is intentionally different from the Account
Funding page:

1. Bank Transfer is first and selected by default.
2. QR Ph is the secondary method.
3. Pay Code funding remains unavailable until its exact-order settlement gate
   is complete.

The shared selector may be reused, but its default selection and instructions
are authored by the `pay_code_issuance` context.

## Bank-transfer amount reservation

NetBank bank transfers do not provide an x-change-controlled VCA for each
order. Service costs may incidentally create uncommon amounts, but they are
not a collision-control mechanism.

The instruction surface therefore uses the configured standing NetBank account
and exact order amount. It must not call the provider's VCA alias-token,
pre-transaction-reference, exact-limit, or QR-generation APIs merely to render
ordinary bank-transfer directions.

x-change therefore leases an exact transfer amount within the active provider
scope:

1. calculate the real instruction cost;
2. attempt to reserve that exact amount;
3. use it unchanged when no active order already owns it; and
4. add the smallest available configured reconciliation adjustment only when
   a collision exists.

Example without a collision:

| Component | Amount |
| --- | ---: |
| Instruction cost | PHP 53.00 |
| Reconciliation adjustment | PHP 0.00 |
| **Transfer exactly** | **PHP 53.00** |

Example with a collision:

| Component | Amount |
| --- | ---: |
| Instruction cost | PHP 53.00 |
| Reconciliation adjustment | PHP 0.17 |
| **Transfer exactly** | **PHP 53.17** |

The adjustment is not principal, a fee, or revenue. It is disclosed
separately. After successful issuance, any unconsumed adjustment remains
client-owned value and follows the governed residual Client Funds policy.

Matching requires the provider connection, destination, currency, exact
leased amount, observation window, settled status, destination verification,
and an unused provider transaction identifier. Sender identity may strengthen
evidence but is not authoritative unless the provider contract proves it.

The provider transaction identifier is claimed through a durable database
boundary before settlement. x-change stores a provider-scoped SHA-256 claim,
not another clear-text copy of the transaction identifier. One provider
transaction may belong to one Funding Intent only; a retry for the same intent
is idempotent, while an attempt to reuse it for another order enters explicit
review and cannot issue.

An amount other than the leased amount cannot automatically issue the Pay
Code. It enters the explicit underpayment, excess, or attention path.

## Fixed-amount QR Ph

The QR Ph method must create an order-specific payment-purpose QR whose payload
contains the exact amount to pay. The payer cannot enter or alter that amount.

When the provider cannot prove fixed-amount QR capability, QR Ph is unavailable
for On-Demand Issuance. The workflow must not substitute a standing or
open-amount QR.

The capability is also guarded by a disabled-by-default application switch.
When enabled, x-change asks the installed provider adapter for the same
order-specific funding instructions used by the authoritative Funding Intent.
The Cockpit exposes QR Ph only when the returned DTO declares
`embeddedAmount=true` and supplies an image payload. Bank Transfer remains the
default method.

The package-owned `on_demand_issuance_fixed_qr_demo` lifecycle scenario uses a
clearly marked, non-monetary QR simulator. It proves exact QR presentation,
collision-safe amount leasing, expiry, cooling, and verified late-payment
disposition, then rolls every database and Account change back. It never calls
a live provider.

## Persistent modal experience

Once an Issuance Funding Order is created, the modal remains the transaction's
workflow surface until the Pay Code is issued, the order is explicitly
cancelled, or the order enters a governed attention state.

```text
Review funding
  -> awaiting payment
  -> payer acknowledged / provider observed
  -> verifying
  -> funds secured
  -> issuing
  -> Pay Code issued
```

After creation:

- clicking the backdrop does not dismiss the modal;
- Escape does not dismiss it;
- the frozen instruction is no longer editable;
- refreshing restores and reopens the active order;
- closing the browser does not cancel the server-side order;
- a visible cancellation action remains available when cancellation is safe;
- payment verification alone does not close the modal; and
- the issued Pay Code result replaces the progress state in the same modal.

For Bank Transfer, the primary action is:

```text
I've made the transfer — Check payment
```

That action records payer acknowledgement and requests an immediate provider
check. It is never payment evidence. Webhooks and scheduled verification may
still complete the order when the browser is closed.

The modal closes only after the user acknowledges the issued result, explicitly
cancels a cancellable order, or follows a governed attention-resolution path.

## Treasury treatment

The user-facing label is:

```text
Client Funds — On-Demand Issuance Hold
```

This is not another Bavix wallet. It is an order-bound Treasury allocation of
client-owned value. The provider-neutral accounting primitive belongs to
`3neti/wallet`; x-change supplies the commercial reason and external order
reference.

The wallet package may use a neutral label such as `Encumbered Client Funds`.

### Full-amount flow

```text
Verified provider payment
  -> order-bound Encumbered Client Funds allocation
  -> principal to Pay Code Reserve
  -> service costs to governed fee positions
  -> GeneratePayCode
```

Existing available Client Funds remain untouched. Newly received funds never
become generally spendable while the issuance job is pending.

### Shortfall flow

```text
Available Client Funds contribution
  -> order-bound Encumbered Client Funds allocation

Verified shortfall payment
  -> same order-bound allocation

Combined allocation
  -> Pay Code Reserve + governed fee positions
  -> GeneratePayCode
```

The existing contribution is reserved when the order is created. Cancellation
or expiry releases that contribution through an evidenced Treasury operation.

## Package ownership

### `3neti/wallet` owns

- provider-neutral place, consume, release, and reverse hold/allocation
  mechanics;
- integer minor-unit and currency invariants;
- locking, idempotency, allocation balances, and immutable operations;
- Treasury read models; and
- Bavix integration details.

### `3neti/wallet` must not own

- Pay Codes or vouchers;
- QR Ph, NetBank, or provider matching;
- Quick Generate or modal presentation;
- pricing snapshots or funding bases;
- Funding Intents;
- Maker/Checker authority; or
- automatic issuance jobs.

### `3neti/x-change` owns

- application-wide feature policy;
- authoritative pricing and exact amount calculation;
- the frozen Issuance Funding Order;
- amount leasing and provider evidence matching;
- Funding Intents and payment-method orchestration;
- Maker/Checker requirements;
- principal and fee disposition;
- issuance resumption; and
- Cockpit read models and lifecycle scenarios.

## Issuance Funding Order

The immutable order records at least:

- stable order reference;
- Account and issuer references;
- encrypted normalized instructions;
- instruction fingerprint;
- accepted pricing/offering snapshot;
- snapshotted application funding basis;
- principal, service cost, required amount, reserved Client Funds contribution,
  and on-demand amount in minor units;
- base amount, reconciliation adjustment, and exact transfer amount;
- currency and expiry;
- Funding Intent and Treasury allocation references;
- versioned state;
- issued voucher identifier; and
- attention or failure reason.

No Pay Code exists at order preparation.

## State model

```text
prepared
  -> awaiting_payment
  -> payer_acknowledged | payment_detected
  -> verifying
  -> funded
  -> issuing
  -> issued
```

Explicit non-success states include:

- `underfunded`;
- `payment_ambiguous`;
- `quote_expired`;
- `cancelled`;
- `expired`; and
- `issuance_attention`.

Browser polling reads these states. It never verifies money, moves Treasury
positions, or creates a voucher.

## Cancellation policy

| State | Cancellation behavior |
| --- | --- |
| Prepared / awaiting payment | Immediate cancellation |
| Payer acknowledged, payment not found | Cancel with late-payment warning |
| Verification in progress | Temporarily unavailable |
| Underfunded / excess / duplicate evidence | Review required; cancellation is not presented as safe |
| Funded / issuing | Unavailable while issuance finalizes |
| Issuance attention | Governed release or recovery |
| Issued | Normal Pay Code lifecycle controls |

A late payment for a cancelled or expired order remains provider evidence. It
must enter the configured residual Client Funds or suspense disposition and
must not revive the instruction silently.

## Controlled implementation gates

### Gate 1 — Wallet allocation primitive

Implement and release the provider-neutral hold/allocation enhancement in
`3neti/wallet`. Prove place, consume, release, reversal, concurrency, and
idempotency without importing x-change concepts.

### Gate 2 — Application policy

Add typed x-change configuration, strict-doctor validation, commissioning
fingerprinting, and operator-safe inspection. The disabled feature preserves
current behavior. The enabled default basis is `full_amount`.

### Gate 3 — Frozen order and preparation

Persist the encrypted immutable order and authoritative quote. Return `202
funding_required` without issuing or moving money in `full_amount` mode.

### Gate 4 — Shortfall reservation

Atomically reserve the Client Funds contribution only when the application
basis is `shortfall`. Release it on cancellation or expiry.

### Gate 5 — Persistent funding modal

Reuse the funding selector with Bank Transfer first, fixed read-only amounts,
state restoration, safe cancellation, cooldowns, accessibility, and the issued
result in the same modal.

### Gate 6 — Bank Transfer

Bind the existing collision-safe exact-amount reservation to the funding
order. Prefer the unadjusted base amount when available; add and disclose a
small adjustment only for an active collision.

Status: implemented locally. Leases are scoped by provider, connection, and
currency; guarded by a distributed lock and database uniqueness; retained
through an expiry cooling period; and released only after the related Funding
Intent is terminal.

### Gate 7 — Fixed-amount QR Ph

Create an order-specific fixed-amount provider QR or fail the method closed.

### Gate 8 — Verified settlement

Recognize authoritative provider evidence and place the value into the
order-bound Treasury allocation. Dispatch the next step only after commit.

### Gate 9 — Automatic issuance

Run a unique, retry-safe job that locks and revalidates the order, consumes
held value into principal and fee positions, calls `GeneratePayCode`, and saves
the voucher identifier exactly once.

### Gate 10 — Recovery

Cover partial, excess, late, duplicate, reversed, expired, cancelled, and
ambiguous payments without silent repricing or issuance.

Status: complete locally. Expired and cancelled orders remain eligible for
authoritative verification during the amount cooling period. An exact late
payment is credited to Client Funds, permanently recorded on the terminal
order, and cannot restart issuance. Settled short payments become
`underfunded`; settled excess payments and other mismatches become
`payment_ambiguous`. These states stop automatic polling and issuance, retain
the evidence for operator review, and never silently reprice the order.

Provider evidence is claimed once through a database unique constraint before
settlement. Same-intent retries are idempotent; cross-intent reuse is rejected
into suspense. A provider reversal writes a durable marker to the related
order. If issuance has not completed, the order moves to
`issuance_attention`, and the queued issuance job independently refuses to run.
If a Pay Code was already issued, its immutable lifecycle is not rewritten;
the reversal and recovery remain explicit evidence for governed handling.

### Gate 11 — Pay Code funding

Enable only after exact-order binding, recursion prevention, and settlement
parity are proven.

### Gate 12 — Lifecycle scenarios and release

Prove `full_amount` and `shortfall` locally, including browser restoration,
duplicate provider evidence, cancellation, and Treasury balances. Publish in
dependency order, adopt in the sandbox, then request separate authorization
for any live provider test.

## Verification matrix

Minimum automated coverage must prove:

- disabled configuration preserves current issuance behavior;
- `full_amount` leaves existing available Client Funds untouched;
- `shortfall` reserves the contribution once;
- the base transfer amount is used when its lease is available;
- active collisions receive distinct exact amounts;
- a wrong amount never auto-issues;
- payer acknowledgement alone has no financial effect;
- provider evidence settles one order once;
- concurrent workers issue exactly one Pay Code;
- fees and principal reach their correct Treasury positions;
- configuration changes do not mutate existing orders;
- modal state restores after refresh; and
- cancellation and late-payment paths preserve all value and evidence.

## Release order

```text
3neti/wallet contract and runtime
  -> wallet release
  -> x-change dependency adoption
  -> x-change policy/order/backend
  -> Cockpit modal
  -> local lifecycle verification
  -> x-change release
  -> sandbox adoption
  -> explicitly authorized testing-instance payment
```

No push, tag, release, host adoption, deployment, or live-money test is
authorized by this plan.

## Shipped evidence: v1.0.69–v1.0.74

| Release | Evidence |
| --- | --- |
| `v1.0.69` (`9f3275ec`) | Strict doctor rejects an invalid on-demand funding basis before runtime. |
| `v1.0.70` (`b5a2fb84`) | Quick Generate gained the persistent funding workspace and issued-stamp handoff. |
| `v1.0.71` (`7978d220`) | Bank-transfer verification uses the configured corporate NetBank account rather than the QR-purpose address. |
| `v1.0.72` (`f657d6af`) | The funding workspace presents the configured corporate account number and account name correctly. |
| `v1.0.73` (`1edd65fa`) | Automatic, restrained payment polling became part of the modal lifecycle. |
| `v1.0.74` (`2a12755c`) | Successful issuance presents the correct Pay Code amount and normal stamp/share result. |

The release sequence was exercised with an authorized PHP 25.03 GCash
direct-QR payment. Provider evidence was detected, the order advanced through
the hold and issuance lifecycle, and the issued Pay Code result replaced the
funding controls. The Gate 10 recovery hardening documented above is newer
local package work and still requires a separately authorized publication and
host-adoption gate.
