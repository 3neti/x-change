# Workflow integration extraction — Gate 3a

Date: 2026-09-25
Status: local contract bridge; no runtime activation or release.
Companion: [compass](WORKFLOW_CAMPAIGNS_COMPASS.md).

## Before and after

Previously the typed catalog could describe workflows, but integrations had no
common submission/result boundary. AUI completed through its x-change-specific
preparation and Pipedream service. The canonical BST demonstration was a test
fixture only.

Now settlement-envelope owns `WorkflowSubmission`, `WorkflowIntegrationAdapter`,
`WorkflowIntegrationResult`, `WorkflowIntegrationStatus` and an explicit
`WorkflowIntegrationRegistry`. The registry resolves an exact authorized catalog
entry with configured readiness to an explicitly supplied adapter. Resolution
does not call `submit`, authorize execution, or mutate any record.

x-change supplies two reference bridges and discoverable YAML definitions:

- AUI: payment-QR entry, the existing PHP 50 premium / PHP 5,000 benefit / one-day
  demonstration plan, limited payload transport and demo completion result.
- PhilHealth BST: public-endpoint entry, no product plan, two synthetic document
  requirements and review required. Its local adapter only returns
  `awaiting_review`; it never issues a benefit decision or pays a claim.

The existing AUI call sites remain intact. Its response parser was extracted
without changing its behavior. Existing driver/version disposition, credential
reference, timeouts, headers, private evidence projection, after-commit processing
and durable outcome/replay handling are not replaced by the registry.

## Explicit use and authority boundary

Hosts must bind `WorkflowAccessPolicy` using server-derived account and actor
identity. Default discovery remains deny-all. `ReferenceWorkflowIntegrations` is
an explicitly constructed registry factory, not an automatically activated
provider binding.

Resolve through the catalog/registry, then independently validate the current
execution authority, immutable preparation, evidence and applicable review gates
before submitting. Do not expose adapter `submit()` directly as a controller
endpoint. Registry authorization is discovery authorization, not a transferable
execution approval. A cached or previously resolved adapter is not authorization.

The AUI adapter accepts only `AuiWorkflowSubmissionData` wrapping the existing
private preparation. That private preparation is never serialized wholesale to
HTTP. The existing allowlisted transport request still withholds applicant
evidence. The wrapper does not certify the provenance of arbitrary caller-created
preparations; production callers must continue using the existing preparation and
authorized completion actions.

## Connection bridge

The new AUI bridge requires the named `settlement-envelope.connections.aui-demo`
configuration to match the accepted legacy transport disposition exactly:
destination, bearer credential value and both timeouts. For this compatibility
bridge, `base_url` is the complete accepted submission URL, not a root URL to which
an invented path is appended. Credentials remain host-private configuration.

Missing, malformed or drifted configuration blocks before HTTP. A valid named
connection is not enough: the existing accepted Pipedream disposition remains
mandatory. No migration or automatic copy of credentials occurs.

The existing Pipedream service still makes one request without automatic retry.
Timeouts and invalid responses do not create local completion outcomes. The
existing authorized completion action, not this bridge, owns durable replay
protection. Calling a transport twice is not local exactly-once execution proof.

## BST simulation boundaries

The BST submission has a stable caller key, reference, requested amount, private
patient details and two synthetic evidence hashes. Its fingerprint changes when
the requested amount or evidence changes. The result contains only a deterministic
demo reference, `awaiting_review`, and the demonstration flag.

The adapter refuses execution outside `local`/`testing`. It has no HTTP client,
approval method, approved amount, payout operation, record persistence or status
polling. Equal inputs produce equal references; this is determinism, not durable
idempotency conflict detection. Evidence hashes do not establish authenticity or
prove a private file exists. The later claim/document and reviewer gates must
establish those facts.

## Validation findings retained

- Catalog integration exposed a strict Spatie validation incompatibility:
  embedding a workflow DTO in `DriverData::from()` failed with the host's `always`
  strategy. Passing the validated workflow as an array fixes it without disabling
  validation. Upstream regression covers strict discovery and composition.
- Empty notification placeholder lists now use `present` array validation rather
  than inferred nonempty `required` validation. Static messages are valid; a
  missing placeholder list is still rejected. Host and upstream tests cover this.
- Initial envelope JSON-schema enforcement and reviewer amount/provenance gaps
  remain documented in the compass; this bridge does not bypass them.
- Existing AUI response checks enforce demo constants and authoritative coverage
  equality, but are not complete JSON-schema validation. Unknown fields, relative
  Carbon timestamp expressions and missing timestamps need a separate deliberate
  parser-hardening slice. Extraction preserves existing behavior, not a stronger
  validation guarantee.

## Local verification and adoption

Tests use fake HTTP with stray requests prevented, synthetic data and local
Testbench databases. The upstream package is not yet released, so x-change tests
use a process-local Composer PSR-4 override to load the sibling source. No
`vendor/` file, Composer manifest/lock, host projection or host configuration is
edited. This proves local compatibility, not normal Composer release readiness.

Final counts and worktree status are recorded in the compass. No browser test or
production asset build is needed for this backend-only bridge; browser lifecycle
acceptance remains a later gate. No live requests, payments, claims or SMS occur.

Final verification: settlement-envelope **177 passed / 543 assertions**, four
existing skips. x-change **26 new tests / 85 assertions** plus **116 existing
regressions / 1,002 assertions**, verified in non-overlapping runs. The new tests
include authorized discovery, named connection drift, missing disposition,
cross-workflow rejection, unstable identity, invalid response, HTTP failure,
timeout without retry, BST missing evidence, and production simulation refusal.
Both package formatting and diff checks passed. No full x-change suite or browser
acceptance is claimed.

