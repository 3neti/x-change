# Instance Continuity and Recovery

This runbook defines the controlled movement of evidence and approved state from one
X-Change host instance to another. It is written for authorized human operators and
AI-assisted installers. The AI may explain, inspect, and plan; X-Change commands,
policies, approvals, and journals remain authoritative.

## Trust model

1. Composer installs application code; a continuity image carries selected instance data.
2. The source produces an encrypted keepsake for a destination-controlled public key.
3. The destination verifies and inspects the image before any destructive source action.
4. A deterministic continuity plan identifies portable evidence and unresolved obligations.
5. Financial recovery requires a separate immutable plan and Maker/Checker authorization.
6. Replaying an approved apply operation must never duplicate a credit or obligation.

Current commands are deliberately read-only. There is no continuity apply command yet.

## Implementation status and revised recovery path (2026-09-22)

The local sandbox consumes x-change `v1.0.33`. The bounded NetBank capture,
offline attribution audit, and checksum-pinned provider-attribution proposal are
implemented. The local five-row capture for September 1–20, 2026 (exclusive end)
produced three unmatched provider credits, two unmatched provider debits, and
zero matches. Repeated proposal runs returned the same hash, and the inspected
financial table counts remained unchanged. The five-row limit was reached:
this is an **incomplete sample**, not a statement of total provider activity,
source liabilities, beneficial ownership, or recoverable value.

We will pursue recipient-bound, Account Funding Pay Codes as a narrower
**delivery mechanism** instead of importing source account records as a first
step. A person may create or connect a fresh destination Account during claim,
prove control of the intended mobile number, and receive approved value through
the existing `account_funding` claim outcome. Mobile verification alone does
not prove ownership of a source balance. This path does not restore an old
Account, old Pay Code, prior claim status, provider history, or credentials.
It is not authorized or implemented as a continuity issuance workflow today.

The order of gates is:

1. **Preserve source evidence first.** Export and independently verify the
   encrypted keepsake before any source or destination reset. Retain claim
   images, maps, and location evidence under the existing privacy controls;
   defer their account-linked presentation, not their preservation. Pin the
   archive checksum, manifest, source instance identity, and a consistent
   source cutover checkpoint.
2. **Build a complete entitlement proposal, read-only.** Reconcile source
   Client Funds and outstanding Pay Code obligations at the cutover, plus
   provider activity and ownership evidence. Classify each historical code
   (paid, claimed, cancelled, expired, or still outstanding), and exclude
   liabilities that must remain on the source or were already satisfied.
   Propose one recipient identity, amount in minor units, mobile binding,
   source evidence reference, and disposition per proposed entitlement. No
   amount may be derived by summing unmatched provider credits or by treating
   the five-row capture as complete.
3. **Review backing and non-duplication.** Establish which instance owns the
   provider funds, identify any shared NetBank account exposure, and prove
   sufficient attributed Account Funding Reserve in the destination. Freeze
   or otherwise fence source-side spending at a documented cutover. Match the
   entitlement total to source obligations and the destination reserve without
   double-counting outstanding Pay Codes. Produce deterministic plan and
   evidence hashes, per-recipient exceptions, and a dry-run Treasury posture.
4. **Separate approval and implementation gate.** An independent Maker and
   Checker must approve an immutable, expiring plan pinned to source/destination
   IDs, cutover, hashes, beneficiary set, amounts, fee treatment, and rollback
   policy. Implement and test idempotent recipient-bound issuance through the
   existing Treasury-backed Account Funding path; fail closed on duplicate
   source entitlements, missing reserve, changed evidence, mobile mismatch,
   or partial failure. No direct wallet/database credit or synthetic provider
   transaction is permitted.
5. **Rehearse locally before any live cutover.** Use a disposable, separately
   identified x-PayOut installation. Verify issuance reservation, OTP/mobile
   binding, account creation or connection, one-time claim, resulting Client
   Funds, repeat/replay behavior, journal, and reconciliation. Compare source
   and destination obligations before and after; stop if the provider-backed
   conservation proof fails. Only then seek a distinct authorization for any
   live financial operation.

