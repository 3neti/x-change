# Workflow-driven campaigns implementation plan

Status: approved direction; implementation proceeds through verified gates.
Date: 2026-09-25
Companion: [working compass](WORKFLOW_CAMPAIGNS_COMPASS.md).

## Objective and boundaries

Campaigns distribute template-backed voucher instructions. Instructions select a
versioned workflow and, where supported, a versioned plan. Each customer journey
receives its own envelope; a completion Pay Code completes that existing envelope.
The public endpoint remains stable while publication revisions govern future
journeys. Existing instructions, terms, evidence and queued notifications do not
silently change when a campaign is edited.

Do not replace `/x/claim`, the voucher execution engine, money movement, or the
existing envelope state machine. Gates are pure readiness decisions, never API
calls, payouts or SMS sends. A product catalog and an API connection are optional
workflow capabilities. A sale may be payment-first or details-first; data intake
does not imply a payment requirement. Use “Collect applications” rather than the
ambiguous “Collect” for non-financial intake.

This effort is not an insurer-approved product specification. All AUI and
PhilHealth reference scenarios are demonstrations until authoritative contracts
and policy terms are separately accepted. Uploaded evidence is not authenticity,
submission is not approval, and approval is not confirmed payout.

## Ownership

| Repository | Responsibility |
| --- | --- |
| `3neti/settlement-envelope` | Existing evidence/checklists/signals/gates; new typed workflow catalog and adapter contracts |
| Integration packages (names provisional) | Workflow definitions, optional plan catalogs, provider-specific adapters and default communications |
| `3neti/x-campaign` | Campaign configuration, availability and publication revisions |
| `3neti/x-change` | Editor, template/voucher integration, claim/payment orchestration, SMS delivery and browser lifecycle runners |
| Host | Private connections, credentials, authorization integration and private evidence storage |

Integrations are `3neti/settlement-envelope-aui` and
`3neti/settlement-envelope-philhealth`. Gate 3b creates them locally after generic
contract verification; publication remains a separate dependency-ordered gate.
An integration package may register multiple workflows; do not create one
Composer package per plan.

## Gate 1 — Baselines and reconciliation

Inventory existing AUI workflow, execution/transport contracts, browser runner,
notifications, idempotency and tests. Establish a canonical **demonstration** BST
fixture without overwriting either existing PhilHealth definition.

### PhilHealth source discrepancy

- `redeem-x/config/envelope-drivers/philhealth-bst.yaml` is legacy top-level
  `id/schema/checklist/gates` YAML, with patient fields, optional medical documents,
  and `payload_present` plus manually verified `amount_verified` gate conditions.
  Although it lists a document checklist item, its gate does not require that item.
- `settlement-envelope/resources/stubs/drivers/philhealth-bst/v1.0.0.yaml` uses the
  canonical format but describes a simpler reference-based workflow. It is not a
  semantic migration of the legacy document/review workflow.
- `settlement-envelope/tests/Unit/Fixtures/PhilhealthBstFixtureTest.php` documents
  parser rejection of the legacy shape. `LegacyPhilhealthBstNormalizerTest.php`
  is a scaffold, not an implemented production normalizer.

Use a separate identity `philhealth.bst.demo@1.0.0`. For this test scenario only,
require claim reference, patient name/mobile, claim form and hospital bill, plus
an explicit amount-verification decision. These document choices are scenario
assumptions, not assertions about actual PhilHealth requirements. Keep requested
and approved amounts distinct in the later reviewer contract. A boolean alone
is not sufficient monetary authority for payout.

Acceptance: executable missing-evidence and unverified-amount tests, no API or
payout action, existing definitions unchanged, AUI baseline still green.

### AUI baseline to preserve

Use the current reusable payment-first campaign path, not the older CLI acquisition
scenario. Its existing product is PHP 50 premium, PHP 5,000 insured amount and
24-hour demonstration coverage. The PHP 500 premium used in design examples is
not a requested mutation of that running demonstration.

`BindCampaignPaymentQrTest.php` exercises recognition, immutable terms/payment
snapshots, coverage, zero-value completion Pay Code, claim evidence projection,
policy preparation, durable notifications and private summaries. Reuse
`LeadCampaignBrowserScenarioCatalog` and `LeadCampaignLifecycleScenarioRunReadModel`
for the new browser runs. The older `campaign_aui_insurance_acquisition` CLI is
issuance-only and is not end-to-end purchase proof. Some current scenario prose
still describes intake-first sequencing; reconcile that in the runner gate.

The Pipedream request intentionally excludes applicant evidence, and the accepted
demo response requires `document_ready=false`. The displayed demonstration summary
is generated by x-change, not an insurer-issued policy document. Extending this
transport to send documents/personal data requires a new explicit contract.
Preserve the exact opt-in automatic-demo path separately from the manual
maker/checker path. Do not force reviews onto AUI just because BST requires one.

## Gate 2 — Typed workflow discovery

Define typed context, workflow, plan, entry method, readiness, requirement and
notification descriptors. A catalog lists and resolves eligible driver versions;
YAML definitions do not themselves implement PHP interfaces. Account policy is
enforced server-side by x-change, including on publication, not only in dropdowns.

Extend the existing parser/DTO and composition rules explicitly; unsupported new
YAML keys currently do not create new behavior. Keep existing drivers compatible.
Resolve configured connections without provider I/O. Never expose endpoints with
embedded secrets, tokens or raw config to the browser. Installation does not imply
activation. Gate expressions do not execute transport operations.

