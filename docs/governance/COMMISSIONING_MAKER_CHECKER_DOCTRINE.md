# Commissioning Maker/Checker Doctrine

## Scope

This doctrine belongs to `3neti/x-change`.

It defines why an x-change host may mint Maker and Checker onboarding
invitations during commissioning, what those invitations do, and what must
remain locked until named humans complete onboarding and receive explicit
authority.

Host applications such as x-PayOut, BPLS, payroll, merchant, or bank-specific
deployments may customize labels, workspace routes, and onboarding copy. They
should not weaken the package-level governance model.

## Core rule

Maker and Checker onboarding is optional for completing cloud commissioning,
but required for governed maintenance.

An x-change host may complete commissioning and become operational on an
immutable package baseline even if the Maker and Checker invitations have not
yet been claimed. This allows a clean cloud instance to pass readiness gates,
recognize provider readiness, activate package-defined pricing and waterfall
rules, and issue using unchanged baseline rules.

However, no institution-specific governed maintenance may proceed until:

- one Maker has completed onboarding;
- one distinct Checker has completed onboarding; and
- both are explicitly authorized for the relevant governed domain.

In short:

> Commissioning can finish without onboarded Maker/Checker humans. Maintenance
> cannot begin without them.

## Why this exists

Commissioning has two competing needs:

1. A clean host must be usable immediately after installation.
2. Institution-specific financial rules must not be changed by an anonymous,
   unaudited, or non-human actor.

x-change resolves this by separating baseline activation from governed
maintenance:

- **Baseline activation** is package-owned, immutable, and recorded as
  commissioning evidence. It does not pretend that a human approved a custom
  price or payout rule.
- **Governed maintenance** is institution-owned and requires named human
  accountability with separation of duties.

## What commissioning may do without claimed Maker/Checker accounts

The following may complete before the Maker and Checker invitations are
claimed:

- cloud deployment;
- strict pre-commission readiness;
- provider readiness checks;
- System Principal provisioning;
- immutable package baseline activation;
- Maker and Checker invitation Pay Code minting;
- funded onboarding reservation, if configured;
- final strict doctor, if all other gates pass; and
- issuance using unchanged baseline pricing and waterfall.

These operations establish the host's starting state. They do not authorize a
human-authored maintenance change.

## What requires claimed Maker/Checker accounts

The following must remain unavailable until distinct onboarded humans exist and
hold the right capability grants:

- pricing maintenance;
- waterfall maintenance;
- Commercial Offering revisions;
- Commercial Partner changes;
- Partner payout destination changes;
- commission payout requests and approvals;
- Treasury reserve movements;
- account funding grants;
- funding classification and reconciliation approvals;
- provider settlement and recovery approvals;
- production Partner API client or scope activation;
- provisioning authority changes;
- campaign or payroll batch approval; and
- future institution-specific policy changes.

## What Maker/Checker onboarding creates

The Maker onboarding Pay Code creates a named human Maker account for the host
workspace.

The Checker onboarding Pay Code creates a named human Checker account for the
host workspace.

After claiming, the users can enter the host workspace and may receive the
configured funded onboarding amount, such as:

> ₱100.00 available for instructions

The onboarding invitation is a seat-creation and identity handoff mechanism. It
is not a blanket administrator grant.

## What onboarding does not automatically grant

A claimed Maker or Checker invitation does not automatically mean the user can:

- change pricing;
- change waterfalls;
- approve commissions;
- move Treasury reserves;
- create production API credentials;
- approve payroll batches; or
- change institution policy.

Those powers require separate, explicit capability grants.

## Authority model

Authority is capability-scoped. x-change maintains distinct maker/checker
pairs for governed domains such as:

- Commercial controls;
- Treasury controls;
- Provisioning controls;
- Partner API controls; and
- campaign or payroll authorization.

A host may appoint the same organizational roles across domains, but authority
must still be granted per capability. A person must not hold both sides of the
same maker/checker control pair.

## Separation-of-duty rules

The following rules must hold:

- The System Principal cannot be Maker or Checker.
- Maker and Checker must be different human accounts for the same control
  pair.
- A Checker cannot approve their own Maker action.
- Authority must be capability-scoped.
- Authority should carry a stable institutional reference, such as a board
  resolution, deployment approval, policy reference, or delegated authority
  record.
- Governed changes must be journaled.

## Operating phases

### Phase 1: Operational baseline

After commissioning:

- the System Principal exists;
- provider readiness is verified;
- immutable baseline pricing and waterfall are active;
- Maker and Checker invitation Pay Codes are minted;
- issuance can proceed using baseline rules; and
- governed maintenance remains locked.

### Phase 2: Governed maintenance ready

After both invitations are claimed:

- Maker is onboarded;
- Checker is onboarded;
- they are distinct human accounts;
- domain-specific authorities may be granted; and
- governed maintenance becomes available according to granted capabilities.

## Package responsibility

x-change owns:

- the commissioning baseline;
- the Maker/Checker invitation shape;
- the governed capability model;
- separation-of-duty rules;
- doctor and readiness checks;
- journal and audit expectations; and
- fail-closed behavior for governed maintenance.

## Host responsibility

A consuming host should:

- expose or relay the Maker and Checker onboarding invitations;
- ensure the invited humans can complete onboarding;
- preserve the separation between onboarding and authority;
- grant domain-specific capabilities only after onboarding;
- keep the System Principal non-human and non-approving; and
- prevent one person from holding both sides of the same maker/checker pair.

## Policy statement

An x-change host may operate on the immutable package baseline after
commissioning. But no institution-specific maintenance of pricing, waterfalls,
commissions, Treasury, provisioning, payout, production API, campaign, or policy
settings may proceed until a Maker and a distinct Checker are onboarded and
explicitly authorized for the relevant governed domain.
