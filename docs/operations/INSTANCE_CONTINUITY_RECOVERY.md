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