Acceptance: authorized discovery, missing/invalid connection, invalid definition,
version selection, composition and backward compatibility tests.

## Gate 3 — Reference integration extraction

Extract reusable AUI definitions/adapter behind the proven interfaces. Retain the
existing exact-driver/version disposition, credential reference, bounded timeouts,
idempotency key and fingerprint, strict demo response validation and after-commit
processing. Credentials stay in host configuration. Do not introduce generic
automatic retries for economically consequential operations.

Register a canonical BST demonstration workflow using fake/local external
processing. Different workflows may support review and status lookup rather than
immediate purchase completion. Share contracts, not assumptions about sequencing.

Acceptance: common adapter contract suite and workflow-specific negative cases.

## Gate 4 — Template-backed campaign draft

Editor sequence: service → workflow → optional plan → supported entry method →
permitted parameters → claim/stamp and SMS preview → validate → publish.

The template holds the voucher instruction reference; do not introduce a second
independent campaign workflow configuration. Publication resolves and snapshots
driver/plan versions, terms, parameters, notification content and connection
reference. Connection credential rotation may be allowed; destination switching
must not silently redirect existing journeys.

Plan terms are read-only unless the driver explicitly permits a parameter.
Defaults resolve driver → plan → permitted campaign override. Preserve necessary
links/disclaimers and reject unknown placeholders. Message wording follows actual
events, not assumed insurance or payout success.

Payment identity defaults from the account's merchant profile and may be edited
before provider QR creation. Capture requested and provider-confirmed identity;
lock it after successful creation. Profile edits never update printed QRs.

Acceptance: AUI and BST editor behavior; incompatible templates and unavailable
entry methods; version/snapshot isolation; existing campaign tests green.

## Gate 5 — Required documents inside the claim flow

Derive X-Ray and form-flow requirements from resolved instructions. Persist files
privately with server-side limits/type checks, authorized temporary retrieval,
hashes and correlation to the correct claim/envelope. Missing/failed uploads block
submission. Decide explicit malware/quarantine handling before external document
transmission. Avoid duplicate file storage when secure evidence references suffice.

Do not use the older `SyncFormFlowToEnvelope` bypass unchanged: it directly updates
payload and can return a success result containing attachment errors. Build on the
versioned x-change completion evidence projection and envelope service boundaries.
`settlement-envelope.storage_disk` defaults to public; explicitly enforce private
storage for sensitive evidence. UI authorization cannot substitute for backend
authorization. Front/back requirements need separate types or one combined PDF.

Acceptance: missing/invalid/oversized files, unauthorized retrieval, retry safety,
upload failure, incomplete projection and no premature external submission.

## Gate 6 — AUI browser lifecycle runner (package-owned)

Reuse/extend the existing x-change runner rather than create a host-only script.
First use fake provider payment evidence, fake transport and an SMS sink.

1. Select AUI workflow and demonstration plan; create template and draft.
2. Preview terms, messages, requirements; publish; inspect payment QR presentation.
3. Inject a verified test payment through the approved test adapter boundary.
4. Assert exactly one recognition/envelope/completion Pay Code and first SMS.
5. Open captured claim link; complete declared fields/documents in `/x/claim`.
6. Assert evidence readiness, one adapter submission and demo result.
7. Open result link; assert second SMS and accurate campaign metrics.

Negative cases: duplicate observation/job delivery, missing evidence, invalid files,
claim replay, provider timeout/rejection, and edits after publication. No real
coverage claim or live payment is implied by a simulated run.

## Gate 7 — PhilHealth browser lifecycle runner (package-owned)

1. Select canonical BST demo; create template and public endpoint.
2. Start once; collect patient details, reference and required test documents.
3. Assert submission is pending; no payout before decision.
4. Use a separate authorized reviewer browser context, not a reused login shell.
5. Request correction/reject or record amount verification and decision evidence.
6. Assert all required gates before invoking the existing payout execution path
   with a fake adapter.
7. Assert successful payout outcome, notification and journal evidence separately.

Negative cases: duplicate reference, unauthorized/self approval policy as specified,
rejected evidence, missing verification, altered approval amount and payout failure.
An arbitrary signal-setting call is not a reviewer authorization implementation.

## Gate 8 — Local acceptance and controlled release

Run focused/upstream contracts, affected campaign/claim/funding/settlement tests,
production build, asset checks, mobile/desktop browser tests, formatting and diff
checks. Keep local hosts isolated from unrelated work and live financial state.

Every runner produces timestamped expected/actual steps; exact definition versions;
redacted correlation IDs; evidence/gate transitions; notification and financial
outcomes; screenshots; and a private machine-readable artifact index plus report.
Never persist secrets, public document URLs or real personal data in repository
fixtures. Report browser acceptance separately from backend or synthetic UI tests.

Only after local acceptance request package publication and testing deployment.
Live payments, SMS, insurer submission and payout each require explicit scoped
authorization. Opening a runner never starts live operations.

## Next implementation contract decision

Before Gate 2 changes public APIs, review the catalog/adapter DTO shapes with both
reference fixtures. Versioned driver resolution must be exact (no silent latest
fallback for an existing journey). Define evidence provenance and approved-amount
authority before enabling a reviewed payout. Do not “fix” old BST rules in place.
