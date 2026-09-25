# Workflow-driven campaigns compass

Updated: 2026-09-25
Plan: [WORKFLOW_CAMPAIGNS_PLAN.md](WORKFLOW_CAMPAIGNS_PLAN.md)

## North star

One template-backed campaign editor discovers versioned workflows. AUI purchase
and a PhilHealth BST-style reviewed submission use the same evidence/gate engine
without pretending their entry methods, decisions or completion outcomes are equal.
The customer stays in the existing Pay Code claim/payment experience.

## Current position

Gates 1 and 2 and standalone integration extraction are implemented. Envelope
v1.3.0 and both integration v1.0.0 releases are published and indexed by Packagist.
x-change's release lock is synchronized against those immutable versions, with
171 affected PHP tests passing. Gate 3c now proceeds to x-change publication and
local sandbox adoption. Cloud deployment is outside this gate.

The legacy BST and canonical stub differ in both format and meaning. Neither is
being overwritten. A separately identified test-only canonical workflow proves
missing documents and amount verification block readiness. This is not official
PhilHealth policy or a production adjudication/payout implementation.

### Artifacts and baseline evidence

- `settlement-envelope/tests/Fixtures/drivers/philhealth.bst.demo/v1.0.0.yaml`
  declares the synthetic evidence/verification workflow.
- `settlement-envelope/tests/Feature/PhilhealthBstDemoTest.php`: 8 passed,
  45 assertions after private test-disk checks. Missing fields/documents, a payload
  flag masquerading as verification, revocation and sequential duplicate references
  are covered. File paths, private visibility and SHA-256 hashes are checked.
- Final full envelope suite: 132 passed, 4 existing skipped, 414 assertions.
  Pint/diff checks passed.
- AUI frontend baseline: 4 files / 23 tests passed. These are component tests,
  not a browser lifecycle run. Initial Vite cache permission error was resolved
  by running with authorized generated-file write access.
- AUI PHP baseline first ran under restricted filesystem access: 73 failed and
  60 passed, with Testbench log/cache permission errors. Rerun with package write
  access resolved those failures: four transport/endpoint baseline files passed
  30 tests / 414 assertions; binding/lifecycle suite passed 103 tests / 731
  assertions (160.74 seconds). Accepted AUI PHP baseline: 133 tests / 1,145
  assertions across five files, executed as two non-overlapping commands.
- Independent companion review found no material AUI inaccuracies in the plan.
  No browser lifecycle, production build or release was performed in this gate;
  no UI/runtime implementation changed.

### Findings the next gates must address

- `EnvelopeService::create()` does not fully JSON-schema validate initial payload;
  checklist field presence is not equivalent to nonempty, valid input. The trusted
  campaign/claim boundary must validate before relying on readiness.
- `amount_verified` source metadata is not reviewer authorization, and a boolean
  cannot carry approved monetary authority. Preserve decision provenance and amount.
- Existing reference uniqueness is sequential/application-level, not a concurrency
  guarantee. Do not advertise concurrency-proof duplicate rejection from this test.
- Existing AUI driver resolution uses scenario metadata. New catalog references
  must deliberately bridge that path; avoid two independent sources of truth.
- Pipedream's current limited demo request excludes applicant evidence, and its
  response declares `document_ready=false`; document transmission is a later contract.

## Gate status

| Gate | State |
| --- | --- |
| 1. Baselines/reconciliation | Complete |
| 2. Typed discovery contracts | Released in envelope v1.3.0 |
| 3. Integration extraction | Integrations released; x-change lock verified; host adoption gate in progress |
| 4. Draft/template editor | Pending |
| 5. Private claim documents | Pending |
| 6. AUI browser runner extension | Pending |
| 7. PhilHealth browser runner | Pending |
| 8. Local/release acceptance | Pending |

## Safety compass

- No live external requests, payments, payouts, claims, SMS or secret reads for
  Gate 1. Offline tests use synthetic records and fake transport/delivery.
- No live authorizations from previous demonstrations are reused.
- Submission ≠ approval ≠ paid; demo result ≠ real insurance.
- No public document storage; no gate side effects; no arbitrary API URLs in drafts.
- No invented legacy normalizer; no silent replacement of old driver semantics.
- No full lifecycle/browser claim until real browser evidence exists.

## Next clean move

