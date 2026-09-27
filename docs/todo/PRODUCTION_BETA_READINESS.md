# Shared Host Production Beta Readiness

## Objective

Prepare one shared, public-facing X-Change host for a small, allowlisted beta
that may process real payments and transfers without representing X-Change as a
digital wallet, bank, deposit product, or licensed financial institution.

This checklist is fail-closed. The shared host must not accept public real-money
activity until every required gate below is complete and its evidence is linked
from this document. An invite-only or inner-circle audience does not waive a
legal, financial, security, or operational control.

## Current decision

- [x] **Balance migration is deferred.** The first production-beta release will
  not transfer, recreate, or credit balances from another host.
- [x] **Continuity is evidence-only.** DevOps will preserve an encrypted
  keepsake and a read-only closing balance report.
- [ ] Add any future balance-transfer or recovery mechanism only through a
  separately designed, authorized, reconciled, and versioned release.

The balance report must state that it is evidence only and does not authorize a
credit, transfer, settlement, or recreation of an Account or Pay Code.

## Gate 1 — Read-only balance report

**Owner:** X-Change package maintainer
**Status:** Complete — implemented, released, evidenced, variance dispositioned,
and reviewed on 2026-09-27
**Required before:** cleanroom production rehearsal

- [x] Define one stable, versioned balance-report schema.
- [x] Implement a read-only command that makes no provider call and performs no
  database, cache, journal, Treasury, Pay Code, or account mutation.
- [x] Include the snapshot timestamp, stable instance ID, package version,
  deployment profile, runtime tier, currency, and active provider connection.
- [x] Include persisted provider-balance snapshot value and freshness, while
  clearly distinguishing provider inventory from Account-attributed funds.
- [x] Include Treasury control positions and totals: Provider Inventory,
  Legacy Unattributed, Account Funding Reserve, Pay Code Reserve, Treasury
  Clearing, commercial positions, and other active position types.
- [x] Include per-Account Client Funds, Outstanding Pay Codes, and the inputs to
  Issuance Capacity. Use stable internal references and masked human identifiers.
- [x] Include active Pay Code obligations and terminal-state totals without
  exposing claim evidence, full mobile numbers, bank details, or credentials.
- [x] Include conservation checks, variances, stale/missing evidence warnings,
  and unresolved exceptions. A discrepancy must make the report visibly
  incomplete; it must not be silently normalized.
- [x] Produce deterministic JSON for machines and a human-readable report from
  the same read model. Record a SHA-256 checksum for each artifact.
- [x] Add regression coverage proving read-only behavior, deterministic output,
  masking, multi-provider/currency separation, incomplete snapshot handling,
  and arithmetic consistency.
- [x] Document the operator command, evidence-retention rules, and independent
  verification procedure in the continuity runbook.
- [x] Add a separate, explicitly acknowledged private roster showing `As of`,
  Account name, mobile number, and Client Funds for one exact active connection.
  Keep the masked forensic report as the authoritative conservation artifact.
- [x] Require an external authorization reference and sensitive-output
  acknowledgement for the private roster; state that the reference records but
  does not grant operator authority.
- [x] Prove the private roster makes no provider call and performs no financial,
  database, cache, journal, Treasury, Pay Code, or Account mutation.

**Completion evidence**

- [x] Focused test results: 3 passed, 59 assertions; adjacent continuity suite:
  19 passed, 158 assertions (2026-09-27).
- [x] Full package test result or documented unrelated baseline failures: the
  clean stop-on-first-failure run reached 55 passing tests before the unrelated
  existing named-slice claim assertion failed in
  `ValidateCompiledClaimVoucherTest.php:83`; the isolated assertion reproduces
  independently of this reporting slice (2026-09-27).
- [x] Example sanitized JSON/report and checksum fields: continuity runbook,
  **Read-only closing balance report**.
- [x] Git commit and released package version: `v1.0.58`.
- [x] Private roster focused test result: Gate 1 combined command suite, 5 passed
  and 82 assertions (2026-09-27).
- [x] Private roster implementation commit: `43eac149`.
- [x] Private roster released package version: `v1.0.59`.
- [x] Sandbox private roster evidence: generated from `netbank-primary` at
  `2026-09-27T02:17:51+00:00`; semantic SHA-256
  `0d1445677302379861163d9fbcaeedbd14fb6b7a60372aa4472a918ab01a1ea6`.
