# 3neti/wallet Release Audit

## Status

Audit performed on 2026-07-31 and advanced through the approved release
preparation gate.

```text
package:        3neti/wallet
repository:       git@github.com:3neti/wallet.git
branch:           main
runtime head:     05a141b
cleanup commit:   b9a1706
remote state:     local main equals origin/main
latest tag:       v1.1.0
classification:   release-after-fix
```

The runtime Treasury implementation remained frozen. Approved changes were
limited to Finder cleanup, release documentation, Composer compatibility
metadata, CI, and package tests. No release tag was created.

## Audit result

`3neti/wallet` is functionally healthy at its current `main`, but `v1.1.0`
cannot satisfy x-change’s Treasury runtime.

Current `main` is 22 commits beyond `v1.1.0`:

- 108 changed paths;
- 9,562 insertions and 68 deletions;
- six Treasury migrations;
- Inventory and Position models and append-only operations;
- Treasury contracts, DTOs, read models, and Bavix adapters;
- federated system-principal resolution;
- Account Funding Reserve and commercial waterfall positions; and
- sensitive Treasury metadata sanitization.

This is a real release body, not an incidental patch after `v1.1.0`.

## Working-tree finding

The two dirty paths reported in the publishing matrix were:

```text
docs/architecture/.DS_Store
docs/architecture/treasury/.DS_Store
```

They are untracked Finder metadata. No wallet source or tracked file was dirty.
They did not represent uncommitted Treasury work.

The cleanup was completed and pushed in wallet commit `b9a1706`:

- both Finder files were removed;
- `.DS_Store` is ignored at every repository depth; and
- no runtime or Composer file changed.

The wallet worktree is clean and matches `origin/main`.

## Verification evidence

The existing wallet test suite was run without PHPUnit result caching:

```text
Tests:       91 passed
Assertions:  1,710
Duration:    2.62s
```

Additional checks:

- `git diff --check v1.1.0..HEAD` passed.
- The root Composer manifest passed strict structural validation when lock
  checking was disabled.
- Normal strict Composer validation failed only because an ignored local
  `composer.lock` is stale.
- `composer.lock` is not tracked and is already ignored by the package.

The ignored local lock is development residue, not a release artifact. It must
not be used as evidence for a clean consumer.

## Release-preparation evidence

The approved `v2.0.0-beta.1` preparation lane produced these pushed commits:

| Commit | Slice |
| --- | --- |
| `7251c23` | Rewrite the package contract and document the 2.x beta boundary. |
| `86318b6` | Declare PHP/Laravel support and add compatibility tests and CI. |
| `4ef85ff` | Make the CI database extensions explicit. |
| `4d83759` | Remove a redundant clean-runner Pest flag. |
| `05a141b` | Commit a stable PHPUnit configuration for clean Pest runners. |

The package manifest now declares:

```json
{
    "php": "^8.3",
    "laravel/framework": "^12.0 || ^13.0"
}
```

The package suite now contains 94 tests and 1,733 assertions. It passed locally
on Laravel 12 / Pest 3 and Laravel 13 / Pest 4.

GitHub Actions run `30595844287` passed all four advertised lanes:

- PHP 8.3 / Laravel 12;
- PHP 8.4 / Laravel 12;
- PHP 8.3 / Laravel 13; and
- PHP 8.4 / Laravel 13.

Composer security audit reported no known advisories for the resolved Laravel
13 lane.

## Downstream candidate evidence

The exact untagged wallet candidate commit `05a141b` was exercised without
changing downstream runtime source:

| Consumer | Evidence |
| --- | --- |
| `3neti/cash` | Clean VCS install of `05a141b`; 95 tests and 194 assertions passed. |
| `3neti/emi-netbank` | Current source suite: 154 passed, 28 skipped, 542 assertions. |
| `3neti/voucher` | Current source suite: 420 passed, 28 skipped, 1,287 assertions. |
| `3neti/x-change` | Focused Treasury/wallet integration: 24 tests and 177 assertions passed on Laravel 13. |

