# X-Change Principals, Accounts, and Human Onboarding

This is the canonical onboarding and account-ownership guide for an x-change
host. Read it before commissioning a new host, defining a commercial seller,
or issuing Maker and Checker invitations.

The short rule is:

> The **System Principal** operates x-change. The **Commercial Principal** owns
> earned service fees. Named human **Makers** and **Checkers** govern
> consequential changes.

These are different responsibilities even when one legal organization controls
the complete beta deployment.

## Canonical vocabulary

| Canonical term | Definition | Never use as a synonym |
| --- | --- | --- |
| **System Principal** | Non-human runtime identity used for commissioning and narrowly authorized automation | System User, administrator, revenue owner |
| **System Operations Account** | Account for System Capital, commissioning reserves, and operational clearing | System Wallet, customer balance |
| **Commercial Principal** | Legal entity that sells the priced service and is entitled to the resulting fee | Service Principal, System Principal |
| **Commercial Revenue Account** | Account for recognized and collected service fees owned by the Commercial Principal | Revenue Wallet, Product Wallet, Client Funds |
| **Maker** | Named human who proposes a governed action | System Principal, automatic approver |
| **Checker** | Different named human who independently approves a governed action | System Principal, Maker's second role |
| **Account holder** | Person or organization with Client Funds and permission to issue according to policy | Commercial Principal by default |

In identity and access management, “service principal” commonly means a
machine identity. x-change therefore does not use that term for the legal
seller. Use **Commercial Principal**.

## Principal, Account, Position, and User are different

- A **Principal** is an actor or legal owner.
- An **Account** groups financial ownership and authority for a principal.
- A **Treasury Position** classifies an amount inside an Account.
- A **User** is an authenticated human with contact and login attributes.

The relationships are:

```text
System Principal
└── System Operations Account
    ├── System Capital
    ├── Commissioning Reserve
    └── Operational Clearing

Commercial Principal
└── Commercial Revenue Account
    ├── Accrued Service Fees
    ├── Collected Service Fees
    ├── Revenue Available for Sweep
    └── Reversed or Refunded Fees

Human users
├── Maker
├── Checker
└── Account holders
```

This is one auditable x-change financial model with separate Accounts and
Positions. It is not two unrelated ledgers, and the System Principal does not
economically own the Commercial Revenue Account.

## What commissioning creates

Every commissioned host requires:

1. one stable, non-interactive System Principal;
2. one System Operations Account;
3. provider and Treasury topology that passes strict readiness; and
4. no fabricated human login for the System Principal.

A host that enables customer charging additionally requires:

1. a configured Commercial Principal representing the legal seller;
2. a Commercial Revenue Account separated from System Operations and Client
   Funds;
3. an approved pricing and fee-recognition policy;
4. invoicing and tax configuration appropriate to the host; and
5. fail-closed commercial readiness before any fee is charged.

For the shared 3neti beta, the Commercial Principal is:

```text
3NETI RESEARCH AND DEVELOPMENT OPC
```

A bank or EMI that operates its own host normally supplies its own Commercial
Principal. A separate 3neti subscription or implementation fee is a separate
commercial relationship and must not be blended into the host's transaction
principal.

Commissioning may mint Maker and Checker invitation Pay Codes after the
non-human boundaries are ready. The invitations create human seats; they do
not retroactively create the System or Commercial Principal.

## Contact and authentication rules

Neither non-human principal requires a personal email address or mobile
number.

| Identity | Interactive login | Personal email/mobile | Permitted contact data |
| --- | --- | --- | --- |
| System Principal | No | No | Machine identifier, authorization reference, runtime credentials |
| Commercial Principal | No | No | Registered name, TIN, registered address, billing and operations contacts |
| Maker/Checker | Yes | Yes, according to onboarding policy | Verified human identity and notification channels |

An organizational billing email or operations mobile may be recorded as a
contact channel for the Commercial Principal. It must not be presented as a
human identity or used to make the principal interactively claim a Pay Code.

The current Laravel host adapter resolves the System Principal through the host
`User` model and therefore uses a deployment-specific, non-personal technical
email as its stable identifier. That compatibility identifier is not a mailbox,
notification destination, or permission to log in. A future dedicated
principal model may remove that adapter requirement without changing the
canonical identity boundary.

## Issuance authority

The System Principal cannot use ordinary product issuance as though it were an
Account holder. Its exceptional issuance is restricted to package-defined,
journaled operations such as commissioning invitations, continuity, or
recovery instructions.

The Commercial Principal may be the legal issuer for an approved commercial
journey, invoice, or payment request. That authority does not permit it to
spend Client Funds or bypass liquidity, pricing, invoice, or execution gates.

Ordinary Account holders issue Pay Codes only within their Client Funds,
Issuance Capacity, and policy boundaries.

## Transaction fees and revenue ownership

