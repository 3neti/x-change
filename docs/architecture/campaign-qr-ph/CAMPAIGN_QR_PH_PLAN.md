# Campaign QR Ph, Provisional Coverage, and Settlement Envelope Plan

Last updated: 2026-09-23

## Objective

Add a second, explicit campaign entry mode in which a reusable campaign QR Ph
collects provider payments directly. A confirmed payment may bind provisional
coverage and open a first-class settlement envelope without pretending that a
policy has already been issued.

This mode coexists with the current endpoint campaign:

| Campaign entry mode | Public artifact         | First durable business event               |
| ------------------- | ----------------------- | ------------------------------------------ |
| Endpoint            | Web link or ordinary QR | A fresh Pay Code is issued                 |
| Campaign QR Ph      | Reusable provider QR Ph | A provider payment is observed and settled |

The two modes must not be inferred from one another or silently converted.

## Settled Architecture

### Provider boundary

- `emi-netbank` owns NetBank transport, response parsing, pagination, provider
  status normalization, and provider evidence provenance.
- `emi-core` owns provider-neutral payment-observation DTOs and contracts.
- `x-change` owns campaign binding, orchestration, idempotency, settlement
  envelope creation, provisional-coverage binding, completion Pay Code
  issuance, audit, and read models.
- `x-campaign` may own campaign configuration and aggregate lifecycle, but it
  must not parse NetBank responses or apply money.

### Settlement envelope and provisional coverage

A settled, qualifying campaign payment creates the following in one database
transaction:

1. an immutable campaign-payment recognition;
2. an authoritative `ProvisionalCoverage` record;
3. a first-class settlement envelope; and
4. an immutable coverage snapshot/reference inside that envelope.

The dedicated `ProvisionalCoverage` record remains the legal and operational
source of truth. The envelope is the durable orchestration and evidence
container. It must carry provisional coverage from its first committed
version—not add it later as decorative metadata.

The initial AUI driver key is reserved as:

```text
aui.personal-accident.provisional-cover@1.0.0
```

The driver must declare ownership of each envelope field:

- `payment.*`: system-managed from immutable provider evidence;
- `coverage.*`: system-managed from the authoritative coverage record;
- `applicant.*`: claimant-managed through the completion Pay Code;
- `policy.*`: insurer/integration-managed after policy issuance.

The driver is not implemented until provider evidence and generic envelope
contracts pass their gates. No AUI-specific behavior belongs in the generic
payment observer.

## Invariants

1. A QR scan, page view, or provider row is not by itself a settled payment.
2. Provider transaction identity plus provider account/address binding is the
   idempotency boundary.
3. Payment observation does not credit Client Funds or create an Account
   Funding receipt.
4. Payment monitoring records facts; it does not decide coverage or business
   application.
5. Provisional coverage starts only after a qualifying settled-payment rule
   succeeds.
6. Coverage and the first envelope version commit atomically.
7. A completion Pay Code is zero-denominated and cannot move money.
8. Policy issuance is a later outcome and is never implied by intake or
   provisional coverage.
9. Raw provider payloads, account identifiers, and personal data are not
   emitted to logs, broadcasts, or public read models.
10. Retries, polling, and duplicate webhooks converge on one recognition, one
    coverage record, one envelope, and one completion Pay Code.

## Controlled Gates

### Gate 0 — Architecture disposition and boundary lock

Status: **Complete**

- Record the two campaign entry modes.
- Lock package ownership and the non-interpretive payment-monitor boundary.
- Lock the authoritative coverage record plus envelope snapshot model.
- Reserve the initial driver key without implementing insurer-specific logic.

### Gate 1 — NetBank observation characterization

Status: **Complete; provider reversal shape remains a Gate 4 stop condition**

Characterize the existing `FundingAddressPurpose::Payment` path before adding
recognition logic.

Baseline findings now protected by tests:

- exact-destination payment observations are persisted and classified;
- repeated synchronization converges on one immutable provider observation;
- Account Funding receipts, Client Funds credits, and Treasury operations are
  not created; and
- the current standing-address persistence does not retain the optional
  provider-neutral payer identity DTO.

Live findings:

