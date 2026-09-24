# Campaign QR Ph Compass

Last updated: 2026-09-24

## North Star

A reusable campaign QR Ph can accept many provider payments. Each qualifying
settled payment is recognized exactly once, binds authoritative provisional
coverage, creates a first-class settlement envelope containing that coverage
snapshot from inception, and issues one zero-value completion Pay Code.

## Current Position

### Demonstration summary and follow-up SMS — local gate, 2026-09-24

This supersedes the earlier statement that no follow-up presentation exists.
The package now provides a **demonstration policy summary**, not an insurer
policy document. Pipedream's v1 response contract remains unchanged with
`document_ready=false`. No insurer availability or real coverage is inferred.

The continuation is:

1. Completed claim projects declared evidence into the envelope.
2. Existing maker request, independent checker approval, and authorized
   recorder persist a successful `policy_issued_demo` outcome.
3. `PolicyCompletionOutcomeRecorded` queues summary notification after commit.
4. The worker reloads and validates the outcome, then uses the existing
   journaled feedback/SMS pipeline and the original GCash/Maya payer mobile.
5. The SMS opens `/x/demo/policies/{outcome-reference}` through a temporary
   signed URL. The scenario runner's outcome artifact points to the same page
   when enabled. The link is bearer access: recipients should not share it.

New package configuration (all host activation remains a separate gate):

| Environment key | Default | Meaning |
| --- | --- | --- |
| `XCHANGE_DEMO_POLICY_SUMMARY_ENABLED` | `false` | Allow the redacted demo page/link |
| `XCHANGE_DEMO_POLICY_SUMMARY_SMS_ENABLED` | `false` | Queue the follow-up SMS after eligible outcomes |
| `XCHANGE_DEMO_POLICY_SUMMARY_LINK_TTL_HOURS` | `168` | Link lifetime from outcome recording; clamped to 1–720 hours |

Both enable flags must be true for automatic SMS preparation. The page is
read-only, signed, throttled, no-store, no-referrer, and noindex. It shows only
the demo reference, product, authoritative demo dates, recording time, and
explicit non-insurance disclaimer. No applicant answers, contact information,
bank details, claim tokens, or insurer private result are returned. The page
has no Cockpit shell or additional data-entry workflow.

Job identity is `campaign-demo-policy:{outcome-reference}`. Unique dispatch,
overlap protection, and the existing feedback delivery key prevent ordinary
replay from creating a second delivery record. This is not an exactly-once
provider-send guarantee: retain the existing SMS provider ambiguity/runbook
controls. The link expiry does not extend on retries. Disabling the flags
prevents new summary preparation; it does not recall a generic encrypted SMS
delivery job already queued. Inspect exact delivery jobs rather than flushing
the entire queue. SMS failure must never rewrite the policy outcome.

Release acceptance remains pending: publish/adopt the package and built page,
recover `POLI-W23C` claim 145's projection, and complete the governed demo
request/approval/outcome stages with authorized actors. Enable the two flags
only with explicit host/SMS approval. If an eligible outcome predates listener
activation, dispatch `SendDemonstrationPolicySummaryJob` for that exact outcome
reference; do not rerun payment, claim, insurer transport, or outcome issuance.
Inspect its `campaign-demo-policy:` correlation and obtain the link privately.
No automatic maker/checker approval is introduced. Testing Cloud and x-PayOut
are unchanged by this local gate.

Verification: related Campaign QR/lifecycle runner/Pipedream suites passed
82 tests / 742 assertions; shared journaled delivery passed 7 tests / 47
assertions. Strengthened guest-access and duplicate-event checks passed on
focused reruns (21 and 10 assertions). The new Vue test passed (1 test).
Pint and diff whitespace validation passed. No browser acceptance, host
publication, production build, live transport, release, or deployment was
performed; those remain the host-adoption gate.

Implementation inventory (all paths relative to the x-change package):