- [x] Sandbox masked forensic evidence: generated at
  `2026-09-27T02:18:27+00:00`; canonical JSON SHA-256
  `587d91e79baec9ebd645c5e6f5dd219ad32c650dec5aa521736839b2cc383653`;
  human report SHA-256
  `4d298ed8d4c67bda77bf8b6d378aa680f86ebfe92b0ed28525d3ca48276216b4`;
  semantic report SHA-256
  `06e41280196abf4b3add439b556493841a1cbf336cad27413ace2ff0a6cc6de6`.
- [x] Sandbox variance disposition (2026-09-27): the provider refresh observed
  authoritative NetBank liquidity of PHP `399245` minor while this host records
  PHP `2577702` minor of Treasury Inventory. Management confirmed the same
  NetBank account is shared by multiple non-production X-Change hosts, so the
  per-host Inventory cannot reconcile to the provider account. The report
  correctly remained `review_required` and made no financial posting. This
  disposition is valid only for sandbox evidence; the production-beta host must
  use a dedicated provider account or another reviewed topology that permits
  complete per-host reconciliation.
- [x] Reviewer and review date: Lester Hurtado — 2026-09-27.

## Gate 2 — Exact-version cleanroom commissioning rehearsal

**Owner:** DevOps, witnessed by an independent reviewer
**Status:** In progress — the exact-version local and fresh published-artifact
Cloud rehearsals completed on 2026-09-27. Commissioning, reconciliation,
idempotency, and public readiness passed. Independent keepsake verification,
an on-demand database recovery point, and independent review remain open.
**Required before:** production host commissioning

- [x] Pin the host application, X-Change, integration packages, PHP, Node, and
  Composer versions used by the release lock.
- [x] Create a disposable host from the published artifacts only. Do not use a
  package path repository or an unpublished local checkout.
- [x] Apply secrets through the deployment secret manager; never place secret
  values in chat, source control, manifests, or command arguments.
- [x] Run the canonical pre-commission strict doctor and retain its output.
- [x] Commission once using the reviewed production manifest and retain the
  installation manifest, command transcript, and package inventory.
- [ ] Confirm workers, scheduler, private durable storage, database backups,
  logging, alerting, and provider configuration are operational.
- [x] Run the final strict doctor and the approved non-financial smoke suite.
- [ ] Generate and independently verify the encrypted keepsake.
- [x] Generate the Gate 1 closing balance report and confirm that no balance was
  transferred or recreated in the cleanroom host.
- [x] Repeat the idempotent commissioning/verification checks and confirm no
  duplicate principals, invitations, reserves, or journal entries.
- [x] Record rollback and abort evidence. A failed gate must leave the host
  unavailable for public real-money use.

**Completion evidence**

- [x] Exact package/runtime inventory: x-PayOut `v1.0.0-beta.48`, X-Change
  `v1.0.60`, Laravel `13.33.0`, PHP `8.5.10`, Composer `2.10.3`, and Node
  `24` in Cloud. The host source was exact commit
  `4857d809b3566e8d178202b9d6ade4e535fc7621`; X-Change resolved to commit
  `3e640316b76bf8ff799d1a7404bfaaf007f0c630`.
- [x] Pre-commission and final doctor reports: pre-commission `24/24`; final
  strict doctor `31/31`, with runtime tier `production`, private durable `s3`
  claim evidence, database sessions/cache/queue, EngageSpark SMS, TXTCMDR OTP,
  NetBank readiness, and operational commissioning state.
- [x] Build/deploy/commission transcript: published-artifact deployment
  succeeded; first commissioning recognized PHP `399245` minor of NetBank
  inventory, capitalized it into Account Funding Reserve, and reserved PHP
  `20000` minor for two unclaimed invitations. Opening reserve was PHP
  `399245`; Account Funding Reserve after reservation was PHP `379245`.
  Replaying bootstrap returned the same Maker and Checker as `existing` and
  left counts unchanged at 2 vouchers, 4 Treasury position operations, and 1
  installation manifest.
- [ ] Keepsake and balance-report checksums: the fresh Cloud balance report
  completed with no blockers or warnings; report SHA-256
  `48449d55ec4ea6ead5eae0587ef23d9b7b8c2722bf22950a6e5a97efbd977993`,
  canonical JSON SHA-256
  `8637214907fa9d71fb3751afd30bf80a5ee409fc67b608d64f745c82111ed93a`,
  and human report SHA-256
  `8ef3c921768b54eb46958a472b37486956b8b79a9dc7a1f59ab99c48e1822fa0`.
  The keepsake dry-run was complete with plan hash
  `cb94caedbcad3ad6773363545a2148b0856984a06431eed8c4517fff76f92da1`,
  but archive creation failed closed because every newly issued bucket key was
  denied with HTTP 403. A fourth dedicated read/write key was issued, attached
  to the existing environment secrets, and deployed as
  `depl-a2d80b4c-4c3d-4d81-a6d9-e9ee60dc3e78`; the reversible
  write/read/delete probe still failed on the first existence check. No archive,
  grant, or journal record was created.