- one reusable QR accepted two independent settled payments;
- provider transaction identity distinguished those economic payments;
- immutable payload identity produced two evidence versions for the first
  transaction after NetBank refined `settled_at` by six seconds; and
- immediate replay of the unchanged provider response added no evidence row.

Recognition must therefore reduce compatible immutable evidence versions by
provider transaction identity before applying campaign rules. It must fail
closed when amount, currency, destination, or status evolution is incompatible.

Implemented reducer boundary:

- `ReduceProviderFundingTransactionEvidence` reads persisted immutable
  observations only;
- `CanonicalProviderFundingTransactionData` exposes hashed identities and
  canonical economic/provider state;
- compatible status and settlement-time refinement converges deterministically;
- incompatible economic, routing, identity, time, or status evidence throws
  `IncompatibleProviderFundingEvidence`; and
- the live two-payment evidence reduces to exactly two canonical transactions.

Remaining qualification requires controlled NetBank evidence for:

- stable provider transaction/operation identifiers;
- settled-status semantics and reversals;
- pagination and time-window behavior;
- reusable QR lifetime and expiry behavior;
- sender institution, account/mobile, and name availability; and
- replay behavior across polling and webhooks.

Polling policy at this boundary:

- an active reusable campaign address remains eligible for observation for its
  full active lifetime; x-change does not invent a QR expiry that NetBank has
  not reported;
- the minute scheduler is only a dispatcher, and an address is eligible only
  when its configurable minimum polling interval has elapsed;
- never-checked addresses are prioritized, then the stalest due addresses,
  within the configured batch ceiling;
- the NetBank adapter reads at most ten 100-row VCA-history pages and fails
  closed if the provider history exceeds that bounded view; and
- webhooks and operator synchronization may supply fresher evidence without
  changing the scheduled cadence policy.

Reversal and return policy at this boundary:

- raw provider status is evidence, never an instruction to move money;
- `reversed`, `refunded`, `charged_back`, `returned`, unknown terminal states,
  and any transition away from `settled` are incompatible evidence;
- incompatible evidence must be retained and surfaced for attention, with no
  coverage, envelope, Pay Code, Client Funds, or Treasury side effect; and
- NetBank status mapping remains intentionally unimplemented until a
  documented response or controlled live observation proves whether a return
  mutates the original transaction or appears as a separate debit/reference.

The first local provider provisioning attempt produced an additional boundary
finding: the sandbox still uses `netbank-mobile-v1`, and the proposed mobile
already resolves to a Standing Funding Address bound to Account Funding.
`ProvisionStandingFundingAddress` correctly rejected reusing that provider
destination for purpose `payment`. The sandbox has no dedicated
`netbank-account-hmac-v2` key configured, so the characterization must not
invent another person's mobile or create a non-reproducible temporary HMAC
binding.

No live payment is authorized by this document. The operator must authorize a
small live characterization separately.

### Gate 2 — Provider-neutral payer evidence

Status: **Dependency-integrated; x-change release and host verification pending**

- Live characterization proved provider-reported name, source account, and
  institution code for both payments; explicit payer mobile was absent.
- `ProviderPayerIdentityData` now carries those optional fields without
  inferring account as mobile.
- EMI Core stores payer fields in encrypted columns outside observation
  metadata and preserves verification source plus provider-verification flag.
- NetBank maps only provider-returned fields and marks transaction-history
  identity `providerVerified=false`.
- Standing-address observation now consumes the existing bounded multi-page
  transaction iterator.
- Broadcasts, journal summaries, and canonical transaction DTOs remain free of
  raw payer identity.

### Gate 3 — Campaign QR Ph configuration

Status: **Complete; operator UI deferred**

- `CampaignEntryMode` distinguishes `pay_code_on_open` from
  `reusable_payment_qr`; existing endpoint campaigns default explicitly to the
  former.
- `CampaignPaymentQrBinding` immutably binds one endpoint-campaign template
  revision to one active payment-purpose Standing Funding Address and one
  active static QR artifact.
- The binding snapshots provider, currency, open/fixed amount mode, optional
  fixed amount, availability interval, permitted-payment rules, and a
  deterministic configuration hash.
- Replaying identical configuration returns the existing binding. Conflicting
  configuration for the same revision, reused provider addresses, stale QR
  artifacts, incoherent amount rules, and cross-owner binding fail closed.