Worktrees: all Gate 3a changes are uncommitted. The existing Gate 1/2 untracked
files remain in place. settlement-envelope has modified config, DriverData,
DriverService, service provider and new catalog/integration files; its existing
`.DS_Store` remains untouched. x-change has the two tracked changes listed below
(AUI YAML and dispatcher) plus untracked new integration files/reports. The
sandbox's pre-existing dirty/untracked files are unchanged. No Composer override
files, release tags, repository branches or generated host assets were created.

## File inventory

In `3neti/settlement-envelope`:

- `src/Contracts/WorkflowSubmission.php`
- `src/Contracts/WorkflowIntegrationAdapter.php`
- `src/Contracts/WorkflowIntegrationResult.php`
- `src/Enums/WorkflowIntegrationStatus.php`
- `src/Services/WorkflowIntegrationRegistry.php`
- `src/Services/DriverService.php` (strict DTO compatibility correction)
- `src/Data/WorkflowNotificationData.php` (present-but-empty placeholder lists)
- `tests/Feature/WorkflowIntegrationRegistryTest.php`
- `tests/Feature/WorkflowCatalogTest.php` (strict-validation regression)
- `resources/docs/WORKFLOW_CATALOG.md`

In `3neti/x-change`:

- `src/Data/Settlement/AuiWorkflowSubmissionData.php`
- `src/Data/Settlement/AuiWorkflowIntegrationResultData.php`
- `src/Data/Settlement/PhilhealthBstDemoSubmissionData.php`
- `src/Data/Settlement/PhilhealthBstDemoResultData.php`
- `src/Services/Settlement/AuiDemonstrationWorkflowAdapter.php`
- `src/Services/Settlement/PhilhealthBstDemoWorkflowAdapter.php`
- `src/Services/Settlement/ReferenceWorkflowIntegrations.php`
- `src/Services/Settlement/ParseAuiDemonstrationPolicyResponse.php`
- `src/Services/Settlement/DispatchAuiDemonstrationPolicyViaPipedream.php`
- `config/envelope-drivers/aui.personal-accident.provisional-cover.yaml`
- `config/envelope-drivers/philhealth.bst.demo.yaml`
- `tests/Unit/Services/Settlement/ReferenceWorkflowIntegrationsTest.php`
- This report and `WORKFLOW_CAMPAIGNS_COMPASS.md`.

## Gate 3a handoff boundary (historical)

Gate 3 is not fully complete. Standalone integration packages, reusable HTTP
transport extraction, strict transport parser hardening, dependency publication
and ordinary Composer adoption remain ahead. No second AUI engine was invented;
the bridge deliberately reuses its current implementation until those contracts
are accepted. Do not begin a production call-site cutover or campaign editor
activation based solely on this local bridge.

## Worktree snapshots at handoff

### settlement-envelope

```text
 M config/settlement-envelope.php
 M src/Data/DriverData.php
 M src/Services/DriverService.php
 M src/SettlementEnvelopeServiceProvider.php
?? .DS_Store
?? resources/docs/WORKFLOW_CATALOG.md
?? src/Contracts/
?? src/Data/WorkflowContext.php
?? src/Data/WorkflowCoverageData.php
?? src/Data/WorkflowDefinitionData.php
?? src/Data/WorkflowDescriptor.php
?? src/Data/WorkflowNotificationData.php
?? src/Data/WorkflowPlanData.php
?? src/Data/WorkflowReadinessData.php
?? src/Enums/WorkflowEntryMethod.php
?? src/Enums/WorkflowIntegrationStatus.php
?? src/Services/DenyWorkflowAccess.php
?? src/Services/WorkflowConnectionReadiness.php
?? src/Services/WorkflowDefinitionParser.php
?? src/Services/WorkflowIntegrationRegistry.php
?? src/Services/YamlWorkflowCatalog.php
?? tests/Feature/PhilhealthBstDemoTest.php
?? tests/Feature/WorkflowCatalogTest.php
?? tests/Feature/WorkflowIntegrationRegistryTest.php
?? tests/Fixtures/drivers/philhealth.bst.demo/
```

### x-change

```text
 M config/envelope-drivers/aui.personal-accident.provisional-cover.yaml
 M src/Services/Settlement/DispatchAuiDemonstrationPolicyViaPipedream.php
?? config/envelope-drivers/philhealth.bst.demo.yaml
?? docs/architecture/campaign-qr-ph/WORKFLOW_CAMPAIGNS_COMPASS.md
?? docs/architecture/campaign-qr-ph/WORKFLOW_CAMPAIGNS_PLAN.md
?? docs/architecture/campaign-qr-ph/WORKFLOW_INTEGRATION_EXTRACTION.md
?? src/Data/Settlement/AuiWorkflowIntegrationResultData.php
?? src/Data/Settlement/AuiWorkflowSubmissionData.php
?? src/Data/Settlement/PhilhealthBstDemoResultData.php
?? src/Data/Settlement/PhilhealthBstDemoSubmissionData.php
?? src/Services/Settlement/AuiDemonstrationWorkflowAdapter.php
?? src/Services/Settlement/ParseAuiDemonstrationPolicyResponse.php
?? src/Services/Settlement/PhilhealthBstDemoWorkflowAdapter.php
?? src/Services/Settlement/ReferenceWorkflowIntegrations.php
?? tests/Unit/Services/Settlement/ReferenceWorkflowIntegrationsTest.php
```