“The instruction is the transaction” does not mean that the Pay Code or its
execution Account becomes the owner of revenue. Voucher instructions may
declare the seller, fee, recognition event, and settlement destination. The
authoritative financial outcome must still classify ownership correctly.

```text
Account holder Client Funds
├── principal → beneficiary or settlement purpose
└── service fee → Commercial Revenue Account
```

The principal is never commercial revenue. A transaction fee becomes revenue
only at its configured authoritative recognition event. A pending fee may be
reserved; a failed or cancelled transaction releases that reservation. A
successfully recognized fee is attributed to the Commercial Principal and may
later be swept under governed authority.

The System Operations Account, Pay Code reserve, Product Wallet, provider
inventory, and Client Funds are not substitutes for the Commercial Revenue
Account.

## Maker and Checker onboarding

Maker and Checker are human governance roles, not installation dependencies
for an immutable non-commercial baseline.

Commissioning may finish and unchanged baseline operations may run before the
invitations are claimed. Governed maintenance remains locked until:

1. the Maker invitation is claimed by a named human;
2. the Checker invitation is claimed by a different named human; and
3. both humans receive explicit authority for the relevant domain.

Maker/Checker authority applies to pricing revisions, commercial policy,
exceptional invoice correction, revenue sweeps, Treasury changes, production
credentials, provisioning, and other consequential operations. Routine
execution under an already approved policy does not require a new approval for
every transaction.

The System Principal cannot be a Maker or Checker. The Commercial Principal is
a legal owner and cannot stand in for either human role.

See the [Commissioning Maker/Checker Doctrine](./docs/governance/COMMISSIONING_MAKER_CHECKER_DOCTRINE.md)
for the full separation-of-duty rules.

## Shared beta commissioning sequence

```text
Install package
    ↓
Provision System Principal and System Operations Account
    ↓
Bind 3neti Commercial Principal and Commercial Revenue Account
    ↓
Verify provider, Treasury, pricing, charging, and invoice readiness
    ↓
Mint optional Maker and Checker invitation Pay Codes
    ↓
Complete strict commissioning
    ↓
Humans claim invitations
    ↓
Grant domain-specific Maker and Checker authority
```

The same human administrator may initially have operational access to inspect
both non-human Accounts. That does not merge their ownership or balances.

## Runtime implementation and compatibility

The runtime provisions a non-interactive System Principal and, when billing is
enabled, a package-owned non-login Commercial Principal with its own zero-balance
Commercial Revenue Account. Strict doctor and commissioning state fail closed
when either required principal Account is missing or conflicts with its
configuration. Informational billing mode does not require a Commercial
Principal Account.

System Treasury Positions, including the existing commercial classification
Positions, remain owned by the System Principal in this compatibility gate.
Some persistent keys and commands predate this terminology and still contain
`system_account`, `system_principal`, or `commercial_revenue`. Those identifiers
are compatibility contracts and must not be renamed by documentation-only
work.

Provisioning the Commercial Principal does not migrate historical balances or
silently redirect commercial postings. The commercial revenue Position remains
an accounting boundary inside the established Treasury topology until a later
controlled routing migration is approved. Customer charging must remain fail-closed
unless the legal seller, Commercial Revenue Account ownership,
pricing, fee recognition, invoice authority, and routing disposition are
explicitly resolved.

New product copy, architecture documentation, and APIs should use the canonical
terms in this guide. Compatibility identifiers may be replaced only through a
separately tested migration.

## Commissioning acceptance checklist

- [ ] System Principal exists exactly once and is non-interactive.
- [ ] System Operations Account exists exactly once.
- [ ] No personal email or mobile was fabricated for either non-human principal.
- [ ] Commercial Principal is configured when customer charging is enabled.
- [ ] Commercial Revenue Account is distinct from System Operations and Client Funds.
- [ ] Transaction principal and service fees are independently classified.
- [ ] Fee-recognition and failure-release rules are explicit.
- [ ] Invoice and tax readiness fail closed before charging.
- [ ] Maker and Checker invitations, if configured, identify different humans.
- [ ] Governed maintenance remains unavailable until authority is granted.
- [ ] Strict doctor and financial control checks pass.

## Documentation map

- [Getting Started](./GETTING_STARTED.md) — install and first commissioning.
- [Deployment](./DEPLOYMENT.md) — repeatable deployment and recovery.
- [Bank Operating Model](./docs/commercial-governance/BANK_OPERATING_MODEL.md) — operator and commercial controls.
- [Commercial Governance](./docs/commercial-governance/README.md) — pricing, sales, settlement, and commissions.
- [Commissioning Maker/Checker Doctrine](./docs/governance/COMMISSIONING_MAKER_CHECKER_DOCTRINE.md) — human governance boundary.
- [Terminology Lock](./docs/terminology-lock.md) — package-wide canonical product vocabulary.