- A later campaign-template change creates a new revision and therefore needs
  a new explicit binding; historical bindings cannot be updated or deleted.
- This gate adds no payment recognition, coverage, envelope, Pay Code,
  notification, or financial posting behavior.

### Gate 4 — Payment recognition service

Status: **Complete**

#### Gate 4a — Evidence quarantine and operator attention

- Payment-purpose synchronization inspects immutable observations associated
  with an immutable campaign payment-QR binding.
- Compatible `pending`, `processing`, and `settled` evidence remains
  observation-only.
- Adverse, unknown, or canonically incompatible evidence creates an immutable,
  replay-safe quarantine record and a redacted audit event.
- Cockpit campaign rows expose only an aggregate `Needs attention` status and
  count; raw provider transaction and payer evidence remains private.
- Regression coverage proves this path creates no coverage, settlement
  envelope, Pay Code, Account Funding receipt, Client Funds movement, wallet
  transaction, funding settlement, or Treasury operation.

#### Gate 4b — Qualifying-payment recognition

Status: **Complete**

- `RecognizeQualifyingCampaignPayment` consumes canonical immutable provider
  evidence only after Gate 4a inspection.
- Recognition requires a settled, destination-verified payment matching the
  exact provider, funding address, currency, active availability interval,
  amount mode, configured amount limits, allowed rail, and maximum-payment
  rule.
- Binding row locks plus unique binding/transaction keys make recognition
  exactly once. Replays verify the immutable economic and configuration
  snapshots before returning the existing record.
- Settled payments that fail a binding rule enter the durable Gate 4a
  quarantine as `qualification_rejected`; pending and processing evidence
  remains observation-only.
- A redacted `CampaignPaymentRecognized` event carries a DTO and dispatches
  only after commit. Raw provider identifiers and payer evidence are excluded
  from its broadcast payload.
- Recognition is explicitly non-financial: it creates no coverage, envelope,
  Pay Code, Account Funding receipt, Client Funds, wallet transaction, funding
  settlement, Treasury operation, or message.

### Gate 5 — Provisional coverage and settlement envelope

Status: **Complete through Gate 5b**

- Persist the authoritative coverage record and envelope atomically.
- Snapshot the qualifying payment, coverage terms, campaign revision, driver,
  effective time, expiry, and authorization provenance.
- Prove failure rolls back both facts.

Gate 5a now provides a generic `BindProvisionalCoverage` boundary. It accepts
one immutable payment recognition and explicit, versioned driver terms; locks
the recognition; validates the exact envelope driver and its JSON schema; and
commits one immutable `ProvisionalCoverage` with envelope payload version 1 in
one database transaction. Deterministic fingerprints make identical replay a
no-op and reject conflicting replay. Its audit and broadcast event expose only
references and non-sensitive coverage metadata, dispatch after commit, and
declare zero financial side effects.

The envelope refers to the recognition as its source fact and contains the
coverage reference from its first payload version. No AUI driver is shipped by
this slice. The settlement-envelope package's own
model-level `EnvelopeCreated` event is not currently an after-commit event; no
x-change listener relies on it, and hardening that package event remains
separate package debt.

Gate 5b adds an explicit driver-owned orchestration boundary:

- `CampaignCoverageDriverContract` evaluates a persisted recognition and
  returns an explicit eligible/ineligible decision;
- an eligible decision must include complete, versioned coverage terms;
- an ineligible decision contains a reason and stops before persistence;
- `CampaignCoverageDriverRegistry` resolves the exact `driver@version`, rejects
  duplicate identities, and fails closed for unavailable versions;
- `OrchestrateProvisionalCoverage` verifies that returned terms match the
  selected driver identity and then delegates all locking, schema validation,
  idempotency, and atomic persistence to Gate 5a; and
- hosts may register driver implementations through
  `x-change.settlement.campaign_coverage_drivers` or the package's tagged
  service-container extension point.

The orchestration remains explicit and dormant. No event listener automatically
converts every recognized payment into coverage. Driver evaluation creates no
durable denial record or additional event, and no generic product rule has been
introduced.

### Gate 6 — Completion Pay Code

