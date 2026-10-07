# Public On-Demand Issuance Testing-Instance Acceptance Compass

**Last updated:** 2026-10-07

## North Star

One testing-instance visitor can fund one low-value public Pay Code instruction
and receive exactly one Pay Code through the commissioned Commercial Principal,
without an Account, wallet, Client Funds balance, scheduled Standing Funding
run, or duplicate issuance.

## Current position

**Status: Planned and blocked at Gate 0. No payable order or live payment is
authorized by this compass update.**

The implementation is deployed on x-change `v1.0.104`, public Auto-Generate and
On-Demand Issuance are configured, fixed-amount QR Ph is configured, and
scheduled Standing Funding synchronization remains disabled. The public
surface forces `full_amount` even though the host-wide authenticated policy is
`shortfall`.

The testing environment is not currently ready for the exercise:

- commissioning state is `installation_incomplete`;
- the reason is `installation_manifest_stale`;
- `GET /api/x/v1/public-issuance` returns HTTP 503;
- `GET /x/auto-generate` redirects to commissioning; and
- the observed redirect/status URL used HTTP behind the Cloud proxy, so HTTPS
  external URL generation must be verified before order creation; and
- focused package verification found the rollback runner calling the current
  funding webhook job with one missing dependency, while the Cockpit runner
  test receives a commissioning redirect; and
- the host still uses database queues and has no commissioned Horizon/Redis
  queue topology.

Verification checkpoint: 18 public discovery/page tests passed with 121 total
assertions in the combined run; three rollback lifecycle tests failed. This is
recorded as a blocker, not a green lifecycle proof.

## Canonical testing URLs

| Surface | URL |
| --- | --- |
| Readiness | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/commissioning` |
| Recovery checklist | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/commissioning/checklist` |
| Discovery | `https://x-change-testing-testing-uw1gvj.laravel.cloud/api/x/v1/public-issuance` |
| Estimate | `https://x-change-testing-testing-uw1gvj.laravel.cloud/api/x/v1/public-issuance/estimate` |
| Handoff | `https://x-change-testing-testing-uw1gvj.laravel.cloud/api/x/v1/public-issuance/handoff` |
| Exercise entry | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/auto-generate?amount=25.00&currency=PHP` |
| Rollback proof | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/cockpit/campaigns/public-auto-generate-scenario-runner` |
| Pay Code Explorer | `https://x-change-testing-testing-uw1gvj.laravel.cloud/x/cockpit/pay-codes` |

The mutation endpoint is the browser-managed `POST /x/auto-generate`. The
public JSON estimate and handoff endpoints are read-only and cannot issue a Pay
Code.

## Gate status

| Gate | Scope | Status |
| --- | --- | --- |
| 0 | Commissioning recovery and HTTPS URL integrity | Blocked: stale installation manifest and HTTP-generated redirect observed |
| 1 | Read-only discovery, estimate, and handoff | Pending Gate 0 |
| 2 | Repair and run authenticated rollback-only lifecycle proof; prove queue canary | Blocked by runner drift and uncommissioned Horizon/Redis topology |
| 3 | One bounded PHP 25 payable order | Separately gated |
| 4 | One exact real payment | Separately gated; ceiling and rail not authorized |
| 5 | Operator and beneficiary evidence | Pending issuance |
| 6 | Idempotent retry and mutation-free rerun | Pending issuance |
| 7 | Closeout and compass evidence | Pending |

## Settled decisions

1. Public `/x/auto-generate` is the exercise surface.
2. Authenticated `/x/cockpit/quick-generate` is not substituted for the public
   exercise because it uses the host-wide funding policy.
3. Public issuance is forced to `full_amount` by the server.
4. The commissioned Commercial Principal is server-bound and cannot come from
   browser input.
5. Discovery, estimate, and handoff remain read-only.
6. Order creation, real payment, claim, and redemption are distinct authority
   gates.
7. Provider evidence is payment truth; acknowledgement and polling are not.
8. `GeneratePayCode` remains the sole issuance authority.
9. Scheduled Standing Funding remains disabled and is not required for public
   issuance.
10. Standing Funding address 4 remains quarantined.
11. Live payable-order acceptance waits for the separately commissioned Queue
    Operations and Horizon canary.

## Invariants

- No Pay Code exists before authoritative settlement.
- No estimate or handoff creates an order or financial state.
- No public request selects the issuer or funding basis.
- No live payment occurs without a separately approved ceiling and rail.
- No duplicate evidence, retry, refresh, or resume creates a second Pay Code.
- No received order-bound amount becomes generally spendable Client Funds
  while issuance is pending.
- No signed recovery or receipt URL is hand-built or persisted in public
  evidence.
- No externally presented URL uses HTTP.
- No Standing Funding schedule is enabled by this exercise.
- No action changes the quarantine of address 4.

## Immediate next gate

**Gate 0 — Commissioning recovery and HTTPS URL integrity.**

Inspect the stale installation manifest and current principals. If existing
installation facts are complete, review the guarded adoption operation. Then
prove commissioning is operational, public discovery and the editor return
HTTP 200, and every externally generated URL uses HTTPS. Do not create an order
or contact a provider in this gate.

## Following gate

Run discovery, estimate, and handoff; repair the rollback lifecycle runner and
its browser fixture; publish/adopt the tested repair; then run the rollback-only
browser scenario. Only after those proofs pass should the operator decide
whether to authorize one PHP 25 payable order and, independently, one real
payment.

## Companion documents

- [Testing-Instance Acceptance Plan](TESTING_INSTANCE_ACCEPTANCE_PLAN.md)
- [Public Auto-Generate Plan](PUBLIC_AUTO_GENERATE_PLAN.md)
- [Public Auto-Generate Compass](PUBLIC_AUTO_GENERATE_COMPASS.md)
- [On-Demand Issuance Funding Plan](../on-demand-issuance-funding/ON_DEMAND_ISSUANCE_FUNDING_PLAN.md)
- [On-Demand Issuance Funding Compass](../on-demand-issuance-funding/ON_DEMAND_ISSUANCE_FUNDING_COMPASS.md)
- [Queue Topology and Horizon Commissioning Plan](../QUEUE_TOPOLOGY_AND_HORIZON_PLAN.md)
- [Queue Operations Compass](../QUEUE_OPERATIONS_COMPASS.md)

Future agents must update this compass after every completed or blocked gate.
