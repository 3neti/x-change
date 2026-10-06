# Payment Confirmation Resilience Recovery Plan

**Opened:** 2026-10-06

**Status:** Complete through immutable release, x-PayOut adoption, and branch retirement

## Objective

Recover the durable partner payment-event work from local-only stale branches,
forward-port it onto current x-change, publish one immutable package release,
adopt that release in x-PayOut, and retire each stranded branch only after its
unique patches are merged or proved superseded.

This work communicates a completed collection to an explicitly configured
partner receiver. It does not collect money, repeat settlement, infer payment
truth, or make a webhook authoritative.

## Repositories and ownership

| Repository | Ownership |
| --- | --- |
| `3neti/x-change` | Durable event outbox, signed delivery, receiver safety, retry state, queue command, and package tests |
| `3neti/x-PayOut` | Installed x-change release, deployment-managed receiver configuration, queue/worker posture, and host acceptance |
| `3neti/x-change-sandbox` | Historical host reference only; its stale host branch is not authoritative production source |

## Stranded branch inventory

| Branch | Disposition |
| --- | --- |
| x-change `codex/payment-confirmation-resilience-v1` | Retired after its two source patches were forward-ported and reconciled onto current `main` |
| x-change `release/payment-resilience-v1030` | Retired as a patch-equivalent duplicate of the recovered work |
| x-change `codex/commissioning-recovery` | Retired after patch-equivalence to `main` was proved |
| x-change-sandbox `release/payment-resilience-v1` | Retired after x-PayOut beta.75 superseded its host configuration |
| x-change-sandbox `codex/x-change-v1.0.56-testing` | Retired locally and remotely with zero commits ahead of sandbox `main` |
| x-PayOut Dependabot PR 1 | Closed and branch deleted after its action upgrades were reapplied and tested on current `main` |
| x-PayOut immutable release branches | Preserve; they are release evidence, not stranded work |

## Ordered gates

### Gate 1 — x-change forward-port

- Start from current x-change `main`.
- Forward-port only the two unique payment-resilience patches.
- Reconcile conflicts with current payment monitoring and commissioning code.
- Preserve transaction-bound outbox creation and zero HTTP activity during
  collection.

### Gate 2 — x-change hardening

- Prove one stable event per collection.
- Prove rollback removes the event.
- Prove replay does not duplicate it.
- Prove disabled, unbound, ambiguous, and inactive partner cases fail closed.
- Prove receiver URLs, DNS answers, redirects, response bodies, and diagnostics
  cannot bypass the outbound safety boundary.
- Prove bounded retries, expired-lease recovery, shared locking, dedicated
  durable queue use, and explicit failed-event requeue.

Verification checkpoint (2026-10-06):

- `PartnerPaymentEventsTest` and `PaymentAttemptLifecycleTest` pass together:
  21 tests.
- Pint and strict Composer validation pass.
- The unpartitioned package-wide Pest run is not a green signal: after more
  than one hour it exhausted a 2 GB PHP memory limit while exporting accumulated
  failures. This is recorded as a suite-harness/baseline constraint, not hidden
  and not represented as payment-resilience test failure.
- Release acceptance therefore remains bounded to the affected payment suites
  plus host adoption tests until the package-wide suite can be partitioned into
  reliable groups.

### Gate 3 — immutable x-change release

- Run focused and full package suites, Pint, Composer validation, and relevant
  static analysis.
- Merge the tested recovery branch into x-change `main`.
- Publish the next unused immutable x-change tag.

### Gate 4 — x-PayOut adoption

- Update x-PayOut to the new immutable x-change release.
- Add deployment-managed partner receiver configuration without committing a
  receiver secret.
- Keep delivery disabled by default and fail closed when configuration,
  durable queue, or shared lock prerequisites are missing.
- Add host-level configuration and command acceptance tests.

### Gate 5 — x-PayOut release acceptance

- Run focused host tests, deployment tests, full suite, build, and strict
  pre-commission checks.
- Merge into x-PayOut `main`.
- Publish the next unused immutable x-PayOut release.
- Treat live deployment and receiver enablement as separate explicit gates.

### Gate 6 — branch retirement

- Record final commit and release ancestry.
- Prove each retired branch has no unique patch absent from accepted `main`.
- Remove clean associated worktrees and delete obsolete local/remote branches.
- Preserve immutable release branches and tags.
- Update both compasses with exact dispositions.

## Safety boundaries

- No provider call or money movement.
- No live partner notification during automated acceptance.
- No receiver secret in Git, deployment manifests, logs, or evidence.
- No direct merge of branches more than sixty commits behind `main`.
- No branch deletion before patch-equivalence or merged-ancestry proof.
- No live deployment, receiver activation, or OAuth issuance without a
  separately explicit gate.

## Completion evidence

- x-change `main`: `dbee25b7816d090e8ad0fc90c0c81b86c0c07864`.
- Immutable x-change release: `v1.0.101`.
- x-PayOut immutable adoption release: `v1.0.0-beta.75` at
  `d3ab8af`.
- x-PayOut `main` after CI bootstrap and action refresh: `18b62a3`.
- x-PayOut acceptance: 166 tests, 1,046 assertions; production frontend build
  and strict Composer validation passed.
- The x-PayOut repository-wide frontend formatting command still reports its
  pre-existing 333-file baseline. That baseline was not auto-rewritten or
  hidden during this recovery.
- No live deployment occurred and partner payment-event delivery remains
  disabled until separately configured and authorized.