- `src/Services/Settlement/DemonstrationPolicySummary.php`: eligibility, expiry, signed link, redacted presentation.
- `src/Services/Settlement/CampaignWalletPayerMobile.php`: shared existing wallet-mobile rule.
- `src/Actions/Campaigns/SendCampaignPaymentCompletionSms.php`: reuse that shared rule without changing the first SMS.
- `src/Actions/Campaigns/SendDemonstrationPolicySummarySms.php`: journaled, default-off follow-up SMS.
- `src/Jobs/Campaigns/SendDemonstrationPolicySummaryJob.php`: unique, bounded-retry notification orchestration.
- `src/Listeners/QueueDemonstrationPolicySummary.php`: successful-outcome listener.
- `src/Providers/XChangeServiceProvider.php`: package listener registration.
- `src/Http/Controllers/Web/Claim/DemonstrationPolicySummaryController.php`: read-only public presentation and privacy headers.
- `routes/web.php`: signed, throttled route.
- `config/x-change.php`: activation flags and bounded lifetime.
- `resources/js/pages/x-change/claim/DemonstrationPolicySummary.vue`: public claim-shell summary.
- `src/Services/Leads/LeadCampaignLifecycleScenarioRunReadModel.php`: link the existing outcome artifact.
- `tests/Feature/Actions/Campaigns/BindCampaignPaymentQrTest.php`: security, replay, rollback, delivery, and disabled-state coverage.
- `tests/Feature/Leads/LeadCampaignScenarioRunnerTest.php`: gated artifact link coverage.
- `tests/frontend/DemonstrationPolicySummary.test.ts`: explicit demo wording and PHT presentation.
- This compass: activation, recovery, limitations, and acceptance record.

### Completion evidence corrective slice — 2026-09-24

The user confirmed receipt of the initial SMS and completed `POLI-W23C`.
Read-only testing diagnostics found claim 145 redeemed at 07:20:36 UTC,
with finalized persisted evidence, but no completion evidence projection or
policy request. The browser persisted ancillary Form Flow fields alongside
the six declared requirements. Exact equality against all evidence keys
rejected projection after redemption had already been recorded.

The local correction requires every declared field, ignores ancillary fields
for projection, and retains the complete original claim evidence. Manifest,
private source references, event count, and insurer preparation now use only
the selected evidence. Missing or duplicate declared evidence still fails
closed; replay remains hash-checked and creates no duplicate envelope version.

Release acceptance must recover the existing claim through
`ProjectCompletionClaimEvidence::handle()` using its exact persisted identity,
verify one projection and the declared-field manifest, then prepare the existing
policy workflow. Do not redeem again, request another payment, or reissue the
completion Pay Code. This slice does not send a second SMS automatically.
Publication, testing deployment, and exact-claim recovery remain pending.

The demonstration insurer contract still explicitly returns
`document_ready=false`; there is no downloadable policy URL or policy-link SMS
implementation. A separate controlled gate must define that demonstration
document and notification contract, retaining existing maker/checker authority.
Do not present the fake policy result as actual insurer coverage.

Local verification: Campaign QR and Pipedream transport suites passed 62 tests
/ 442 assertions; compiled-claim and lifecycle-runner suites passed 9 tests
/ 237 assertions. Pint and `git diff --check` passed. All external transports
were mocked; no Cloud record, payment, or SMS was changed by this correction.

Gate 9b recognition bridge is implemented locally. A provider-verified
settlement Pay Code collection can now become an idempotent campaign payment
recognition through an explicit immutable payment source. This path performs no
second collection, wallet credit, Treasury posting, or provider call. The
lifecycle runner exposes the recognition and intentionally pauses before
provisional coverage and completion Pay Code issuance.

Gate 9c is now implemented locally. Exact AUI demonstration runs advance from
the recognition into immutable provisional coverage, a versioned settlement
envelope, and one zero-value completion Pay Code. The operational checkpoint is
`awaiting_completion_claim`; neither a claimed completion nor a live insurer
submission is inferred.

Current gate: **Gate 4 complete — qualifying payment recognition operational**

Current state: **Compatible qualifying payments are recognized exactly once;
nonqualifying or unsafe evidence is durably quarantined**

