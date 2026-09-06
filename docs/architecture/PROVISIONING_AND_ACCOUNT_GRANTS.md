# Provisioning and Treasury Account Grants

## Purpose

X-Change separates two operations that look similar in the user interface but
have different legal and accounting effects:

- **Provisioning** establishes who accepted an approved relationship or
  authority.
- **Account Grants** allocate already-reconciled institution-owned money to a
  recipient's Client Funds.

Neither operation impersonates the non-interactive System Principal. Named
human operators initiate and approve the governed records; the System
Principal executes only the exact approved envelope.

## Package boundaries

`3neti/x-provisioning` owns requests, immutable revisions, maker-checker
approval, opaque one-time offers, acceptance evidence, activation, and vacant
commissioning seats. It has no dependency on Voucher, Wallet, Treasury,
Passport, providers, or x-change.

`3neti/x-change` adapts an activated provisioning envelope into a particular
domain. Commercial, Treasury, and Partner API capabilities remain separate
adapters so that a generic provisioning claim cannot silently acquire broad
authority.

`3neti/wallet` owns the `institution_owned_funds` Treasury Position and the
permitted allocation from that Position to recipient `client_funds`.

## Commissioning seats

Commissioning may create vacant seats before the bank knows the human names:

```bash
php artisan x-change:provisioning:commission
```

This command is idempotent. A vacant seat is a requirement, not an authority.
It creates no user, secret, Pay Code, wallet balance, or provider request. The
protected commissioning checklist reports counts without exposing identities.

Once identities are known, the intended lifecycle is:

```text
draft → awaiting approval → approved → offered
      → activation pending → activated
```

The maker approves the envelope before an invitation is issued. The claimant
then proves the required identity and accepts that exact snapshot. Profiles may
activate automatically after compliant verification, but the consuming domain
still applies its own narrow capability adapter.

The offer credential is a high-entropy secret; only its hash is stored. A short
display Pay Code must never be treated as sufficient proof of privileged
authority.

## Normal invitations and cash

The Issuance page's **Invitation** mode remains the ordinary Account onboarding
experience. It may carry zero principal (the default) or cash for an ordinary
recipient. Cash settlement follows the existing Pay Code execution engine; it
does not come from provisioning.

Privileged provisioning is correlated to an approved provisioning request. It
locks the requested profile and evidence requirements; a template or user edit
must not weaken the approved snapshot.

## Institution-owned funds

Reconciled bank liquidity must be classified before it can be granted:

```text
Provider observation
    → Provider Inventory
    → Treasury Clearing
    → Institution-Owned Funds
```

Unexplained liquidity remains in Suspense or Clearing and is not grantable.

Provider-balance reconciliation records a positive authoritative difference in
Legacy Unattributed. It does not decide who owns that cash. The provider call
is itself governed: a named maker requests one active Treasury connection, an
independent checker approves the immutable request, and only an authorized
executor may run the balance check. Submission and approval never contact the
provider. A completed or review-required run is terminal, so replay does not
repeat the provider call.

```text
Request → independent approval → authoritative provider read
                               → exact positive difference → Legacy Unattributed
                               → mismatch or shortfall → Review Required
```

No automatic retry is performed after a provider failure. A new deliberate
request and approval are required, preserving an auditable reason for each
external observation. The stored run contains sanitized balances, evidence,
and operation references—not provider payloads or credentials.

Once evidence exists, an explicitly
authorized maker may submit the exact recognition operation as owner-funding
evidence; an independent checker approves and executes the immutable envelope:

```text
Legacy Unattributed → Institution-Owned Funds
Provider Inventory  → unchanged
Provider call        → none
```

The maker cannot type or alter the amount. X-Change copies the amount,
currency, connection, and evidence reference from the committed provider
recognition. One evidence operation can be classified only once. Provider
reconciliation remains a separate controlled operation and is not triggered by
classification approval.

An approved Account Grant executes exactly once:

```text
Institution-Owned Funds → Recipient Client Funds
Provider Inventory      → unchanged
Provider call           → none
```

The maker and checker must be distinct named humans. The executing operator
must hold explicit authority, and every transition emits append-only journal
evidence. A Test Allocation is still real internal money; it is available only
outside production, must be explicitly enabled, and is bounded per grant and
per recipient/day.

Operator authorization is mechanical and does not require System Principal
login:

```bash
php artisan x-change:treasury:authorize-operator 09170000001 \
  --capability=treasury.account_grants.view \
  --capability=treasury.account_grants.request \
  --capability=treasury.institution_funds.view \
  --capability=treasury.institution_funds.request \
  --capability=treasury.reconciliation.view \
  --capability=treasury.reconciliation.request \
  --authorization-reference=deployment-control:treasury-maker

php artisan x-change:treasury:authorize-operator 09170000002 \
  --capability=treasury.account_grants.view \
  --capability=treasury.account_grants.approve \
  --capability=treasury.account_grants.execute \
  --capability=treasury.institution_funds.view \
  --capability=treasury.institution_funds.approve \
  --capability=treasury.institution_funds.execute \
  --capability=treasury.reconciliation.view \
  --capability=treasury.reconciliation.approve \
  --capability=treasury.reconciliation.execute \
  --authorization-reference=deployment-control:treasury-checker
```