Publish the verified x-change candidate, then upgrade the local sandbox from its
normal Composer lock, publish build inputs, run host acceptance and build checks.
Both new packages now resolve normally from Packagist without extra repositories.
Keep the existing authorized completion action in charge of after-commit
processing and durable replay checks.
Host authorization binding, schema-to-form fields, provider product-code mapping
and publication snapshots still need their later integration/editor gates.
Browser scenario runners remain downstream deliverables.
No publication/deployment approval is inferred from this implementation request.

## Gate 2 — typed discovery (2026-09-25)

Settlement-envelope now supplies `WorkflowCatalog`, `WorkflowAccessPolicy`, a
default-deny policy and a YAML-backed catalog. Contexts identify actor/account;
hosts must derive them server-side and bind actual account authorization. Typed
descriptors contain optional plans/coverage, entry methods, notification templates,
document/checklist requirements, gate names and local connection readiness.

Optional `workflow` definitions validate explicit keys/types/bounds, integer minor
amounts and declared message placeholders. Exact loading checks path/metadata
identity and pinned inheritance without latest fallback or driver cache writes.
Existing `load()` and `list()` behavior stays unchanged. A separate discovery
inventory handles actual flat-file versions and opt-out legacy definitions.
Composed children explicitly opt in with `workflow: {}` or overrides; legacy
unpinned inheritance remains outside discovery and keeps its existing loader.
Metadata inherits; plan/message/entry-method lists replace atomically.

`settlement-envelope.connections` is an empty host-configured registry by default.
Checks accept HTTPS HTTP connections with none/bearer auth and bounded explicit
timeouts. Descriptors never include private destinations/tokens. No transport calls
occur. Configured readiness does not imply provider approval or payout authority.

Verification: full package 159 passed / 476 assertions, 4 existing skips; catalog
27 passed / 62 assertions. Pint and `git diff --check` passed. Independent review caught flat-file and legacy-composition compatibility and
the corrected implementation has regression coverage. Existing validation/reviewer
authority gaps above remain explicit; they were not bypassed or falsely fixed.

Package documentation: `settlement-envelope/resources/docs/WORKFLOW_CATALOG.md`.
No host adoption, frontend change, browser test, provider operation or release.

## Working tree handoff

No commits were created. Gate 2 adds catalog/contracts/DTOs/parser/readiness services,
configuration registration, developer documentation and catalog tests in
settlement-envelope; Gate 1 fixture and documents remain alongside them. Existing
untracked `.DS_Store` in settlement-envelope and all original sandbox changes
remain untouched. No temporary Composer overrides or host adoption were made.

## Gate 3a — reference contract bridges (2026-09-25)

Implemented submission/adapter/result contracts, typed outcome statuses and an
explicit exact-version integration registry in settlement-envelope. Registry
resolution uses catalog authorization and configured readiness, but never submits
or authorizes execution. Upstream full suite: **177 passed / 543 assertions**, four
existing skips; Pint and diff checks passed.

x-change now has AUI and local-only BST reference adapters, private submission
wrappers, safe result boundaries and discoverable definitions. AUI retains its
PHP 50 / PHP 5,000 / one-day demonstration terms. Its new named connection must
match the existing accepted transport disposition; the existing call sites remain
unchanged. The reusable AUI parser is mechanically extracted, not silently
strengthened. BST returns only `awaiting_review` without HTTP or persistence.

Host integration exposed an upstream strict DTO validation bug. Passing parsed
workflow data as an array fixes host `validation_strategy=always` without disabling
validation; strict discovery/composition regression is now included upstream.
Static notifications with an empty placeholder list also pass strict DTO
validation; missing lists still fail.

x-change verification: **26 new bridge tests / 85 assertions** and **116 existing
regressions / 1,002 assertions** passed (142 tests / 1,087 assertions across
non-overlapping suites). The existing group covers Pipedream dispatch, demo
responses, the 103-case binding/completion lifecycle and the six-case lead runner.
The initial combined run exposed the strict discovery issues; the final new suite
rerun is green. Pint and `git diff --check` passed for both repositories.

Independent review accepted this **local Gate 3a** boundary, not full Gate 3 or
release readiness. x-change verification uses a process-local PSR-4 override for
unreleased upstream source; no Composer/vendor files were changed. Existing AUI
unknown-field/timestamp parser gaps, reviewer authority and durable BST idempotency
remain explicit follow-ups. No host adoption, browser test, live action, commit,
tag, push or deployment occurred.