Prior checkpoint: **Reusable multi-payment characterization complete**

The existing payment-purpose standing-address path is now explicitly
characterized and regression-protected. It persists immutable provider
observations and classifies them without creating Account Funding receipts,
Client Funds credits, or Treasury operations. Repeated synchronization is
idempotent at the provider observation boundary.

At the characterization checkpoint, the installed standing-address persistence
did not retain the optional provider-neutral payer identity DTO. EMI Core
`v2.0.1` and EMI NetBank `v2.3.8` now provide the encrypted evidence contract
and mapping, and x-change is integrated against them pending its release and
host migration. No coverage, settlement envelope, completion Pay Code,
notification, or business application has been added.

The first live provisioning attempt also proved that purpose isolation fails
closed. The sandbox's legacy `netbank-mobile-v1` address for `09173011987` is
already bound to Account Funding, so x-change refused to bind the same provider
destination to purpose `payment`. The sandbox does not currently have the
preferred `netbank-account-hmac-v2` key configured.

After explicit operator authorization, the otherwise-unbound test mobile
`09175180722` produced characterization address
`01M3656EXH0FWZGJKBPGXCNT8J`. Its persisted posture is `payment`,
`observe_only`, `active`, reusable, static P2M, and without an embedded amount.
The pre-payment provider synchronization returned zero observations, zero
settlements, zero suspense cases, and zero applications. It created no Account
Funding receipt. The QR artifact remains private local characterization
evidence.

The operator then paid ₱5.37 through the reusable QR. NetBank returned one
settled InstaPay observation with a stable provider transaction identifier, a
provider operation identifier, exact-destination verification, PHP currency,
and identical occurred/settled timestamps. Two consecutive synchronizations
converged on one immutable observation. There were zero Account Funding
receipts, zero suspense cases, and zero financial applications. Payer identity
was not retained by the standing-address normalization.

The operator then paid ₱6.23 through the same QR. NetBank returned two distinct
provider transaction identities, proving the QR is reusable for independent
payments. The immutable evidence ledger contains three rows: one for the
second transaction and two payload versions for the first transaction. The
later first-transaction version refined `settled_at` by six seconds without
changing amount, currency, or settled status. Immediate replay added no new
row. Economic-payment identity must therefore be distinct from immutable
evidence-version identity.

`ReduceProviderFundingTransactionEvidence` now projects those immutable
versions into `CanonicalProviderFundingTransactionData`. A read-only run over
the live evidence returned exactly two settled transactions—₱5.37 from two
compatible evidence versions and ₱6.23 from one—with verified destinations.
The reducer emits only hashed transaction/operation/request keys and fails
closed on incompatible economic, routing, identity, time, or status changes.

A redacted live shape check proved both payments contain provider-reported
payer name, source account, and institution code, while explicit payer mobile
is absent. The account is not inferred to be a mobile number. EMI Core now has
an additive encrypted payer-evidence schema and NetBank now maps only those
provider-returned fields with `providerVerified=false`. Raw payer values remain
outside metadata, canonical projections, journals, and broadcasts. The
standing-address adapter also consumes NetBank's bounded multi-page iterator.

Scheduled reusable-address observation is now cadence-bound as well as
batch-bound. Active addresses remain eligible for their full active lifetime,
but only never-checked or due addresses enter each fair oldest-first batch.
NetBank history remains bounded to ten 100-row pages and fails closed when the
bounded view is exhausted.

Reversal semantics are intentionally conservative. Adverse or undocumented
status evolution—including any transition away from settled—is incompatible
canonical evidence and cannot trigger a business side effect. Exact NetBank
mapping is deferred until provider documentation or controlled live evidence
shows whether a reversal mutates the credit or arrives as a separate debit.

Campaign entry is now explicit: ordinary endpoint campaigns use
`pay_code_on_open`, while reusable campaign QR Ph uses
`reusable_payment_qr`. The latter binds one exact campaign template revision
to one active payment-purpose Standing Funding Address and its active static QR
artifact. Provider, currency, amount mode, availability, and permitted-payment
rules are hashed into an immutable configuration. Identical retries converge;
conflicting or stale bindings fail closed.

