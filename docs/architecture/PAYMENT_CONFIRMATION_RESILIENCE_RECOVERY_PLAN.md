# Payment Confirmation Resilience Recovery Plan

**Opened:** 2026-10-06

**Status:** x-change forward-port verified; immutable release and x-PayOut adoption in progress

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
| x-change `codex/payment-confirmation-resilience-v1` | Forward-port its two unique patches, then retire |
| x-change `release/payment-resilience-v1030` | Patch-equivalent duplicate; retire after forward-port acceptance |
| x-change `codex/commissioning-recovery` | Already patch-equivalent to `main`; retire without merge |
| x-change-sandbox `release/payment-resilience-v1` | Use as host configuration reference; retire when x-PayOut adoption supersedes it |
| x-change-sandbox `codex/x-change-v1.0.56-testing` | Zero commits ahead of `main`; retire without merge |
| x-PayOut Dependabot PR 1 | Rebase or recreate and merge only when green; otherwise close as superseded |
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