Detailed behavior, limitations and exact file inventory:
[WORKFLOW_INTEGRATION_EXTRACTION.md](WORKFLOW_INTEGRATION_EXTRACTION.md).

## Gate 3b — standalone extraction (2026-09-25)

AUI strict response validation and safe one-request transport now live in
`settlement-envelope-aui`. Synthetic BST intake and pending-review processing live
in `settlement-envelope-philhealth`. x-change retains accepted disposition,
credential, execution and durable replay authority through compatibility wrappers.
Neither package activates itself or grants authority when installed.

Local Composer installation of all three candidate dependencies succeeded in an
isolated runtime. Both new manifests validate strictly; repeat install is a no-op.
No manual autoload override is needed. Source and host locks remain unchanged;
normal published adoption must follow dependency publication before committing
the x-change release. The new package directories are not yet Git repositories.

Verification: AUI 86/127, BST 23/54, full envelope 177/543 (four existing skips);
x-change 58/145 focused and 139/1,072 broader, with overlapping bridge tests.
Pint and diff checks pass. No host upgrade, browser run, live request or release.

Full acceptance and release-boundary details:
[WORKFLOW_INTEGRATION_GATE_3B.md](WORKFLOW_INTEGRATION_GATE_3B.md).

## Gate 3c — dependency publication, partially completed (2026-09-25)

User authorized dependency publication, release-lock synchronization and local host
adoption. Cloud deployment and live operations remain outside this gate.

- Published `3neti/settlement-envelope` main `a1b4576` and immutable `v1.3.0`.
  Remote main/tag and Packagist metadata both resolve to
  `a1b4576b7b8e89c8962154aabbc884f743c37af5`.
- Prepared `3neti/settlement-envelope-aui` local main `449e5c7`.
- Prepared `3neti/settlement-envelope-philhealth` local main `dd6796e`.
  Both new local repositories have the intended SSH origin and clean worktrees;
  neither has a release tag or remote push yet.
- Independently installed each integration from ordinary published dependencies,
  resolving envelope v1.3.0. No local path repositories or autoload overrides.
  AUI: 86 tests / 127 assertions; PhilHealth: 23 / 54. Composer strict validation
  passes for both. Their generated development locks/vendor directories are ignored.
- Upstream full suite: 177 / 543, four existing skips; Pint, strict Composer
  validation and diff checks pass. Its unrelated `.DS_Store` remains untracked.
- Read-only companion release review found no concrete blocker in runtime
  dependencies, package activation, secret exposure or circular x-change imports.

**Blocker:** `gh` has no authenticated account; SSH reports repository not found
for both new integration remotes. User was asked to authenticate `gh auth login`
or create the two repositories. No credential searches or alternative account
workarounds were attempted.

**Not performed:** x-change lock synchronization/commit/tag, sandbox Composer
upgrade, build publication, host tests/build/browser acceptance, or any Cloud
operation. The x-change candidate still has the prior documented manifest/lock
mismatch until published dependencies can resolve. Existing host files and lock
remain untouched, including unrelated dirty/untracked work.

Resume in order: integration repositories → v1.0.0 tags → Composer availability
(register on Packagist, or explicitly approved VCS repositories) → x-change normal
lock and regression checks → x-change release → local sandbox upgrade without
silently overwriting its unrelated generated-file edits.

### Gate 3c blocker resolved in the same turn

The user created both GitHub repositories and Packagist entries. Main and v1.0.0
were pushed atomically for each integration. Packagist source references match
`449e5c7cc15bd9059788d8d569730a1cf6fc445d` (AUI) and
`dd6796e2b46efff6d6a7966efc4ff88d6c8eec96` (PhilHealth).
Temporary GitHub Composer entries were removed after registry indexing.

x-change's ordinary release lock now changes only these three dependencies:
envelope v1.2.0 → v1.3.0, AUI new v1.0.0, PhilHealth new v1.0.0. Strict Composer
validation passes; no advisory vulnerabilities were reported. The pre-existing
abandoned `eloquent/enumeration` warning remains. All six focused PHP suites pass
against the published dependency source: **171 tests / 1,133 assertions**.

Sandbox generated Campaigns and CampaignPolicyLifecycle changes were verified
as exact v1.0.48 package projections (ignoring generated headers); other overlapping
untracked campaign files match current package source. A local archive was taken
at `/tmp/xchange-v1050-host-preservation/generated-before.tar` before publication.
Unrelated Pipedream/settings/deployment-skill changes must remain untouched.