The first Gate 4 boundary is now implemented. Payment-purpose synchronization
looks up the immutable campaign QR binding and evaluates all immutable versions
of a provider transaction through the canonical reducer. Adverse, unknown, and
incompatible evidence converges on an immutable, replay-safe quarantine.
Campaign rows expose only a redacted aggregate `Needs attention` indicator.
Compatible evidence remains observation-only. Tests prove the quarantine path
does not create or mutate coverage, settlement envelopes, Pay Codes, Account
Funding receipts, Client Funds, wallet transactions, funding settlements, or
Treasury operations.

Gate 4b now persists one immutable recognition per binding and canonical
provider transaction. Recognition is serialized by the immutable binding row
and protected by database uniqueness. It applies only generic binding rules:
settled status, verified destination, provider/address/currency match,
availability, fixed/open amount mode, optional amount bounds, allowed rail,
and maximum-payment capacity. Pending evidence remains observation-only;
settled rule failures become durable attention records. A DTO-backed,
redacted event is emitted after commit. Recognition does not credit or move
funds and does not create coverage, an envelope, a Pay Code, or a message.

Redacted evidence: [Gate 1 live characterization report](reports/001-netbank-live-characterization.md).

## Settled Decisions

- Endpoint campaigns and campaign QR Ph are separate explicit entry modes.
- Payment monitoring remains a fact observer, not a business-application
  engine.
- `ProvisionalCoverage` is the authoritative legal/operational fact.
- The settlement envelope is created atomically with coverage and contains an
  immutable coverage snapshot/reference in its first version.
- The initial driver key is
  `aui.personal-accident.provisional-cover@1.0.0`.
- The driver is deferred until provider evidence and generic envelope
  contracts are ready.
- Completion Pay Codes are zero-denominated and use the existing `/x/claim`
  execution model.

## Evidence at This Checkpoint

Protected by `StandingFundingAddressProtocolTest`:

- payment-purpose observations are classified;
- duplicate polling does not duplicate immutable provider observations;
- no Account Funding receipt is created;
- no Client Funds balance changes;
- no Treasury inventory operation is created.
- adverse bound-campaign observations create one durable attention record;
- the synchronization result remains unapplied and no financial credit occurs.

Protected by `BindCampaignPaymentQrTest` and the Cockpit frontend suite:

- adverse, unknown, and incompatible evidence is classified and replay-safe;
- quarantine records are immutable;
- compatible settled evidence does not create attention;
- Cockpit exposes an aggregate attention count without raw provider evidence;
- all measured financial and issuance side-effect counts remain unchanged.
- qualifying fixed and open payments converge on one recognition;
- pending evidence waits without a terminal decision;
- amount and maximum-payment failures enter quarantine;
- replay emits no duplicate recognition event;
- broadcast payloads omit raw provider transaction and payer evidence.

Protected by `CampaignQrPhPlanTest`:

- package ownership is explicit;
- coverage/envelope atomicity is explicit;
- the driver and field-ownership boundaries are explicit;
- live payment is not implicitly authorized.

## Open Questions Requiring Provider Evidence

1. Which NetBank identifier is stable across list/detail/webhook views?
2. Can one QR receive multiple independent transactions until explicit expiry?
3. How are reversals, returns, and late settlement represented?
4. Which payer fields are reliably present and provider-verified?
5. What pagination/window rules prevent missed or duplicated observations?
6. Does NetBank impose an expiry independent of x-change campaign policy?

## Stop Conditions

Stop before business recognition if any of these remain ambiguous:

- provider transaction identity;
- exact campaign/address binding;
- settlement finality;
- amount or currency interpretation;
- replay/idempotency behavior; or
- evidence provenance.

## Next Controlled Gate

Gate 5a is complete. A recognized payment can now be bound explicitly to one
immutable generic `ProvisionalCoverage` and one settlement envelope whose first
payload version already contains the coverage reference and snapshots. The two
facts commit atomically; identical replay returns them, conflicting replay is
rejected, and rollback leaves neither fact behind. The contract validates the
exact driver/version and schema before persistence and emits only redacted,
after-commit x-change audit/broadcast evidence.