Status: **Complete**

- Issue one zero-denominated Pay Code from the envelope through an immutable,
  one-to-one issuance record.
- Collect only the driver-declared applicant requirements through `/x/claim`.
- Preserve normal OTP, form-flow, X-Ray, journal, and execution rules.

Gate 6a adds the generic issuance boundary. It requires the issuer to own the
campaign payment address, verifies the envelope and coverage driver identities,
and locks the coverage before issuance. Exact replay returns the original Pay
Code; changed instructions or authority fail closed. Voucher and immutable link
creation share one transaction, so a link failure rolls back the voucher.

The issued voucher has zero cash value and uses the dedicated
`campaign_coverage_completion` driver. Its persisted `execution_only` policy
allows ordinary `/x/claim` evidence collection and redemption while suppressing
external payout. It creates no Client Funds, Treasury, funding-settlement, or
non-zero wallet movement. The generic contract contains no AUI fields or policy
semantics.

Gate 6b projects finalized claim evidence into a new immutable envelope payload
version only after normal claim completion. The public envelope receives a
redacted manifest of requirement keys, evidence kinds and statuses, opaque
references, hashes, and timestamps. Raw values, PII, and private artifact paths
remain outside the envelope; the projection keeps only an encrypted private
source snapshot for durable correlation.

The issuance, claim, envelope, and new payload version are locked together.
Exact replay returns the original projection, changed evidence fails closed,
and a projection persistence failure rolls back the envelope version and audit
row. A redacted after-commit event is emitted without creating financial side
effects. Product interpretation and insurer fulfillment remain outside Gate 6.

The settlement-envelope package currently emits its generic `PayloadUpdated`
event from inside its transaction. Gate 6b attaches no listeners or external
side effects to that event; changing that package-level timing is tracked as a
separate durability improvement rather than widening this gate.

### Gate 7 — AUI policy completion

Status: **Complete through Gate 7e offline intake tooling; authoritative AUI disposition pending**

- Implement the reserved AUI driver as a versioned adapter.
- Hand completed applicant and settlement-envelope facts to the insurer
  boundary.
- Record policy success/failure without rewriting payment or coverage facts.

Gate 7a implements the reserved
`aui.personal-accident.provisional-cover@1.0.0` policy-completion adapter as a
pure preparation boundary. An exact-version, duplicate-rejecting registry
resolves the adapter from the immutable completion projection. The adapter
validates that the projection, completed claim, completion issuance,
provisional coverage, settled payment recognition, envelope, and payload
version form one coherent chain before producing a deterministic preparation
fingerprint and idempotency key.

The preparation object separates a safe reference/economic context from
private applicant evidence. Raw applicant values are decrypted only into a
memory-only private property; they have no generic array or serialization
surface and never enter logs, events, envelope payloads, or public read models.
Preparing or replaying the handoff performs no HTTP request, insurer message,
queue dispatch, policy issuance, envelope mutation, financial posting, or
durable outcome write.

Gate 7b adds a durable, authorization-gated state machine:

```text
awaiting_approval -> authorized -> succeeded | failed | indeterminate
```

One completion projection can create one replay-safe request. The campaign
payment-address owner must also hold explicitly configured policy-completion
maker authority. An independently configured checker authorizes the request,
and an explicitly configured outcome recorder may persist one immutable,
provider-neutral terminal outcome. Authority is domain-specific and defaults
to deny; onboarding role, Treasury authority, or Commercial authority does not
implicitly grant it.

The request stores the safe Gate 7a context plus encrypted private applicant
evidence. The outcome stores a redacted result plus optional encrypted private
evidence. Exact replays converge; changed authorization, actors, preparation,
or outcomes fail closed. Request, approval, and outcome events dispatch only
after commit and contain no applicant values. Outcome creation and terminal
request transition are atomic, and failure rolls the request back to
`authorized`.

Gate 7c adds a provider-neutral, read-only transport-readiness boundary. An
authorized request is ready only when an enabled disposition exists for its
exact driver and version and explicitly records the accepted contract,
request/response schemas, idempotency mechanism, connect/response timeouts,
retry policy, ambiguous-outcome policy, reconciliation mode, and configured
credential reference. Missing, disabled, incomplete, wrong-version, and
non-authorized dispositions fail closed. Readiness returns only safe field
names and a deterministic disposition fingerprint; it never returns or reads
a credential value.