The authorized operators then use **Treasury Operations** in Cockpit. Ordinary
Account holders receive a concealed 404 and never see this navigation.

## Rollback-only acceptance

The lifecycle runner exercises the production request, approval, allocation,
and journal actions with no HTTP provider:

```bash
php artisan xchange:lifecycle:run treasury_account_grant_simulation \
  --issuer=<recipient-id> \
  --maker=<maker-id> \
  --checker=<checker-id> \
  --amount=100 \
  --json
```

It is restricted to `local` and `testing`, creates temporary authority inside
one outer transaction, verifies that Provider Inventory is unchanged by the
grant, and proves a complete rollback. It cannot be enabled in production.

The provisioning governance runner exercises the authority envelope itself:

```bash
php artisan xchange:lifecycle:run provisioning_governance_simulation \
  --issuer=<candidate-id> \
  --maker=<maker-id> \
  --checker=<checker-id> \
  --json
```

It provisions vacant seats, creates and submits an immutable request, proves
same-person approval is rejected, obtains independent approval, issues a
one-time high-entropy invitation, records verified acceptance, and confirms
that activation fails closed when no explicit domain adapter exists. It makes
no provider call, moves no money, grants no domain authority, and rolls back
the complete provisioning state.

## Cockpit manifestation

Named provisioning operators use **Cockpit → Provisioning**. The workspace
shows:

- vacant required and optional commissioning seats;
- immutable authority requests and their snapshot hashes;
- maker submission, independent approval or rejection, and maker withdrawal;
- one-time invitation issuance with a browser-memory-only link ceremony;
- evidence requirements and append-only lifecycle events; and
- accepted invitations as **Activation Pending** until a profile-specific
  adapter has been explicitly approved and installed.

The operator page is concealed from ordinary Account holders. Operator access
is granted mechanically and cannot be assigned to the System Principal:

```bash
php artisan x-change:provisioning:authorize-operator 09170000001 \
  --capability=provisioning.view \
  --capability=provisioning.request \
  --capability=provisioning.issue \
  --authorization-reference=deployment-control:provisioning-maker

php artisan x-change:provisioning:authorize-operator 09170000002 \
  --capability=provisioning.view \
  --capability=provisioning.approve \
  --authorization-reference=deployment-control:provisioning-checker
```

The maker chooses the exact capability pills before submission. The immutable
revision stores that list and its hash. Acceptance identifies the recipient but
grants nothing. A separately authorized checker activates exactly those pills;
revocation removes only authorizations whose reference came from that envelope.

Invitation delivery is an explicit post-issuance action. Email and SMS are
queued on `x-change-feedback`, preserve the normal x-feedback journal lifecycle,
and never call a mail or SMS provider from the Provisioning controller. The
one-time claim token is encrypted while it crosses the queue boundary.

Elapsed invitation offers are terminally expired by the scheduler through
`x-change:provisioning:expire-offers`. Rejection, withdrawal, expiry, and
revocation are append-only state transitions in `3neti/x-provisioning`.
Revocation remains fail-closed unless the consuming domain supplies a revoker
that can prove the underlying authority was actually removed.

Supersession is replacement-first: the replacement envelope must already be
active for the same verified subject and profile. The predecessor authority is
then revoked exactly once and preserved as `superseded` with both immutable
snapshot hashes and the replacement reference.

## Invitation boundary

**Issuance → Invitation** remains the ordinary recipient Account invitation.
It may carry zero value or cash, and cash continues through the Pay Code
execution engine.

**Provisioning → Issue Invitation** is a privileged authority invitation. Its
approved snapshot cannot be weakened by an Issuance template. The recipient
may need to create or authenticate an Account, but accepting the invitation
does not move money and does not by itself create an OAuth credential or grant
Treasury, Commercial, or API authority.

These two experiences intentionally share invitation language, identity
verification, and onboarding primitives; they do not share authority effects.

## Controlled activation gates

- Commercial, Treasury, and Partner API profiles require separately reviewed
  capability maps. No broad map is inferred from a profile label.
- Production API credentials are created only after an approved API mandate
  activates. An invitation is never an OAuth credential.
- Supersession must activate a replacement before retiring the old envelope;
  historical snapshots and evidence remain immutable.
- Institution-owned cash classification and Account Grants remain separate
  maker-checker Treasury operations. Provisioning never supplies liquidity.

## Production API credential ceremony

API Production Maker authority permits an operator to submit a bounded,
immutable client mandate. It does not create a Passport client. An independent
API Production Checker approves the snapshot and may then activate it. Only
activation creates the OAuth client and reveals its secret once in a
`no-store` response. Reloaded pages contain the client ID but never the secret.
The ordinary client suspension and terminal revocation controls revoke existing
tokens; terminal revocation also revokes the Passport client.

The System Principal cannot be maker, checker, credential activator, or the
recipient of interactive authority.

## Remaining operational closure

The provisioning machinery is implemented. Live maker-checker acceptance and
final documentation closure are tracked in
[Provisioning Operational Acceptance and Documentation Closure](../todo/provisioning_operational_acceptance.md).
Institution Funds classification and Account Grants remain adjacent Treasury
operations, not unfinished x-provisioning responsibilities.