Gate 5b is also complete. An explicitly selected, registered
`CampaignCoverageDriverContract` now owns the eligibility decision and coverage
terms. Exact driver/version lookup fails closed, an ineligible decision stops
without persistence, mismatched term identity is rejected, and eligible replay
delegates to Gate 5a's atomic/idempotent binder. The registry ships empty; no AUI
driver or automatic recognition listener has been installed.

Gate 6a is complete. One persisted coverage can now issue exactly one generic,
zero-denominated completion Pay Code through an immutable link. The issuer must
own the bound payment address; coverage/envelope driver identities and the
embedded coverage reference must agree. Identical replay converges, conflicting
replay fails, and voucher/link persistence is atomic. The dedicated
`campaign_coverage_completion` driver uses normal `/x/claim` evidence and
redemption while an explicit execution-only policy suppresses external payout.
Tests prove there is no Account Funding, Treasury, funding settlement, or
non-zero wallet movement.

Gate 6b is complete. A finalized completion claim now creates one immutable
evidence projection and one new envelope payload version. The public payload is
a redacted manifest containing requirement keys, evidence kinds/statuses,
opaque references, hashes, and timestamps; raw answers, mobile numbers, names,
and private artifact paths are excluded. The private correlation snapshot is
encrypted. Exact replay converges without another envelope version or event,
changed evidence fails closed, and forced projection failure rolls back the
envelope version and audit record. The projection emits one redacted
after-commit event and creates no financial movement.

Known package-boundary debt: settlement-envelope's generic `PayloadUpdated`
event is dispatched inside its update transaction. Gate 6b deliberately adds
no listener or external side effect to it. Any package-wide after-commit change
must be handled independently with settlement-envelope regression coverage.

Gate 7a is complete. The reserved
`aui.personal-accident.provisional-cover@1.0.0` adapter is resolved through an
exact-version, duplicate-rejecting registry and validates the full immutable
projection, claim, issuance, coverage, recognition, envelope, and payload
version chain. It returns a deterministic fingerprint and idempotency key.
Safe references and economic facts are separated from applicant values, which
exist only in a private, memory-only preparation property with no generic
serialization surface. Tests prove deterministic replay, unavailable and
duplicate driver rejection, identity mismatch rejection, no HTTP call, no new
domain event, no envelope version, and no financial movement.

Gate 7b is complete. One completion projection can create one durable request
under a default-deny, domain-specific authority. The campaign owner/maker and
independent checker are explicitly configured, and a separately authorized
recorder can persist one immutable `succeeded`, `failed`, or `indeterminate`
outcome. Safe context is queryable; applicant and private result evidence are
encrypted and hidden. Exact replay converges, conflicting replay fails closed,
all three lifecycle events are redacted and after-commit, and forced outcome
failure leaves the request authorized with no partial terminal record. No HTTP,
queue, message, policy document, envelope mutation, or financial movement was
added.

Gate 7c is complete as a provider-neutral, fail-closed readiness boundary.
Only an authorized request with an enabled, complete disposition for its exact
driver version can report ready. The disposition must explicitly cover the
accepted contract and schemas, idempotency, timeouts, retry policy, ambiguous
outcomes, reconciliation, and a configured credential reference. The result
contains only safe missing-field names and a deterministic fingerprint; it
does not expose credential references or values. Default, incomplete,
disabled, wrong-version, and non-authorized cases remain not ready. Readiness
inspection performs no HTTP, queue, persistence, envelope, outcome, or
financial side effect.

Gate 7d is complete as the package-owned intake mechanism. Transport
dispositions are now typed, schema-versioned, exact-driver artifacts with
strict HTTPS endpoint, schema-digest, timeout, unknown-field, credential
reference, and acceptance-provenance validation. Normalization produces a
stable order-independent fingerprint. Embedded credential values are rejected;
runtime readiness checks only presence of the referenced Laravel configuration
secret and does not expose its reference or value. All invalid or absent
artifacts remain fail-closed, with no HTTP or domain mutation.

