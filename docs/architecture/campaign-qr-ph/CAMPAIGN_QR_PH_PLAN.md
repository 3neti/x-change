# Campaign QR Ph, Provisional Coverage, and Settlement Envelope Plan

Last updated: 2026-09-23

## Objective

Add a second, explicit campaign entry mode in which a reusable campaign QR Ph
collects provider payments directly. A confirmed payment may bind provisional
coverage and open a first-class settlement envelope without pretending that a
policy has already been issued.

This mode coexists with the current endpoint campaign:

| Campaign entry mode | Public artifact | First durable business event |
|---|---|---|
| Endpoint | Web link or ordinary QR | A fresh Pay Code is issued |
| Campaign QR Ph | Reusable provider QR Ph | A provider payment is observed and settled |

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

Status: **Gate 5a complete; driver orchestration pending**

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
this slice. No listener invokes the binder automatically yet; that remains an
explicit driver-orchestration gate. The settlement-envelope package's own
model-level `EnvelopeCreated` event is not currently an after-commit event; no
x-change listener relies on it, and hardening that package event remains
separate package debt.

### Gate 6 — Completion Pay Code

Status: **Pending**

- Issue one zero-denominated Pay Code from the envelope.
- Collect only the driver-declared applicant requirements through `/x/claim`.
- Preserve normal OTP, form-flow, X-Ray, journal, and execution rules.

### Gate 7 — AUI policy completion

Status: **Pending**

- Implement the reserved AUI driver as a versioned adapter.
- Hand completed applicant and settlement-envelope facts to the insurer
  boundary.
- Record policy success/failure without rewriting payment or coverage facts.

### Gate 8 — Operator experience and lifecycle acceptance

Status: **Pending**

- Campaign configuration, QR/stamp, transaction list, coverage state,
  envelope state, and completion progress.
- Browser lifecycle: scan QR Ph, settle payment, receive completion Pay Code,
  finish intake, and observe policy status.
- Failure, reversal, duplicate, concurrency, and redaction acceptance.

## Immediate Next Move

Gate 5a's generic coverage/envelope contract is implemented. The next
controlled move is Gate 5b: define the driver-owned orchestration boundary that
selects explicit coverage terms for a recognition and calls the generic binder.
Keep the reserved AUI adapter, completion Pay Code, applicant intake, policy
issuance, and messaging outside that boundary until their respective gates.