This gate deliberately does not define a send method or an HTTP adapter. AUI
has not yet supplied an authoritative endpoint, authentication scheme,
payload schema, response mapping, or reconciliation protocol. Readiness
inspection performs no HTTP request, queue dispatch, insurer message, retry,
policy-document creation, persistence, envelope mutation, or financial
movement. Transport remains disabled until that provider disposition is
accepted and regression-protected.

Gate 7d makes the disposition itself a typed, schema-versioned contract
artifact. The manifest must identify the exact driver version, provider and
contract, HTTPS submission endpoint, authentication scheme, request/response
schema references and SHA-256 digests, idempotency declaration, timeout and
retry policies, ambiguous-outcome and reconciliation rules, credential config
reference, and acceptance provenance. Unknown fields—including embedded
credential values—are rejected. Disabled, unaccepted, malformed,
wrong-identity, invalid-digest, insecure-endpoint, incoherent-timeout, or
credential-unavailable manifests fail closed with a redacted reason.

The normalized manifest produces an order-independent fingerprint. Credential
readiness checks only whether the referenced Laravel configuration value is
present; neither the reference nor its value enters the readiness DTO. The
package still ships no AUI disposition, endpoint, schema, credential, send
method, queue job, or HTTP adapter. This gate provides the intake mechanism;
it does not claim that AUI's contract has been received or accepted.

Gate 7e adds offline operator tooling around that boundary. The package ships a
disabled, unaccepted, secret-free YAML template and two local-only commands:

```text
x-change:policy-completion-transport:template
x-change:policy-completion-transport:validate
```

Template generation requires an exact driver and version, refuses overwrite,
and never writes credential values. Validation accepts local YAML files only,
uses the Gate 7d typed validator, and returns only the exact identity and
deterministic fingerprint. Parse and validation failures are redacted;
endpoint, schema references, acceptance actors, credential references, and
values never appear in output. These commands do not resolve credentials,
activate config, call HTTP, dispatch work, or mutate domain or financial state.

### Gate 8 — Operator experience and lifecycle acceptance

Status: **Complete through Gate 8d authoritative read-only navigation**

Gate 8a introduces a bounded, owner-scoped, read-only lifecycle projection.
It combines the safe references and timestamps for recognized payment,
provisional coverage, completion Pay Code issuance, claim-evidence projection,
maker/checker request, and terminal policy outcome. The projection uses an
explicit versioned DTO and never serializes models or exposes applicant data,
provider transaction keys, actor identities, authorization references,
encrypted snapshots, hashes, private payloads, or credential material.

The list is capped at 100 rows, newest coverage first, and constrains ownership
in SQL. Reading it does not call a provider, resolve transport readiness, emit
events, or mutate envelope, journal, financial, campaign, or policy state.

- Campaign configuration, QR/stamp, transaction list, coverage state,
  envelope state, and completion progress.
- Browser lifecycle: scan QR Ph, settle payment, receive completion Pay Code,
  finish intake, and observe policy status.
- Failure, reversal, duplicate, concurrency, and redaction acceptance.

Gate 8b exposes that DTO through the authenticated Campaigns workspace at
`/x/cockpit/campaigns/policy-lifecycle`. The dedicated page shows payment,
coverage, completion claim, governance, outcome, and attention state while
preserving nullable intermediate stages and a clear empty state. It contains no
approval, dispatch, retry, provider, transport, or policy mutation controls.
The Campaigns endpoint header links to the page through a generated Wayfinder
controller action.

Gate 8c hardens the read-only presentation against representative active,
intermediate, failed, indeterminate, and empty records. Long safe references,
campaign names, stages, result codes, and coverage facts remain contained on
narrow screens; terminal attention states use warning semantics; nullable
facts use contextual wording; and the page exposes the already-safe coverage
amount/status and policy result code. Browser acceptance covers mobile,
tablet, and desktop widths without adding provider calls, approval, retry,
dispatch, transport, or financial controls.

