# 3neti/wallet Release Audit

## Status

Read-only audit performed on 2026-07-31.

```text
package:        3neti/wallet
repository:       git@github.com:3neti/wallet.git
branch:           main
runtime head:     3469f5f4938ace13ea1573bf7c2d01962bfb3444
cleanup commit:   b9a1706
remote state:     local main is one cleanup commit ahead of origin/main
latest tag:       v1.1.0
classification:   release-after-fix
```

No wallet source, configuration, manifest, lock file, repository, remote, or
tag was changed during this audit.

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

The cleanup was completed in wallet commit `b9a1706`:

- both Finder files were removed;
- `.DS_Store` is ignored at every repository depth; and
- no runtime or Composer file changed.

The wallet worktree is clean after that commit. The cleanup commit has not been
pushed by this documentation slice.

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

## Release blockers

### 1. The README describes the pre-Treasury package

The README currently says:

- the package does not own or ship migrations;
- the package is stateless;
- multi-account and audit behavior are future extensions; and
- the package is only integration glue over Bavix.

Current `main` loads six package-owned Treasury migrations and owns Inventory,
Position, operation, read-model, and reconciliation primitives. The README must
be rewritten before release.

### 2. Runtime framework dependencies are implicit

The runtime source imports Laravel configuration, database, broadcasting,
events, queue, and support contracts, but the manifest declares only:

- `spatie/laravel-data`;
- `lorisleiva/laravel-actions`;
- `brick/money`; and
- `bavix/laravel-wallet`.

The package currently relies on transitive Illuminate dependencies. Release
preparation must declare the framework components it directly consumes and
state supported PHP and Laravel versions.

### 3. Laravel 13 is not tested by the package manifest

The development manifest uses:

```json
{
    "orchestra/testbench": "^10.3",
    "pestphp/pest": "^3.8"
}
```

That is the Laravel 12 test generation. The x-change host currently exercises
wallet on Laravel 13, but the wallet repository needs its own supported-version
matrix. Its test dependencies and CI must prove every advertised version.

### 4. Public construction changed after v1.1.0

`SystemUserResolverService` changed from an implicitly no-argument service to a
constructor requiring the Laravel configuration repository.

`TopupWalletAction` also gained a constructor dependency on
`SystemUserResolverContract`.

Container resolution remains supported and the package tests pass, but direct
construction by a consumer can break. This is the primary semantic-versioning
question.

### 5. No package CI was observed

The wallet repository has no observed `.github/workflows` release validation.
A release must not depend on tests run only from this workstation.

### 6. Downstream constraints currently accept only 1.x

Observed consumers:

| Consumer | Constraint |
| --- | --- |
| `3neti/cash` | `^v1.0` |
| `3neti/emi-netbank` | `^v1.0` |
| `3neti/voucher` | `^1.0` |
| `3neti/x-change` | `^1.1` |
| host application | `^1.1` |

A `2.x` wallet release requires a coordinated downstream release wave. A
compatible `1.2.x` release requires explicit backward-compatibility work and
tests first.

## Version decision

Do not tag the current commit yet.

Two valid paths exist:

### Preferred if direct construction is intentionally unsupported

Cut `v2.0.0-beta.1` after the manifest, README, CI, and clean-consumer gates
pass. This is the safest semantic statement because the package’s documented
scope and public construction behavior changed materially.

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

Code remains frozen. When package release changes are explicitly authorized,
the wallet slice should contain only:

1. remove and ignore Finder metadata;
2. rewrite README installation, migration, Treasury, and upgrade guidance;
3. declare PHP and direct Illuminate runtime constraints;
4. add a Laravel 12/13 package CI matrix;
5. add backward-compatibility tests or approve the `2.x` boundary;
6. run strict Composer validation without local path repositories;
7. run the full wallet suite;
8. install the proposed wallet release in clean consumers for `cash`,
   `emi-netbank`, `voucher`, and `x-change`; and
9. create a tag only after those consumers pass.

Each behavior-changing compatibility fix must be a separate tested commit.
Documentation, manifest/CI, and release tagging must remain separate commits or
external actions.

## Gate decision

```text
source integrity:       pass
worktree cleanliness:   pass
remote parity:          pending cleanup push
package tests:          pass
manifest structure:     pass
release documentation:  fail
direct dependency list: fail
Laravel version matrix: fail
semantic version:       decision required
clean consumer:         not run
tag authorization:      blocked
```

The next dependency must not be released around wallet. Wallet is the first
release-order gate and remains `release-after-fix`.