The isolated NetBank consumer resolved and installed wallet `05a141b`
successfully. Its clean test run then exposed a separate dependency-release
blocker: current NetBank test code uses
`ProviderLivePreflightFailureCode::DnsResolutionFailed`, but the latest
immutable `3neti/emi-core` release does not contain that enum. The local
NetBank suite passes because it consumes the current emi-core workspace.

This is not a wallet compatibility failure. It proves that the immutable
emi-core release must be advanced before NetBank can satisfy its own clean
consumer gate.

## Release blockers

### 1. Public construction changed after v1.1.0

`SystemUserResolverService` changed from an implicitly no-argument service to a
constructor requiring the Laravel configuration repository.

`TopupWalletAction` also gained a constructor dependency on
`SystemUserResolverContract`.

Container resolution remains supported and the package tests pass, but direct
construction by a consumer can break. This is the primary semantic-versioning
question.

### 2. Downstream constraints currently accept only 1.x

Observed consumers:

| Consumer | Constraint |
| --- | --- |
| `3neti/cash` | `^v1.0` |
| `3neti/emi-netbank` | `^v1.0` |
| `3neti/voucher` | `^1.0` |
| `3neti/x-change` | `^1.1` |
| host application | `^1.1` |

A `2.x` wallet release requires a coordinated downstream release wave. The
runtime compatibility evidence passes, but immutable constraints have not yet
been advanced.

### 3. NetBank's clean consumer requires a newer emi-core release

The exact wallet candidate resolves from GitHub in a clean NetBank consumer.
The consumer cannot finish its suite using the latest immutable emi-core
release because current NetBank tests reference a newer provider preflight enum.
Release emi-core before treating the NetBank clean-consumer gate as complete.

## Version decision

Do not tag the current commit yet.

Two valid paths exist:

### Preferred if direct construction is intentionally unsupported

Cut `v2.0.0-beta.1` after the remaining immutable clean-consumer gate passes.
The manifest, README, package CI, and source-compatibility gates now pass. This
remains the safest semantic statement because the package’s documented scope
and public construction behavior changed materially.

Consequences:

- release `cash`, `emi-netbank`, `voucher`, and `x-change` with compatible
  wallet `2.x` constraints;
- validate the dependency chain in order; and
- do not retain host path-version aliases that describe current `main` as
  `1.1.1`.

### Alternative if a compatible 1.x line is required

Restore and test backward-compatible construction behavior, document Treasury
as an additive capability, and then consider `v1.2.0`.

This path is valid only if compatibility tests prove that existing 1.x
consumers continue to work. The version number must follow evidence, not
deployment convenience.

## Required release-preparation slice

The approved release-preparation slice is complete through source-consumer
validation:

1. Finder metadata is removed and ignored.
2. README and upgrade guidance describe the Treasury package accurately.
3. PHP and Laravel runtime constraints are explicit.
4. Laravel 12/13 CI passes.
5. The `2.x` boundary is approved and tested.
6. Strict Composer validation passes.
7. The full wallet suite passes.
8. Source consumers pass; cash also passes a clean exact-commit install.
9. NetBank's immutable emi-core dependency remains the no-tag blocker.

Each behavior-changing compatibility fix must be a separate tested commit.
Documentation, manifest/CI, and release tagging must remain separate commits or
external actions.

## Gate decision

```text
source integrity:        pass
worktree cleanliness:    pass
remote parity:           pass
package tests:           pass
manifest structure:      pass
release documentation:   pass
direct dependency list:  pass
Laravel version matrix:  pass
semantic version:        v2.0.0-beta.1 approved
source consumers:        pass
cash clean consumer:     pass
NetBank clean consumer:  blocked by immutable emi-core
tag authorization:       blocked
```

The next dependency must not be released around wallet. Wallet is the first
release-order gate and remains `release-after-fix`.
