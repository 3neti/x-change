# Checkout and Owner Payments Console Plan

Last updated: 2026-10-11

## Objective and sequence

Introduce a durable Checkout in x-change for purchase-style Pay Code issuance.
Integrate public issuance first. Keep the buyer's composer and payment recovery
under `/x/auto-generate`; reserve `/x/checkout` for the temporary owner payments
console. Implement in the x-change package, test there, publish its assets to
the host, and verify a pinned deployment before enabling new public orders.

This document is the implementation contract. The companion
[`CHECKOUT_COMPASS.md`](CHECKOUT_COMPASS.md) records authority, invariants,
decisions, evidence, and open gates. Preserve the release history in the
existing Public Auto-Generate plan and compass.

## Checkout and payment model

- Persist an editable checkout draft with a stable reference, hashed guest
  possession token, session binding, owner reference, encrypted instruction
  and price snapshots, selected funding method, and encrypted visitor mobile.
  A draft has no payment obligation. Place it once by linking one existing
  On-Demand Issuance Funding Order; that order and its Funding Intent and
  Settlement remain the financial source of truth.
- The public GET remains read-only. Existing Turnstile, server-owned Commercial
  Principal, amount limits, instruction allowlist, and idempotency protect
  placement. Pause blocks new placement, while existing checkout, payment,
  issuance, retry, and refund recovery remain accessible.
- Require a valid Philippine mobile before **the server returns** bank-transfer
  instructions. Save it as visitor supplied, not verified identity. QR Ph may
  proceed without typed mobile; valid payer details from confirmed provider
  evidence may enrich Contact. An unpaid order may switch between QR Ph and
  bank transfer without creating another funding obligation. A switch to bank
  transfer still requires the mobile gate. Pay Code funding remains visible
  but unavailable until cycle and settlement safeguards have their own proof.
- After confirmed settlement, link or create a Contact using normalized mobile
  and explicit provenance. Keep visitor mobile, provider sender/account, and
  eventual claimant as separate roles. Never merge an existing profile or
  reveal saved KYC data merely because a visitor typed its mobile.
- The existing funded-order resume job calls `GeneratePayCode`. Its guarded
  issued transition attaches the voucher to the Checkout. The voucher package
  post-generation and post-redemption pipelines retain their current roles;
  a long-lived payment wait is a persisted Checkout state, not a synchronous
  pipeline step.
- A paid issuance failure retains the same order. Guarded retry and a manual
  refund case are mutually exclusive paths to disposition. Refund cases
  reference settled evidence, amount, reason, external return evidence, and
  operator action. The console records a refund performed outside x-change;
  it never initiates a bank return in this phase.

## Buyer and console interfaces

- Continue `/x/auto-generate` for composition. Add guest checkout detail and
  method/mobile mutations under its existing access boundary. All customer
  mutations require the session-bound possession token. Retain existing
  funding-order and signed recovery URLs during adoption.
- `/x/checkout` is the owner console. An expiring, revocable stakeholder
  capability grants read-only counts, filters, transaction rows, and event
  timelines. An owner console session can also request a guarded issuance
  retry and open or dispose a manual refund case. The public-request
  Commercial Principal user resolver does not authenticate a console actor.
- The beta owner session uses a shared password from deployment secrets.
  Production fails closed if it is missing or equals `password`; that literal
  value is permitted only in local/test. Throttle unlock attempts, bind the
  session to the commissioned owner, require password reconfirmation for
  refund disposition, and audit action time, session, and request context.
  Replace shared access with named administrator identity when the cockpit
  exists. Stakeholder links never authorize mutations.
- Console status comes from Checkout, funding order, Settlement, refund case,
  and append-only events. Show masked Contact, method, expected/settled
  amount, last activity, status, attention reason, and timeline. Use database
  truth for payment state; Horizon is a worker-health diagnostic only.
- Backfill existing public funding orders idempotently into Checkout records
  without replaying payment, issuing another voucher, or changing guest access.

## Verification and rollout

Test server-side instruction disclosure, mobile normalization and Contact
association, method switching, duplicate evidence, pause and resumption,
exactly-one issuance, and retry/refund exclusion. Test anonymous denial,
viewer read-only scope and expiry, owner password policy, principal scoping,
audit records, and totals against settled evidence. Verify the browser flow
and a pinned host deployment before enabling new public orders or sharing the
owner console. Authenticated cockpit checkout and Pay Code funding are later
phases.

## Local implementation and operator commands

The package implementation now has a session-bound, seven-day editable draft,
one placed Checkout per funding order, a server-side bank mobile gate, an
asynchronous Contact association after confirmed settlement, guarded refund
case tracking, and the scoped owner/viewer console. The console reads database
payment evidence; a typed mobile never authorizes an existing Contact profile.

