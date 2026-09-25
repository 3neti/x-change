# Gate 3b — strict AUI parser and standalone integration adoption

Date: 2026-09-25. Local implementation only; no publication or host cutover.

## Result and ownership

- `3neti/settlement-envelope`: generic discovery, submission/result contracts and
  explicitly authorized registry resolution. The default catalog remains deny-all.
- `3neti/settlement-envelope-aui`: strict request/response validation, safe typed
  result, single-request demonstration transport and versioned workflow resources.
- `3neti/settlement-envelope-philhealth`: validated synthetic BST intake,
  deterministic demonstration reference, pending-review result and workflow YAML.
- `3neti/x-change`: private preparation mapping, accepted disposition and credential
  resolution, execution authority, persistence, after-commit processing and replay
  protection. Compatibility wrappers delegate integration-specific processing.

Standalone source directories are siblings under `/Users/rli/PhpstormProjects/packages/`.
Neither integration depends on x-change. Neither registers a service provider,
routes, automatic activation, notifications or a production approval mechanism.
Existing x-change YAML files remain compatibility projections; regression coverage
compares them with the standalone versioned definitions to prevent silent drift.

## AUI response hardening

The parser requires exactly the eight accepted response fields. It checks literal
schema/status/product and demonstration flags, the `AUI-DEMO-` reference with sixteen
uppercase hexadecimal characters, and timezone-qualified RFC3339 timestamps.
Calendar rollover, relative dates, missing zones, invalid types and unknown fields
are rejected. Equivalent instants with different offsets remain accepted. Expiry
nullability and both authoritative coverage instants must match the preparation.

The regression was red first: eleven cases failed against the old permissive parser.
The corrected positive dispatch fixture now uses a contract-valid policy reference;
no financial or lifecycle expectations were weakened.

The extracted transport validates the existing allowlisted request schema and
reserved workflow version. It permits only HTTPS Pipedream destinations, rejects
userinfo/query/fragment and credential control characters, bounds both timeouts to
1–120 seconds with connect timeout no greater than total timeout, and sends one
POST without redirects or retries. Existing idempotency/fingerprint headers remain.
Exceptions do not echo credentials or provider bodies. x-change still requires the
accepted transport disposition before any request. Named-connection readiness is
not execution authorization.

These URL/token/timeout checks deliberately fail closed on configurations broader
than the demonstration contract; this is not a general arbitrary-server adapter.

## Composer acceptance

x-change now declares `3neti/settlement-envelope:^1.3`,
`3neti/settlement-envelope-aui:^1.0`, and
`3neti/settlement-envelope-philhealth:^1.0`.

Actual Composer resolution and installation succeeded in
`/tmp/xchange-workflow-composer-gate3b`, using temporary path repositories for the
three local candidates and cached, unchanged third-party lock versions. Composer's
generated autoloader is used for x-change regression tests; this replaces Gate 3a's
manual PSR-4 bridge. Strict manifest validation and repeat install dry-run pass.
The existing abandoned `eloquent/enumeration` warning is unrelated and unchanged.

Candidate version aliases in that temporary consumer are **not released tags**.
No path repositories or fake versions were added to durable package metadata.
The source package/host locks and existing vendor directories were not rewritten.
Therefore the changed x-change source is not yet independently installable from
Packagist: publish upstream and integrations first, then regenerate a normal lock.

## Verification evidence

| Check | Result |
| --- | --- |
| Standalone AUI suite, real Composer runtime | 86 passed / 127 assertions |
| Standalone PhilHealth suite, real Composer runtime | 23 passed / 54 assertions |
| Full settlement-envelope suite | 177 passed / 543 assertions; four existing skips |
| x-change strict parser + bridge + dispatch | 58 passed / 145 assertions |
| x-change bridge/resource adoption + generation + binding/completion + lead runner | 139 passed / 1,072 assertions |
| Composer strict validation: both new packages and isolated consumer | Passed |
| Repeat isolated Composer install dry-run | Passed; nothing to change |
| Pint and existing repository diff whitespace checks | Passed |

The two x-change runs overlap on the bridge suite; do not add them as unique
test counts. The second includes the 103-case binding/completion suite and the
six-case lead runner. A final focused check also compares the copied AUI request
schema to its authoritative compatibility resource. This is focused x-change
regression coverage, not its entire package suite or a browser lifecycle run.

## Safety and deferred work

No live network request, credential read, payment, SMS, database mutation in a host,
browser lifecycle run, frontend build, commit, push, tag or deployment occurred.
Tests use synthetic data and fake transport. Existing sandbox changes are preserved.

BST remains local/testing-only and `awaiting_review`; its deterministic reference
is not durable idempotency, document hashes are not authenticity, and no approval
or payout authority is inferred. Initial envelope schema validation and reviewer
authority gaps recorded in the compass remain unresolved downstream gates.

Next: review/publish envelope contracts, create/publish integration repositories,
then lock and release x-change against published versions. Only then upgrade a
host and proceed with editor/browser runner gates. No production insurer contract
or editor activation is implied by this extraction.