No authoritative AUI disposition is included. The next gate therefore depends
on AUI supplying its real contract and an authorized architecture acceptance.
Only then can the package add a provider adapter and governed dispatch path.

Gate 7e is complete. Operators can now generate a disabled, unaccepted,
secret-free local YAML template and validate a completed local disposition
through redacted Artisan output. The generator refuses overwrite; the
validator refuses URLs and returns only driver identity plus the stable
fingerprint. Neither command resolves credentials, activates transport, calls
HTTP, queues work, or mutates persistence. The package-owned reference template
is available under `resources/policy-completion-transports` and may be
published with the `x-change-policy-completion-transport` tag.

The external blocker is unchanged: AUI must supply and formally accept the real
contract artifact before any adapter or dispatch implementation begins.

Gate 8a is complete as the first operator-facing backend contract. A bounded,
owner-scoped `x-change.campaign-policy-lifecycle.v1` projection now expresses
the safe lifecycle from payment recognition through provisional coverage,
completion claim evidence, maker/checker governance, and terminal outcome.
Failed and indeterminate outcomes are explicitly attention-bearing. The
projection is read-only and excludes applicant/contact data, provider keys,
actor identities, approvals, snapshots, hashes, private payloads, credentials,
and transport readiness.

Gate 8b is complete. The authenticated Campaigns workspace now links to a
dedicated owner-scoped Policy lifecycle page. It renders only the Gate 8a DTO,
handles empty and nullable intermediate states, highlights failed and
indeterminate outcomes, and provides no approval, retry, dispatch, provider,
transport, or policy mutation controls.

Gate 8c is complete as local presentation acceptance. The lifecycle page now
keeps long safe values inside responsive containers, uses accurate success,
progress, and attention iconography, presents coverage amount/status and safe
policy result codes, and replaces ambiguous nullable wording with contextual
states. Representative lifecycle stages plus the empty state are covered.

Gate 8d is complete. The issued completion Pay Code conditionally links to the
existing authenticated Pay Code detail route through Wayfinder. That route
independently applies owner-aware voucher access. Other lifecycle references
remain noninteractive because they lack an authoritative owner-scoped detail
surface. No mutation or transport authority was introduced.

Gate 9a is complete. Starting the AUI browser scenario now creates a durable,
versioned run identity on its owner-scoped endpoint campaign and opens a
polling execution ledger. The ledger derives its checkpoints and artifact
links from authoritative campaign, template, Pay Code, claim, payment,
collection, and lifecycle records. It declares whether evidence is observed,
whether money is real, and whether the driver or insurer contract is only a
demonstration. Private evidence and provider material are excluded.

The exact AUI provisional-cover YAML is now a package-owned published
demonstration driver. It does not claim an accepted insurer contract. The run
stops honestly after voucher collection until Gate 9b provides a canonical,
idempotent settlement-collection-to-campaign-recognition bridge. No simulated
provider settlement or financial mutation was added in Gate 9a.

Gate 9d is complete. The scenario continues through the ordinary claim pipeline
for the zero-value completion Pay Code. A successful claim creates one immutable
evidence projection, one additional envelope payload version, and the lifecycle
stage `claim_evidence_ready`. Replay is idempotent, while applicant names, mobile
numbers, and private artifact paths stay outside the public envelope and runner
projection.

The run deliberately remains active at a `waiting_for_person` governance
checkpoint. Maker/checker request and approval form the next controlled gate.
Provider transport and policy delivery remain blocked until an authoritative
insurer contract is accepted. No financial or provider side effect was added.

Gate 9e is complete. The scenario owner can produce the canonical maker request,
and an independent configured checker can authorize it through the existing
replay-safe domain actions. The execution ledger shows these as distinct
checkpoints and adds a non-linked safe request artifact. It exposes no applicant
evidence, actor identity, authorization reference, or approval reference.