- [x] Cleanroom URL or environment reference:
  `https://x-payout-gate2-final-20260927-production-kq3zom.laravel.cloud`,
  environment `env-a2d7f47c-c4ec-4ae0-8a68-337a8e0e5c5a`. `/x/ready` and both
  unclaimed invitation pages returned HTTP 200 without guarded adoption.
- [ ] DevOps operator, independent reviewer, and rehearsal date: Codex operated
  the rehearsal under Lester Hurtado's explicit authorization on 2026-09-27;
  an independent reviewer is not yet recorded.

**Rehearsal defect and disposition**

- The published default x-PayOut manifest declares a local runtime tier. The
  bootstrap process incorrectly allowed that local default to replace an
  explicitly configured Cloud production tier while recording the installation
  fingerprint. Normal web requests then failed closed as
  `installation_manifest_stale`; `/x/ready` returned HTTP 503 and invitation
  pages redirected to commissioning.
- Guarded adoption revalidated the existing System Account and Treasury, wrote
  the actual production fingerprint, and restored HTTP 200 readiness without
  moving funds or replacing invitations.
- Package commit `e9a5c50a` preserves an explicitly configured non-local runtime
  tier when a reusable manifest supplies `local` as its default. Focused
  regression evidence: 16 tests, 150 assertions. The fix shipped in X-Change
  `v1.0.60`; the fresh published-artifact Cloud rehearsal remained at runtime
  tier `production` and reached an operational manifest without guarded
  adoption.
- A managed database snapshot could not yet be retained because the current
  Cloud CLI rejected the snapshot request without accepting a snapshot name.
  The CLI reports `name is required`, while an added positional name is rejected
  as an unexpected argument. The fresh cluster currently lists no snapshots.
- The dedicated private evidence bucket is available and configured, but all
  four bucket-scoped read/write keys issued by Laravel Cloud returned HTTP 403
  for list, head, put, or existence-check operations. The fourth-key repair was
  deployed successfully and resolved to the expected private R2 endpoint,
  bucket, `auto` region, path-style configuration, and non-empty credentials;
  the probe still failed. The readiness doctor currently validates configuration
  shape rather than an actual object-storage write, so durable claim-evidence
  and keepsake storage must not be accepted until an I/O probe passes.
- The keepsake email selector attempted to compare an email with the PostgreSQL
  bigint user ID. The selector now separates numeric model-key candidates from
  email candidates before building the query. Focused regression evidence is 9
  passing tests and 56 assertions, including proof that an email selector does
  not produce an integer-ID predicate. This package change remains unreleased.
- Database backup, alerting ownership, independent encrypted keepsake evidence,
  and the named independent reviewer therefore remain open. The two invitations
  must remain unclaimed until these controls are closed or formally dispositioned.
- Laravel Cloud support escalation `T-B3318` was submitted on 2026-09-27 with
  the exact application, environment, deployment, bucket, cluster, schema, CLI
  version, error classes, reproduction commands, and fail-closed safety posture.
  No credentials or customer data were included. Gate 2 remains blocked pending
  a supported bucket/key repair and auditable database recovery-point procedure.

## Gate 3 — Written Philippine counsel disposition

**Owner:** Management and qualified Philippine external counsel
**Status:** Not complete
**Required before:** any public real-money beta

- [ ] Obtain a written disposition covering EMI characterization, OPS
  registration, custody or agency, client-fund treatment and segregation,
  insolvency treatment, required disclosures, complaints/refunds, and the
  proposed invite-only pilot.
- [ ] Describe the actual fund flow, provider/bank roles, contractual parties,
  user journey, limits, fees, data processing, and shared-host ownership. Do not
  seek an opinion based only on product labels or UI vocabulary.
- [ ] Record controlling authorities and effective dates in x-legal.
- [ ] Convert the applicable x-legal profile from advisory/under-analysis to an
  explicitly reviewed and effective disposition.
- [ ] Record permitted, prohibited, and review-required operations so the
  application can fail closed.
- [ ] Obtain a specific decision on whether the beta may begin before a banking
  licence or only under a named regulated institution's authority.

