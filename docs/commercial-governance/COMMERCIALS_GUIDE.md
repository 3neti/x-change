# X-Change Commercials Guide

## Purpose

Commercials is the governed subsystem that answers four separate questions for every Pay Code or Account Funding instruction:

1. What did the institution charge?
2. Which price list and legal terms authorized that charge?
3. How was the charge allocated among provider cost, product revenue, partner commission, and residual commercial revenue?
4. Which allocations have merely been recognized, and which have actually been settled in cash?

It is not a generic shopping cart and it is not a shortcut around Treasury. A Commercial Sale records the accepted price and waterfall at issuance. Treasury Positions record what the institution owes or has earned. Separate evidence-controlled operations settle provider costs and partner commissions.

## Core vocabulary

| Term | Meaning |
|---|---|
| Commercial Offering | A versioned Price List, Waterfall, Attribution Policy, and Legal Trace approved for a profile such as `pay_code` or `account_funding`. |
| Commercial Sale | The immutable acceptance of one governed quote. It retains the Offering snapshot even after later prices change. |
| Waterfall | The ordered allocation of the accepted charge. Every centavo must be conserved. |
| Provider Cost Payable | The amount allocated for an external bank or EMI cost. Allocation is not proof that the provider has collected it. |
| Product Revenue | Revenue attributed to the instruction product or service. |
| Partner Commission Payable | An amount earned by a governed Commercial Partner but not necessarily paid yet. |
| Commercial Revenue | The residual commercial allocation after the preceding rules. |
| Provider Inventory | Recognized money held at a bank or EMI. It changes only when authoritative evidence establishes a corresponding cash movement. |
| Client Funds | The Account holder's institution-positioned funds. Commercial charges debit Client Funds through Treasury, not through an arbitrary balance edit. |

The Cockpit avoids presenting x-change as an electronic-money wallet. “Account,” “Client Funds,” “Position,” “Payable,” “Inventory,” and “settlement” are deliberate terms aligned with the package legal profiles.

## Actors and separation of duties

The non-interactive System Principal owns institutional Positions and establishes the immutable commissioning baseline. It cannot act as a human maker or checker.

A maker may prepare Commercial Offering revisions, Partner records, payout destinations, provider-cost evidence, and commission-payment requests. A different checker independently approves the changes or payments that require dual control. An executor may submit an already approved commission payment to its provider. Capabilities are explicit, time-bound operator authorizations rather than inferred administrator status.

An institution can commission x-change before naming maker and checker operators:

- `bootstrap_immutable` activates the package baseline and permits issuance;
- prices remain locked until distinct maker and checker authorities exist;
- `maker_checker_from_start` persists the baseline but keeps it inactive until institutional approval.

## Commercial Offering lifecycle

```text
Package baseline
    -> provisioned with package, manifest, snapshot, and hash
    -> activated by commissioning authority
    -> issuance available; changes locked

Named maker + different named checker
    -> draft
    -> submit
    -> approve and publish
    -> explicit activation
    -> new issuance resolves the new snapshot
```

Publication makes a reviewed Offering eligible. Activation is the separate controlled instant when new work starts using it. Existing Pay Codes and Commercial Sales retain the Offering version and hash accepted when they were created.

## Sale and waterfall flow

For an illustrative ₱25.00 instruction charge:

| Stage | Client Funds | Commercial Clearing | Provider Cost | Product Revenue | Partner Commission | Commercial Revenue |
|---|---:|---:|---:|---:|---:|---:|
| Before sale | ₱25.00 | ₱0.00 | ₱0.00 | ₱0.00 | ₱0.00 | ₱0.00 |
| Charge | ₱0.00 | ₱25.00 | ₱0.00 | ₱0.00 | ₱0.00 | ₱0.00 |
| Waterfall posted | ₱0.00 | ₱0.00 | ₱10.00 | ₱8.00 | ₱2.00 | ₱5.00 |

The example is illustrative; the active Offering is always the source of truth. The posting is atomic and idempotent. Replaying the same accepted sale produces no duplicate charge or allocation. Reusing an idempotency reference with different content fails closed.

Commercial allocations do not move provider cash. They classify value within Treasury Positions:

- Provider-cost settlement requires exact authoritative evidence matching provider, connection, currency, period, and amount.
- Partner commission payment requires a governed Partner, approved destination, maker request, independent checker approval, explicit submission, and authoritative reconciliation.
- Product Revenue and Commercial Revenue remain institutional Positions until a later governed accounting operation uses them.

## Commercial Partners

A Commercial Partner is an institutional payee and attribution record; it need not be an x-change login account. Legal terms and payout destinations are versioned independently.

```text
Partner terms:       draft -> submitted -> independently approved -> active
Payout destination: draft -> submitted -> independently approved -> effective
```

Accepted sales bind commission to the exact Partner and Partner revision that was effective at acceptance. Later changes cannot rewrite prior attribution. Destination details are encrypted at rest and only masked summaries appear in Cockpit and lifecycle reports.

## Provider-cost settlement

Provider cost starts as an allocation to `Provider Cost Payable`. It is settled only when an authorized operator records authoritative evidence that exactly matches the eligible allocations.

```text
provider statement or debit evidence
    -> match provider + connection + currency + period
    -> compare observed and expected amount
    -> exact match: settle payable once
    -> mismatch or ambiguity: review required; no accounting mutation
```