The accepted stopping state is `policy_authorized` with the scenario still
running. No insurer transport, terminal outcome, policy document delivery,
envelope mutation, provider call, collection, or financial movement occurs.
Transport remains fail-closed until an authoritative insurer contract and
disposition are accepted.

Gate 9f is complete as a local demonstration-response contract. The package
now owns a strict JSON Schema and deterministic responder for the reserved AUI
driver. It returns an `AUI-DEMO-*` reference and explicitly states
`issued_demo`, `demonstration_only`, and `document_ready=false`. Exact replay
returns the same response; wrong-driver and missing-evidence requests fail
closed. Applicant values never enter the safe response or outcome projection.

The responder remains transport-free. Gate 9g now gives the owner-scoped
browser ledger a local demonstration action only when the request is
checker-authorized, the feature is explicitly enabled, and the authenticated
operator has outcome-recorder authority. It delegates persistence to the
existing governed, replay-safe terminal-outcome action.

The lifecycle becomes `policy_succeeded` with
`result_code=policy_issued_demo`; replay creates no second outcome. The ledger
shows only a safe outcome reference and continues to exclude applicant
evidence. No HTTP/Pipedream call, insurer message, policy document, envelope
mutation, collection, or financial movement occurs. The next boundary is an
accepted test-transport disposition, not a real insurer integration.

Gate 9h is complete as a constrained Pipedream test-transport boundary. The
adapter remains dormant unless an exact accepted disposition and referenced
credential are configured. It permits only a `pipedream-test` provider at a
Pipedream HTTPS host, uses the canonical idempotency key, enforces bounded
timeouts, and validates the strict demonstration response without retrying.

The outbound DTO deliberately withholds all applicant/contact/evidence fields.
Only the user-authorized references, amounts, timestamps, fingerprint, and
idempotency key may leave X-Change. This gate is verified with a mocked HTTP
transport: no live request, outcome persistence, policy document, envelope
mutation, collection, or financial movement occurs. A live characterization
requires an explicit endpoint/credential disposition and a separate controlled
execution approval.

Gate 9i is complete locally. The AUI demonstration product is now explicit:
Cubao to Lucena Personal Accident Plan, ₱50.00 premium, ₱5,000.00 insured
amount, and 24 hours of provisional coverage beginning at authoritative
settlement. The driver rejects a payment whose amount or currency does not
match the configured product and no longer mistakes premium for insured
amount. The completion Pay Code uses the normal OTP claim flow to collect name,
mobile, email, address, and birth date.

The next boundary is notification initiation. It must not assume the provider
source account is a mobile number. Before an automatic post-payment SMS can be
enabled, the system needs either provider-verified payer mobile evidence or a
separately verified contact acquisition mechanism tied idempotently to the
recognized payment. Printed reusable QR provisioning and policy-document
delivery also remain separate gates.

Gate 10a is complete locally. Campaign QR Ph now has a package-level
provisioning action built from the same Standing Funding Address machinery used
by the Cockpit Funding QR Ph tab. The pinned campaign revision becomes the
stable HMAC input, while the resulting NetBank address is explicitly
`payment`/`observe_only`. The canonical merchant profile and persisted reusable
QR artifact are reused, then bound immutably to the campaign's amount,
availability, and permitted-payment rules.

Focused tests prove exact replay makes only one provider request and creates
one address/binding, while cross-account and invalid-term attempts fail before
the provider boundary. No financial or messaging side effect is introduced.
Gate 10b is complete locally. The Campaigns page now distinguishes a reusable
payment-QR campaign from an ordinary public endpoint. Only the campaign owner
can invoke the throttled provisioning command. The resulting fixed ₱50 QR Ph
is displayed as a campaign payment stamp with enlarge, download, and print
controls; the raw provider artifact remains confined to the authenticated
owner read model. The AUI scenario declares this entry mode explicitly.

The next controlled move is a real low-value GCash/Maya observation against the
new campaign binding, followed by evidence inspection. No notification or
coverage transition may infer payer identity from an unverified provider
account field.

See [the implementation plan](CAMPAIGN_QR_PH_PLAN.md).