Set `XCHANGE_CHECKOUT_OWNER_PASSWORD` as a deployment secret. In production,
the console refuses a missing password, `password`, or a password shorter than
16 characters. Local/test uses `password` by default. `GET /x/checkout` shows
only the password gate until unlocked. Owner actions are session-bound and
audited. A viewer URL grants only read access and expires after at most 30 days.

After migrating, run `php artisan x-change:checkout:backfill-public --dry-run`
then `php artisan x-change:checkout:backfill-public`. The command links existing
public orders idempotently; it does not replay funding or issuance. Create a
viewer link with `php artisan x-change:checkout:viewer-link "Stakeholder name"
--days=7`; list IDs with `--list`, and revoke with `--revoke=ID`. Treat the
printed link as a secret. The owner console records an external refund and a
separate Treasury reconciliation reference; it does not send money.

Package feature tests, the existing public and on-demand funding suites,
frontend component tests, and an isolated host Vite build passed locally.
Host-wide TypeScript checking still reports existing errors in unrelated
published components; the new Checkout console has no reported type error.
The testing deployment, migration/backfill, and paid QR Ph and Bank Transfer
journeys passed. A real external refund with Treasury reconciliation remains
the release gate before x-PayOut beta adoption.

## Testing-instance gate record — 2026-10-11

The `x-change-testing/testing` Laravel Cloud environment deployed host commit
`7eadbc45` (`feat/checkout-testing`) with x-change package commit `38f3d650`
(`feat/checkout`). The Cloud deployment `depl-a2f3c8ac-e538-4ff1-810e-d9b846cf7178`
succeeded. The Checkout migration ran, and the dry run counted eight existing
public orders before the idempotent backfill inspected all eight. The guarded
commissioning adoption renewed the stale installation manifest, and the strict
doctor passed. The testing-only owner password is a strong Laravel Cloud secret.

Browser acceptance confirmed the locked console reveals no monitor data, the
owner password unlocks it, and the monitor lists eight backfilled Checkouts.
It exposed one expired order with a verified late PHP 40.01 payment and no Pay
Code. The corrected monitor now counts this as attention and offers an auditable
manual refund case without an issuance retry. Focused PHP tests cover the
refund case through external-return recording and Treasury reconciliation, and
reject a refund case without settlement. The browser shows the real case as
eligible; no external refund has been claimed or recorded for it.

The first live viewer-link command exposed an omitted owner scope on insert;
the command now writes the commissioned owner type and ID explicitly. A
one-day testing link opened the same eight-row monitor in read-only mode,
offered no retry or refund action, and returned HTTP 404 after revocation.
The generated URL used HTTPS. Neither console nor public editor showed recent
browser JavaScript errors.

Testing has public issuance and fixed QR Ph enabled, Turnstile disabled, one
configured database queue worker for funding and issuance, and the scheduler
enabled. At the read-only checkpoint there were zero queued or failed jobs.
The public editor quoted PHP 40.00 total for a PHP 25.00 Pay Code. Paid QR Ph
and Bank Transfer journeys later passed. A real external refund with Treasury
reconciliation remains open. x-PayOut production has not adopted this package
branch.

An isolated x-PayOut beta.81 worktree loaded the current Checkout package,
registered all eight `/x/checkout` routes, published the package build assets,
and completed a production Vite build under a local deployment profile. This
is a compatibility preflight, not a production release or paid-journey proof.

## Paid testing acceptance — 2026-10-11

QR Ph order `01M4M71N44WZAPT2P1SSNDTWW5` settled the exact PHP 40.01
and issued Pay Code `ASM3` once. Bank Transfer order
`01M4M74NDSCHZR1XRSNDC10212` required a valid visitor mobile before showing
the NetBank instructions, settled the exact PHP 42.00, and issued Pay Code
`GYAG` once. Each order has one Settlement and one Checkout. Both Checkouts
link to the same Contact; the QR Ph association is provider reported and the
Bank Transfer association is visitor supplied. The owner console shows the
Bank Transfer method, masked Contact, expected and settled amount, and issued
status. The Pay Code stamp and console were verified in Chrome.

The first QR Ph settlement exposed a NetBank adapter gap: exact-payment
verification omitted sender identity even though the provider returned a
sender name, wallet source account, and institution. emi-netbank commit
`5e21f3a` preserves those fields; x-change commit `3a739d68` fixes an owner
console query mutation and permits guarded Contact recovery against the same
settled provider transaction, funding address, amount, and currency. NetBank
had changed the transaction payload hash after settlement, so the recovery
guard uses stable provider fields. The QR Checkout was reverified and linked
to the Contact without changing its issued Pay Code. Focused tests passed:
47 emi-netbank tests and 15 Checkout tests. Testing host commit `bf953041`
pins both package fixes, and Laravel Cloud deployment
`depl-a2f3d285-a1cb-4f18-a6e7-c288ffd6cb68` succeeded.

The real manual-refund return and Treasury reconciliation gate remains open.
x-PayOut production still has the prior package release and paused new public
issuance.
