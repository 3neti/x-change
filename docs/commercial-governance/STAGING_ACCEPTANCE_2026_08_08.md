# Commercial Governance Staging Acceptance — 2026-08-08

## Scope

This record closes the controlled activation and accounting acceptance for the first governed Pay Code Commercial Offering in the Laravel Cloud `testing` environment. It does not authorize production use or a real provider payout.

## Release evidence

- x-change release: `v1.0.0-beta.102`
- Host commit: `8f73b404`
- Cloud deployment: `depl-a27255e9-2f62-4da0-a6a3-1cd8396ba709`
- Deployment result: succeeded
- Strict commissioning doctor: passed
- Commercial accounting attestation: ready, with no issues

The first activation attempt exposed a PostgreSQL portability defect: an aggregate version query was combined with `FOR UPDATE`. No draft was created. Release `v1.0.0-beta.102` changed version allocation to lock the latest offering row directly and added a regression test.

## Authority and activation

- One named Account holder received `commercial.offerings.manage` authority.
- A different named Account holder received `commercial.offerings.approve` authority.
- The System Principal received neither authority.
- Maker count: 1
- Checker count: 1
- Separation check: passed

The maker created and submitted Pay Code Commercial Offering version 2. The checker independently published it, and an explicit activation made it effective for future Pay Code pricing.

- Profile: `pay_code`
- Active version: 2
- Origin: `maker_checker_revision`
- Snapshot hash: `ee030f6c87ead2d9bf69d97fc1a4708179bd6eb136f77eeccd8cbbcb3f73d01c`

The version 2 economic terms intentionally match the installation baseline. This acceptance proves governance and immutable provenance without introducing an accidental price change. The `account_funding` profile remains independently active on its installation baseline.

## Issuance and immutable pricing

One unclaimed ₱1.00 Pay Code was issued with the provider call disabled by the lifecycle runner's `--no-claim` boundary.

- Pay Code value: ₱1.00
- Commercial charge: ₱15.00
- Account debit: ₱16.00
- Provider calls: none
- Commercial Offering version captured by the new sale: 2
- Earlier governed sales retaining version 1 snapshots: 6
- Legacy sales without an offering snapshot: 4

The accepted ₱15.00 charge followed this waterfall:

| Allocation | Amount |
|---|---:|
| Provider Cost Payable | ₱10.00 |
| Product Revenue | ₱3.00 |
| Commercial Revenue | ₱2.00 |
| Partner Commission | ₱0.00 — no attributed eligible partner |

## Accounting proof

After issuance, the Commercial read model and Treasury attestation reported:

| Position | Current | Lifetime Allocated | Settled/Paid | Difference |
|---|---:|---:|---:|---:|
| Provider Cost Payable | ₱110.00 | ₱110.00 | ₱0.00 | ₱0.00 |
| Product Revenue | ₱33.00 | ₱33.00 | ₱0.00 | ₱0.00 |
| Partner Commission Payable | ₱4.00 | ₱4.00 | ₱0.00 | ₱0.00 |
| Commercial Revenue | ₱98.20 | ₱98.20 | ₱0.00 | ₱0.00 |

- Posted Commercial Sales: 11
- Total accepted Commercial charges: ₱245.20
- Provider Inventory: ₱8,048.94
- Total Treasury Positions: ₱8,048.94
- Inventory-to-Position difference: ₱0.00
- Reversed sales: 0

The four governance journal events—drafted, submitted, published, and activated—were present. Browser acceptance confirmed that the protected Commercial Controls page displayed governed version 2, all four reconciled Commercial Positions, allocation history, and the latest sale. No application-origin browser error was observed.

## Operational interpretation

Commercial credits are not a generic wallet balance. They accumulate in explicit Treasury Positions according to the accepted sale waterfall. Provider costs and partner commissions remain liabilities until authoritative settlement or payout evidence is recorded. Product and Commercial Revenue remain system positions. Lifetime allocation totals are audit history; the current Position is the amount still held.

This acceptance does not settle provider costs, pay partner commissions, claim the test Pay Code, or move money through NetBank.