## Campaign payment continuation corrective gate — 2026-09-24

The fixed PHP 50 testing payment was recognized automatically on v1.0.42.
Recognition `01M39140N3ED60FJ9T3KCS5YAD` settled at 06:19:24 UTC and was
recognized at 06:19:50 UTC, without quarantine. It had no coverage because
the standing-address path emitted `CampaignPaymentRecognized` without a
registered lifecycle listener.

The corrective slice registers a package listener that queues the existing
coverage/completion processor on `x-change-funding`, after commit. The job
reloads the durable recognition, uses bounded retries and overlap protection,
and preserves the existing transactional idempotency of coverage and issuance.
Only Campaign QR recognitions enter this listener; the individual settlement
payment path retains its existing continuation. A partial issuance failure
must retry even when a coverage record already exists.

Recovery is explicit and scoped to one recognition:

```bash
php artisan x-change:campaigns:resume-payment 01M39140N3ED60FJ9T3KCS5YAD --no-interaction
php artisan x-change:campaigns:resume-payment 01M39140N3ED60FJ9T3KCS5YAD --dispatch --no-interaction
```

The first command only inspects. The second requests queued continuation,
without another provider payment or collection. The funding queue worker must
be running. Re-inspect after processing to obtain the completion Pay Code.
Use this recovery after a missing enqueue as well as for historical recognized
payments; recognition events themselves are emitted only on first creation.

SMS initiation now uses the existing queued, journaled feedback path. Per the
operator's explicit policy, identifiable GCash/Maya Wallet source accounts in
valid Philippine mobile format are the SMS destinations (09, 639, or +639).
This inference does not change canonical provider identity verification.
Unknown institutions, Maya Bank, and malformed numbers are skipped with a
redacted warning. No new identity/KYC prerequisite is introduced for sending.
Stable recognition-based feedback keys reuse delivery evidence on replay;
the existing provider acceptance/recording crash window is not an exactly-once
transport guarantee. Both funding and feedback workers must run. The exact
recovery command reports persisted SMS status as well as the completion code.
The driver remains demonstration-only; a provisional record does not
prove real insurer coverage or policy issuance. Publication, testing-host
adoption, and replay of the above recognition remain the release acceptance
steps for this corrective slice.

Local verification: Campaign QR suite 55 passed / 397 assertions; journaled
feedback, payment-attempt lifecycle, and settlement collection suites 34 passed
/ 196 assertions; standing-address protocol 29 passed / 277 assertions.
After the final SMS-result guard and inline-driver rejection test, focused SMS
coverage passed 10 tests / 42 assertions. All providers were faked or mocked.
Pint and diff whitespace validation passed. No live SMS or recognition replay
has been executed by this slice. Release and testing deployment remain pending.

### Testing release acceptance — 2026-09-24

The preceding pending-release status is superseded by this acceptance.
With explicit user approval, package commit `23973d23` was published as
`v1.0.43`, and host dependency-only commit `895280ea` was pushed. Deployment
`depl-a2d21f97-169d-4c2f-aea5-4c672b3f4d90` succeeded on the testing instance.
Host integration checks passed (3 tests); isolated build publication, asset
doctor, and production build passed. The temporary checkout required explicit
`APP_ENV=local` because it intentionally contained no `.env`. Existing build
annotation/chunk-size warnings remain. Unrelated sandbox edits were preserved.

The exact recognition above was dispatched once using the recovery command.
Workers produced coverage `01M394991D37G1ZF9635FT4FAN` and completion Pay Code
`POLI-W23C`. Feedback delivery `0bed21b4-eb4e-4e0a-a200-74bdc69cb4a9` is `sent`,
with EngageSpark status `ACCEPTED` at `2026-09-24T07:15:12Z`. Provider acceptance
is not handset delivery confirmation. The link opened successfully in the
in-app browser; no claim was submitted. No new payment or collection occurred.
The x-PayOut instance was not changed. Next: user confirms SMS receipt and
completes the personal-details journey, followed by the fake-insurer policy
response gate. This remains demonstration-only, not proof of real insurance.