Gate 8d confirms that the issued completion Pay Code is the only lifecycle
reference with an existing authoritative, authenticated, owner-aware detail
surface. Lifecycle rows now offer a conditional Wayfinder link to that
read-only Pay Code detail page. The destination independently enforces voucher
access and returns not found outside the authorized account. Campaign,
coverage, recognition, request, and outcome references remain plain text
because no equivalent owner-scoped detail route exists.

Mutation authority remains a separate decision. This gate adds no approval,
retry, dispatch, provider, transport, or financial behavior.

### Gate 9 — Browser lifecycle scenario narrative

Status: **Complete through Gate 9e maker/checker authorization**

Gate 9a turns the existing AUI browser scenario into a resumable operational
narrative. Creating the scenario persists a versioned run identity inside the
owner-scoped endpoint campaign and redirects the operator to a dedicated run
page. The page polls a read model that reconstructs chronological checkpoints
and safe artifacts from the authoritative campaign, pinned template, issued
Pay Code, claim, payment attempt, collection, and policy-lifecycle records.

The run explicitly distinguishes not started, running, waiting for a person,
waiting for a provider, passed, and failed states. It labels provider evidence,
financial mode, insurer-contract readiness, and demonstration-driver authority.
Private applicant answers, OTP values, QR payloads, provider secrets, account
numbers, and raw provider evidence are not copied into the run projection.

The package now includes and publishes the exact
`aui.personal-accident.provisional-cover@1.0.0` demonstration envelope schema.
It is intentionally labelled demonstration-only and is not an accepted AUI
transport contract. The initial settlement Pay Code continues to use the
claim-intake envelope driver while carrying the exact post-payment coverage
driver identity separately.

Gate 9a does not simulate payment, create provider evidence, credit funds,
recognize a settlement-voucher collection as a campaign payment, bind coverage,
or dispatch insurer transport. It makes the currently missing post-payment
recognition bridge visible instead of claiming that QR generation or voucher
collection completed the policy lifecycle.

## Immediate Next Move

Gate 9b implements the provider-neutral bridge from an already verified
settlement Pay Code collection into campaign payment recognition. It reuses the
canonical provider observation, remains idempotent, rejects cross-campaign or
amount mismatches, and adds no second credit.

The bridge persists an immutable `CampaignPaymentSource` for the collection and
one `CampaignPaymentRecognition`. It does not fabricate a reusable standing-QR
binding for the per-Pay-Code QR Ph. The browser ledger now exposes the safe
recognition reference and stops at an explicit controlled boundary.

Gate 9c now advances an exact AUI demonstration recognition through the
package-owned coverage driver into one immutable provisional coverage record,
one settlement envelope, and one zero-value completion Pay Code. Replay
converges on the same artifacts. This creates no additional collection, wallet
credit, Treasury posting, or provider call.

The browser ledger exposes the safe coverage and completion Pay Code references
and reports `awaiting_completion_claim`. The applicant's completion claim and
all live insurer transport remain separately controlled gates. The driver and
terms remain explicitly demonstration-only; no policy issuance is implied.

Gate 9d exercises that completion Pay Code through the ordinary claim pipeline.
The ledger marks the completion checkpoint passed only after redemption and an
immutable evidence projection exist, then advances to `claim_evidence_ready`.
Exact projection replay is idempotent, the envelope receives only one additional
payload version, and its public payload excludes applicant names, mobile numbers,
and private artifact paths.

The next policy-governance checkpoint remains `waiting_for_person`. The next
controlled gate is maker/checker policy-completion request and approval. Insurer
transport remains blocked on an accepted contract disposition; Gate 9d adds no
provider or financial side effect.

Gate 9e reuses the existing governed policy-completion actions. The campaign
owner acts as maker and creates one replay-safe request from the immutable claim
evidence projection. A separately configured checker records one replay-safe
approval; self-approval remains prohibited by the authoritative action.

The browser ledger exposes separate request and checker-approval checkpoints,
plus only the safe request reference and status. Authorization references,
approval references, applicant evidence, and actor identities remain excluded.
The lifecycle stops at `policy_authorized` and remains running. No transport,
terminal outcome, policy delivery, envelope update, provider call, collection,
or financial movement is performed. The next gate must remain fail-closed until
an accepted insurer transport disposition exists.
