# Commission Payout Runbook

## Purpose

This runbook explains how earned commercial commissions move from recognized
payable amounts into provider-submitted payouts.

Commission payout is not automatic by default. It is a governed operation that
requires Maker request, independent Checker approval, explicit live-gated
submission, and authoritative provider settlement evidence.

## Mental model

A commission has three different states:

1. **Earned**
   - A Pay Code or commercial transaction created a Commercial Sale.
   - The sale waterfall allocated part of the charge to a partner or recipient.
   - x-change records this as a Partner Commission Payable.
2. **Approved for payout**
   - A Maker groups payable commission lines into a payout request.
   - A Checker reviews and approves the request.
   - The payable is authorized for provider submission, but provider money has
     not necessarily moved yet.
3. **Settled**
   - The approved payout is submitted to the provider.
   - Provider evidence confirms completion.
   - Treasury positions and journal records are updated.

Recognition of a commission payable does not authorize money movement.

## Required actors

### Maker

The Maker prepares the payout request.

Required capability:

```text
commercial.commissions.request
```

The Maker may:

- view payable commission amounts;
- select eligible partner/payee;
- select provider connection and currency;
- select period or cutoff;
- prepare a payout batch;
- verify the masked payout destination; and
- submit the payout request for approval.

The Maker may not approve their own request.

### Checker

The Checker independently approves or rejects the payout request.

Required capability:

```text
commercial.commissions.approve
```

The Checker may:

- review payout amount;
- review partner/payee identity;
- review masked destination;
- review period or cutoff;
- confirm supporting evidence; and
- approve the payout batch.

The Checker must be a different human account from the Maker.

### Executor or provider worker

The executor submits an already approved payout to the provider.

Required capability:

```text
commercial.commissions.execute
```

This may be:

- a controlled operator action;
- a queue worker;
- a scheduled reconciliation job; or
- a controlled Artisan command.

The executor does not decide whether the payout should happen. It only executes
or reconciles an already approved payout.

## Prerequisites

Before commission payout can be requested:

- x-change is commissioned;
- the immutable baseline or governed Commercial Offering is active;
- Commercial Sales have been created;
- the waterfall includes a commission allocation;
- the partner or recipient designation is active;
- the partner payout destination is approved;
- Partner Commission Payable positions exist;
- Maker and Checker are onboarded;
- Maker and Checker have explicit commission capabilities;
- provider payout capability is configured;
- provider liquidity/readiness checks pass; and
- required queues/workers are running.

## Cockpit workflow

The Cockpit Commercial Controls **Operations** tab shows outstanding payable
amounts, evidence batches, payout batches, masked destinations, and lifecycle
status.

The protected routes behind this workflow are:

- `commercial/commission-payout-batches`
- `commercial/commission-payout-batches/{commissionPayoutBatch}/approvals`
- `commercial/commission-payout-batches/{commissionPayoutBatch}/submissions`
- `commercial/commission-payout-batches/{commissionPayoutBatch}/reconciliations`
- `commercial/commission-payout-batches/{commissionPayoutBatch}/retries`

Ordinary Account holders cannot access this workspace.

## Maker procedure

### Step 1: Open Commercial Controls

The Maker opens:

```text
Cockpit → Commercial Controls → Operations / Commissions
```

The page should show:

- payable partners;
- unpaid commission amounts;
- currency;
- period or cutoff;
- masked payout destination; and
- payout readiness status.

### Step 2: Select payout scope

The Maker chooses:

- partner or recipient;
- provider;
- provider connection;
- currency;
- period start;
- period end; and
- payable amount to include.

The system should prevent mixing incompatible payout groups.

A payout batch generally groups by:

```text
partner + provider + connection + currency + period
```

### Step 3: Review destination

The Maker sees only a masked destination, for example:

```text
GCash •••• 1987
Bank account •••• 2316
```

The full destination remains encrypted and is not casually exposed in Cockpit.

If the destination is missing, expired, unapproved, or superseded, the Maker
cannot proceed.

### Step 4: Create payout request

The Maker submits the request.

The system records:

- Maker identity;
- request timestamp;
- payout scope;
- amount;
- currency;
- partner/payee reference;
- masked destination summary;
- idempotency key; and
- supporting sale/allocation references.

At this point, no provider money should move yet.

Expected status:

```text
requested
```

## Checker procedure

### Step 1: Open pending payout request

The Checker opens:

```text
Cockpit → Commercial Controls → Operations / Commissions → Pending approvals
```

The Checker reviews:

- Maker identity;
- partner/payee;
- amount;
- currency;
- period;
- masked destination;
- source Commercial Sales;
- waterfall basis;
- prior payout history; and
- warnings or readiness issues.

### Step 2: Approve or reject

If correct, the Checker enters an institutional approval reference, for
example:

```text
board-resolution:2026-09-commercial-payouts
operations-approval:CP-2026-09-001
```

Then the Checker approves.

Expected status:

```text
approved
```

If rejected, the payable remains unpaid and the request is closed with a
reason. A corrected payout request can be created later.

## Provider submission

After approval, provider submission may be triggered by:

- a Cockpit action;
- a queue worker; or
- the controlled Artisan command.

Submission remains disabled unless:

```text
XCHANGE_COMMERCIAL_LIVE_PROVIDER_CALLS_ENABLED=true
```

The live command also requires:

```text
--confirm-live
```

Every provider submission must use a stable idempotency key so retries do not
duplicate payment.

Expected intermediate statuses include:

```text
approved
pending
settled
rejected
```

## Settlement confirmation

Provider confirmation is required before x-change treats the payout as settled.

Valid confirmation may come from:

- provider payout response;
- provider status polling;
- webhook or postback;
- reconciliation report; or
- provider transaction detail.

The system records:

- provider reference;
- provider timestamp;
- provider status;
- amount;
- currency;
- destination summary; and
- reconciliation evidence.

Only after authoritative completion should the payable position reduce and the
payout be treated as completed.

Expected final status:

```text
settled
```

## Failure handling

### Provider rejects the payout

If the provider rejects the payout:

- the payable remains outstanding;
- the failed attempt is journaled;
- the destination may be marked for review; and
- a new Maker request may be required if payout details change.

### Provider timeout

If submission times out:

- do not create a new payout blindly;
- poll or reconcile using the same provider reference or idempotency key; and
- mark the payout as uncertain until authoritative evidence is found.

### Destination is wrong

If the destination is wrong:

- reject or suspend the payout request;
- create a corrected destination revision;
- require maker/checker approval for the new destination; and
- create a new payout request after correction.

### Duplicate submission risk

Every provider submission must be idempotent.

Retries must reuse the same payout batch reference or idempotency key unless the
prior request is definitively failed and closed.

## Journal expectations

x-journal should capture:

- commission payable recognized;
- payout destination created;
- payout destination approved;
- payout request created by Maker;
- payout request rejected by Checker;
- payout request approved by Checker;
- provider submission queued;
- provider submission dispatched;
- provider submission failed;
- provider confirmation received;
- payout settled;
- payout superseded or cancelled; and
- reconciliation completed.

## Operator checklist

Before requesting payout:

- [ ] Partner is active.
- [ ] Destination is approved.
- [ ] Payable amount is non-zero.
- [ ] Currency is correct.
- [ ] Provider connection is correct.
- [ ] Period is correct.
- [ ] No duplicate pending payout exists.
- [ ] Maker is not the eventual Checker.

Before approving payout:

- [ ] Maker is identified.
- [ ] Partner/payee is correct.
- [ ] Amount reconciles to payable commission.
- [ ] Destination is approved and masked.
- [ ] Provider readiness is green.
- [ ] Approval reference is entered.
- [ ] Checker is not the Maker.

Before marking settled:

- [ ] Provider reference exists.
- [ ] Provider amount matches approved amount.
- [ ] Provider currency matches approved currency.
- [ ] Destination summary matches approved destination.
- [ ] Provider status is final success.
- [ ] Treasury position movement is recorded.
- [ ] Journal evidence is complete.

## CLI examples

Authorize the Maker:

```bash
php artisan x-change:commercial:authorize-operator maker@example.test \
  --column=email \
  --capability=commercial.commissions.request \
  --authorization-reference=delegated-authority:commission-maker
```

Authorize the Checker:

```bash
php artisan x-change:commercial:authorize-operator checker@example.test \
  --column=email \
  --capability=commercial.commissions.approve \
  --authorization-reference=delegated-authority:commission-checker
```

Authorize an execution operator:

```bash
php artisan x-change:commercial:authorize-operator executor@example.test \
  --column=email \
  --capability=commercial.commissions.execute \
  --authorization-reference=delegated-authority:commission-executor
```

Request payout as Maker:

```bash
php artisan x-change:commercial:commission:request maker@example.test \
  --column=email \
  --reference=commission-payout:2026-09:PARTNER-001 \
  --partner=PARTNER-001 \
  --provider=netbank \
  --connection=netbank-primary \
  --currency=PHP \
  --period-start=2026-09-01T00:00:00+08:00 \
  --period-end=2026-09-30T23:59:59+08:00 \
  --idempotency-key=commission-payout:2026-09:PARTNER-001 \
  --json
```

Approve payout as Checker:

```bash
php artisan x-change:commercial:commission:approve commission-payout:2026-09:PARTNER-001 checker@example.test \
  --column=email \
  --approval-reference=commission-approval:2026-09:PARTNER-001 \
  --json
```

Submit approved payout:

```bash
XCHANGE_COMMERCIAL_LIVE_PROVIDER_CALLS_ENABLED=true \
php artisan x-change:commercial:commission:submit commission-payout:2026-09:PARTNER-001 executor@example.test \
  --column=email \
  --idempotency-key=submission:commission-payout:2026-09:PARTNER-001 \
  --confirm-live \
  --json
```

Reconcile one payout:

```bash
php artisan x-change:commercial:commission:reconcile commission-payout:2026-09:PARTNER-001 executor@example.test \
  --column=email \
  --json
```

Queue reconciliation for pending payouts:

```bash
php artisan x-change:commercial:commission:reconcile-pending \
  --limit=50 \
  --json
```

## Policy statement

Commission payout is a governed settlement operation. Recognition of a
commission payable does not authorize money movement. A payout requires Maker
request, independent Checker approval, explicit provider submission, and
authoritative settlement evidence before Treasury positions and journal records
mark the commission as paid.