Evidence may be recorded without changing accounting. This is intentional: a mismatch is an operational fact, not authority to invent an adjustment.

## Partner commission payment

Commission is first earned and recognized as `Partner Commission Payable`. Payment is a later operational transaction.

```text
earned allocations
    -> maker creates aggregate payout request
    -> different checker approves
    -> executor explicitly submits
    -> provider reports pending
    -> scheduled or operator reconciliation obtains authoritative result
       -> completed: decrease Partner Commission Payable and Provider Inventory once
       -> rejected: retain payable and Inventory; preserve attempt evidence
```

The scheduled reconciliation job uses the `x-change-funding` queue. Issuing a Pay Code never automatically sends a partner payment.

## Journal and audit evidence

Every important transition writes append-only x-journal evidence with stable correlation and idempotency references. The audit trail covers:

- Offering baseline, publication, activation, retirement, and hash provenance;
- Partner and payout-destination revisions;
- accepted Commercial Sales, charges, and every waterfall allocation;
- provider-cost evidence, review, and settlement;
- commission request, approval, provider attempts, reconciliation, rejection, retry, and settlement;
- reversals and accounting attestations.

Sensitive credentials, raw provider payloads, and unmasked destinations do not belong in Cockpit summaries, lifecycle JSON, logs, or journal metadata.

## Cockpit operating surfaces

The System Principal and authorized operators use **Commercial Controls**:

- **Offerings** shows active pricing provenance and governed revisions.
- **Partners** administers legal attribution and independently approved payout destinations.
- **Provider Costs** records and reconciles external cost evidence.
- **Commissions** groups earned allocations and carries payment through maker-checker and provider reconciliation.
- **Activity** exposes sanitized, append-only operational history.

Ordinary Account holders do not see institutional operator identities, payout destinations, provider liquidity, or Commercial Controls.

## Commissioning and runtime

Commissioning reports the governance mode, baseline origin, active Offering profiles, maker/checker readiness, and whether issuance or price changes are available. The package baseline must match its stored snapshot hash; a conflict stops installation rather than overwriting evidence.

Required operational responsibilities include the `x-change-funding` queue for scheduled commercial settlement reconciliation, the other named x-change queues documented by commissioning, and Laravel Scheduler. Environment files select deployment behavior; they never contain fabricated approvals.

See [Commissioning](./COMMISSIONING.md), [Bank Operating Model](./BANK_OPERATING_MODEL.md), [Offering Runbook](./OFFERING_RUNBOOK.md), and [Settlement Operations](./SETTLEMENT_OPERATIONS.md) for commands and recovery procedures.

## Rollback-only lifecycle simulation

The `commercial_operations_simulation` lifecycle scenario demonstrates the machinery without calling NetBank, Paynamics, or another live provider and without leaving durable records:

```bash
php artisan xchange:lifecycle:run commercial_operations_simulation \
  --issuer=5 \
  --maker=15 \
  --checker=16 \
  --json
```

The three people must resolve through the configured host user model. Maker and checker must differ and neither may be the System Principal. The scenario temporarily grants only the capabilities required by the demonstration inside its rollback transaction.

The report covers Offering provenance, Partner approval, masked destination readiness, sale and waterfall, exact provider-cost settlement, commission request/approval/submission/reconciliation, Treasury Position changes, simulated provider call counts, journal evidence, idempotency, accounting invariants, and confirmed rollback.

Safety properties:

- allowed only when explicitly enabled in `local` or `testing`;
- uses production Commercial actions and a simulation-only in-memory provider boundary;
- makes no HTTP request and cannot move real money;
- never emits a raw account number, token, credential, or provider payload;
- rolls back Commercial, Treasury, authorization, and journal records before returning;
- is operational validation, not authority to settle a production obligation.

## Troubleshooting

| Observation | Meaning / response |
|---|---|
| Issuance unavailable | Confirm an active Offering exists for the profile and commissioning is ready. |
| Price changes locked | Authorize distinct maker and checker operators; do not use the System Principal. |
| Provider-cost batch is `review_required` | Reconcile the mismatch from authoritative evidence. Do not edit the amount to force a match. |
| Commission remains payable | Check maker request, independent approval, effective destination, provider submission, worker, and reconciliation evidence. |
| Provider rejected commission payout | Correct the destination through a new approved revision; prior attempt and payable remain intact. |
| Lifecycle simulation refuses to run | Use a local/testing environment, enable the scenario, supply distinct maker/checker IDs, and run migrations. |
| Accounting attestation fails | Stop settlement and investigate Inventory, Position, allocation, and journal evidence before correction. |

## Acceptance checklist

- Commissioning identifies the active Offering and immutable provenance.
- A new Pay Code resolves the expected Price List and Waterfall.
- The accepted Commercial Sale conserves every centavo.
- Provider cost enters a payable before evidence-controlled settlement.
- Partner commission binds to an approved Partner revision and masked destination.
- Maker and checker are different named operators and neither is the System Principal.
- Completed partner payment decreases payable and Provider Inventory exactly once.
- Rejected payment retains payable and Provider Inventory.
- Journal evidence is complete and sensitive values are redacted.
- Lifecycle simulation reports `external_provider_calls=false`, `persisted=false`, and `rollback_completed=true`.