The currently released `x-change:continuity:propose-provider-attribution`
command is **review-only**. Its `financial_apply_not_supported`,
`maker_checker_authorization_not_present`,
`provider_attribution_not_authorized`,
`transaction_ownership_not_established`, and
`provider_statement_sample_incomplete` blockers remain. No released command
issues recovery Pay Codes or credits destination Client Funds from this plan.
See the [continuity status in the Settlement OS compass](../architecture/SETTLEMENT_OS_COMPASS.md#instance-continuity-recovery-track).

**Immediate dependency:** the testing-instance forensic report for paid Pay
Codes `6HGF` and `XGQQ` exposed a presentation/read-model defect: full
collection remains recorded, but expiry can supersede Paid in Cockpit and
possibly the Partner API. Complete the
[paid-status corrective gate](../todo/remaining.md)
before treating historical voucher status as source-obligation evidence.
Collection and receipt evidence, availability, and original claim outcome
must be assessed separately. No financial repair was indicated for those
two payments.

## Inputs and secret handling

An AI guide may ask conversationally for an instance identifier, archive location,
retention choice, or disposition decision. Private keys and provider/storage credentials
must be entered through masked local prompts or a deployment secret manager. Never paste
them into chat, command arguments, manifests, reports, or source control.

The agent must stop when the encryption key, expected checksum, destination identity,
required formal authorization, or provider reconciliation is unavailable.

## Baseline workflow

Generate a recipient key locally and configure only its public key on the source. Create
the keepsake according to [Instance Keepsake Export](./INSTANCE_KEEPSAKE_EXPORT.md), then
download it into private operator-controlled storage.

Inspect the encrypted image:

```bash
php artisan x-change:instance-keepsake:inspect \
  /private/transfers/continuity-image.xck \
  --private-key-file=/private/keys/continuity.key \
  --expected-archive-sha256=<EXPORT_SHA256> \
  --json --pretty
```

Create a destination-specific dry-run proposal:

```bash
php artisan x-change:continuity:plan \
  /private/transfers/continuity-image.xck \
  --private-key-file=/private/keys/continuity.key \
  --expected-archive-sha256=<EXPORT_SHA256> \
  --destination=x-payout-cleanroom \
  --json --pretty
```

Both commands must report `read_only: true`, `moves_money: false`, and
`safe_to_reset: false`. A successful plan is evidence for review, not permission to
delete the source or credit the destination.

## Offline provider attribution audit

When a persisted Treasury balance differs from the provider statement, normalize the
private statement to CSV with these required headers:

```text
transaction_id,direction,amount,currency,status,occurred_at
```

`direction` must be `credit` or `debit`; `amount` is an unsigned decimal string in the
selected connection's currency; `occurred_at` must be an explicit parseable timestamp.
Additional columns may be present but are never emitted in the report.

Run the bounded offline audit from a private local path:

```bash
php artisan x-change:treasury:audit-provider-attribution \
  --statement=/private/statements/netbank-normalized.csv \
  --connection=netbank-primary \
  --json
```

The command streams the statement, makes no provider calls, writes no database/cache/
journal/Treasury state, and hashes every transaction identifier before including it in
output. It classifies matched inflows/outflows, unmatched provider debits/credits,
duplicates, and amount/status mismatches. The result always reports
`safe_to_reconcile: false`: it is evidence for disposition, never authority to repair,
credit, derecognize, or reset an instance.

Default safeguards limit the input to 10 MiB, 10,000 rows, 64 columns, and 2,048
characters per cell. Operators should retain the private source statement separately
from the sanitized JSON report and its `statement_sha256`.

## Current portability matrix

| Data | Current treatment |
|---|---|
| Account observations | Inspect and reconcile; identity must be reverified |
| Client Funds | Observation only; separate provider-backed recovery is required |
| Historical Pay Codes | Evidence only; original codes are not recreated |
| Active Pay Codes | Explicit operator disposition required |
| Claim images and maps | Retained for authorized offline review |
| Precise location JSON | Excluded unless separately requested and confirmed |
| Account invitations | Inert blueprint; no credentials or authority |
| Pay Code templates | Inert review blueprint; instructions are currently excluded |
| Endpoint campaigns | Historical snapshot plus inert, disabled review blueprint |
| Secrets, sessions, OTPs | Never included |

Set a stable, non-secret `XCHANGE_INSTANCE_ID` on every host. The continuity checkpoint
also records the public application identity, deployment profile, runtime tier, and only
persisted provider-balance snapshots. Export never refreshes or contacts a provider.

## Cleanroom rehearsal stop conditions

Do not remove or reset the destination/source codebase until all of these are true:

- the encrypted image exists outside both applications;
- its expected SHA-256 is recorded separately;
- destination-key decryption and entry verification succeed;
- inspection reports the expected inventory and privacy scope;
- a destination-specific plan is deterministic across repeated runs;
- source financial totals and provider checkpoint are recorded separately;
- every blocker has an explicit disposition;
- a rollback copy of destination installation inputs exists.

## Future apply contract

The eventual consequential command is reserved as:

```bash
php artisan x-change:continuity:apply \
  --plan=<persisted-plan-id> \
  --authorization=<maker-checker-authorization-id>
```

The authorization must pin the archive hash, plan hash, destination, expiry, requester,
and independent approver. Chat confirmation is not financial authorization. Until the
apply command, persistence model, idempotency guard, and reconciliation proof are shipped,
operators must not simulate recovery through direct database or wallet mutation.

## Evidence report

Retain the archive hash, manifest hash, source export plan hash, destination continuity
plan hash, package versions, inventory, omissions, operator references, doctor results,
provider checkpoint, reconciliation report, and browser lifecycle results. Reports must
name secrets only by configured key; they must never expose secret values.