**Completion evidence**

- [ ] Counsel memorandum/reference and date:
- [ ] Approved x-legal profile/version:
- [ ] Named regulated counterparty and authority, if required:
- [ ] Management acceptance and conditions:

## Gate 4 — One approved pricing schedule

**Owner:** Commercial, Finance, Legal, and Accounting
**Status:** Not complete
**Required before:** charging any customer or recognizing revenue

- [ ] Reconcile the executable X-Commerce catalog with all pricing documents.
- [ ] Approve one versioned schedule for the beta, including taxes, provider
  costs, refunds/reversals, discounts, and effective dates.
- [ ] Separate principal from every service charge in quotes, instructions,
  Treasury postings, receipts, reports, and customer disclosures.
- [ ] Ensure principal, Client Funds, provider inventory, settlement balances,
  float, and pass-through money can never be classified as revenue.
- [ ] Require explicit customer authorization for each applicable charge.
- [ ] Define who invoices and collects each fee and under whose contractual and
  regulatory authority.
- [ ] Test estimation, acceptance, posting, reversal, receipt, and reporting
  against the approved schedule.

**Completion evidence**

- [ ] Approved schedule/version and effective date:
- [ ] Catalog configuration/version:
- [ ] Legal, Finance, Accounting, and Commercial approvals:
- [ ] Pricing and accounting regression results:

## Gate 5 — Pilot operating controls

**Owner:** Operations, Security, DevOps, Finance, and Support
**Status:** Not complete
**Required before:** enabling public routes or real provider execution

- [ ] Enforce an allowlist for beta Accounts and operators.
- [ ] Set reviewed per-transaction, per-Account, daily, campaign, and aggregate
  exposure limits for collections, disbursements, and outstanding Pay Codes.
- [ ] Provide tested kill switches for issuance, collection, payout, campaign
  starts, provider execution, and public access without deleting evidence.
- [ ] Perform and evidence daily provider/Treasury/Pay Code reconciliation.
- [ ] Define alerts for queue failures, provider errors, stale liquidity,
  reconciliation variances, duplicate callbacks, unusual velocity, and limits.
- [ ] Approve incident severity levels, escalation contacts, response times,
  communications, rollback, evidence preservation, and post-incident review.
- [ ] Name the owner and backup owner for DevOps, reconciliation, customer
  support, complaints/refunds, security incidents, and provider escalation.
- [ ] Verify database recovery, private evidence retention, secret rotation,
  least privilege, rate limiting, audit logging, and dependency monitoring.
- [ ] Run controlled low-value end-to-end collection and disbursement acceptance
  only after the preceding controls are active and separately authorized.

**Completion evidence**

- [ ] Approved limits and allowlist policy:
- [ ] Kill-switch test record:
- [ ] Reconciliation report and sign-off:
- [ ] Incident/support roster and runbook:
- [ ] Controlled pilot test report:

## Gate 6 — Pre-clearance revenue boundary

**Owner:** Management, Commercial, Legal, Finance, and Accounting
**Status:** Not complete
**Required before:** signing or invoicing the first customer

- [ ] Monetize B2B software access, implementation, professional services, and
  managed operations first under signed contracts and approved invoices.
- [ ] Keep those charges contractually and operationally separate from client
  principal and provider money movement.
- [ ] Prohibit revenue recognition from principal, Client Funds, provider
  inventory, settlement balances, float, or pass-through funds.
- [ ] Approve tax, invoicing, refund, service-level, data-processing, support,
  and termination terms.
- [ ] Ensure marketing and customer-facing language do not describe X-Change as
  a bank, deposit account, digital wallet, or licensed financial institution.
- [ ] State which real-money features remain unavailable until counsel and any
  required regulated counterparty authorize them.

**Completion evidence**

- [ ] Approved contract templates and service description:
- [ ] Invoice/tax/accounting treatment:
- [ ] Approved public claims and prohibited terminology:
- [ ] First-customer approval record:

## Final production-beta decision

- [ ] Gates 1–6 are complete with linked evidence.
- [ ] The release candidate and exact host lock were independently reviewed.
- [ ] Management, Legal, Finance, Security, Operations, and DevOps signed the
  final go/no-go record.
- [ ] The production host passed strict readiness immediately before opening.
- [ ] Public access was enabled under the approved allowlist, limits, and
  kill-switch posture.

Until every final item is checked, the permitted state is development,
non-financial demonstration, or an expressly authorized rehearsal—not a public
real-money production beta.
