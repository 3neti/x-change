# x-change Package Publishing Dependency Matrix

## Status

Observed on 2026-07-31 from the local package workspace.

This is a documentation-only release inventory. No source code, Composer
constraint, repository, tag, or remote was changed while producing it.

The immediate publishing objective is:

```text
clean consumer
    -> immutable first-party releases
    -> install 3neti/x-change
    -> no local path repository
    -> no mutable runtime branch
```

The current workspace does not meet that objective.

## Classification

- `release-now` means the package is in the runtime closure, has an origin, has
  a clean observed worktree, and has no known manifest-level path or
  `dev-main` blocker. It still requires its own tests, CI, and clean-consumer
  validation before a tag may be created.
- `release-after-fix` means a concrete repository, worktree, documentation, or
  Composer blocker must be resolved before release validation.
- `optional` means the initial NetBank-only deployment must not require or
  enable the package.
- `not-in-runtime-closure` means the package must not be published merely
  because it exists locally.

These labels are release sequencing decisions, not statements that a package
is production-ready.

## Verified workspace facts

| Surface | Path repositories | `dev-main` runtime requirements | Lock state |
| --- | ---: | ---: | --- |
| Host application | 22 | 4 | stale |
| Embedded `3neti/x-change` manifest | 21 | 18 | stale |

Additional facts:

- `packages/x-change` is part of the `3neti/x-change-sandbox` Git repository.
  It is not an independent `3neti/x-change` repository.
- Every inventoried package has a root `composer.json`.
- All 31 dependency manifests passed `composer validate --strict
  --no-check-publish --no-check-lock`.
- Only `3neti/laravel-vouchers` and `3neti/form-flow` had an observed CI
  workflow.
- Repository visibility was not queried from GitHub. The hosting policy below
  is derived from the committed license and must be confirmed by the owner.
- A configured Git origin proves only local repository wiring. It does not
  prove that a clean deployment identity can read the repository.

## Runtime dependency edges

```text
3neti/x-change
├── cash ── wallet
├── contact ── hyperverge
├── emi-core
├── money-issuer ── emi-core
├── emi-netbank
│   ├── emi-core
│   ├── merchant
│   ├── laravel-model-channel
│   ├── wallet
│   └── money-issuer
├── emi-paynamics ── emi-core                         optional for first deploy
├── voucher
│   ├── cash
│   ├── contact
│   ├── emi-core
│   ├── laravel-model-input
│   ├── laravel-vouchers
│   ├── settlement-envelope
│   └── wallet
├── onboarding
├── form-flow
├── form-handler-kyc ── form-flow, hyperverge
├── form-handler-location ── form-flow
├── form-handler-otp ── form-flow
├── form-handler-selfie ── form-flow
├── form-handler-signature ── form-flow
├── instruction
├── merchant
├── laravel-model-channel
├── report-registry
├── wallet
├── x-action
├── x-campaign
├── x-commerce
├── x-feedback
├── x-journal
├── x-ray
└── x-rider

x-legal                                                   suggested, optional
```

## Release matrix

`Dirty` is the number of paths reported by the package repository at the time
of observation. A non-zero value is an absolute no-tag condition until the
owning work is reviewed and committed or intentionally excluded.

### Immutable release progress — 2026-07-31

The following dependency releases have passed hosted CI and an exact remote-tag
clean-consumer proof:

| Package | Released version |
| --- | --- |
| `3neti/emi-core` | `v2.0.0-beta.1` |
| `3neti/money-issuer` | `v1.1.0` |
| `3neti/wallet` | `v2.0.0-beta.1` |
| `3neti/cash` | `v1.3.0` |
| `3neti/merchant` | `v1.2.0` |
| `3neti/laravel-model-channel` | `v1.1.1` |
| `3neti/hyperverge` | `v2.0.0-beta.1` |
| `3neti/contact` | `v1.2.0` |
| `3neti/laravel-vouchers` | `v1.2.0` |
| `3neti/settlement-envelope` | `v1.2.0` |
| `3neti/laravel-model-input` | `v1.2.0` |
| `3neti/instruction` | `v0.3.0` |
| `3neti/report-registry` | `v1.1.2` |
| `3neti/form-flow` | `v1.8.0` |
| `3neti/form-handler-kyc` | `v1.1.0` |
| `3neti/form-handler-location` | `v1.2.0` |
| `3neti/form-handler-otp` | `v1.1.0` |
| `3neti/form-handler-selfie` | `v1.1.0` |
| `3neti/form-handler-signature` | `v1.2.0` |

The x-change Cockpit source/published-asset boundary is also proven:

- `php artisan x-change:doctor --assets --no-interaction` passes;
- the package-isolated drift and publish-map tests pass with 8 tests and
  32 assertions; and
- host Cockpit differences are generated ownership headers, not canonical
  source missing from the package.

Therefore no host-to-package UI copy is required before extraction. The next
release gates begin with `emi-netbank`, `voucher`, `onboarding`, and the
required `x-*` packages. x-change extraction remains blocked until its local
path repositories and mutable runtime constraints can be replaced by those
immutable releases.

| Package | Closure | License / hosting policy | Configured origin | Latest local `v*` tag | Dirty | Classification | Release blocker or next proof |
| --- | --- | --- | --- | --- | ---: | --- | --- |
| `3neti/emi-core` | runtime | proprietary / private | `3neti/emi-core` | none | 1 | release-after-fix | Review the dirty path, refresh its stale lock or remove the library lock intentionally, run tests, then select a compatible first tag. |
| `3neti/merchant` | runtime | proprietary / private | `3neti/merchant` | `v1.1.0` | 1 | release-after-fix | Reconcile the dirty path and prove whether a release newer than `v1.1.0` is required. |
| `3neti/wallet` | runtime | MIT / public | `3neti/wallet` | `v1.1.0` | 0 at `05a141b` | release-after-fix | [Release preparation advanced](./X_CHANGE_WALLET_RELEASE_AUDIT.md): 94 package tests and the PHP 8.3/8.4 plus Laravel 12/13 matrix pass; source consumers and a clean cash consumer pass. The `v2.0.0-beta.1` tag remains blocked until NetBank can consume an immutable emi-core release containing its current preflight enum. |
| `3neti/cash` | runtime | proprietary / private | `3neti/cash` | `v1.2.0` | 0 | release-now | Run package tests and clean-consumer validation against the intended wallet release. |
| `3neti/contact` | runtime | proprietary / private | `3neti/contact` | `v1.1.0` | 0 | release-now | Validate its `hyperverge` dependency from an external repository. |
| `3neti/money-issuer` | runtime | proprietary / private | `3neti/money-issuer` | none | 0 | release-now | Run tests and API compatibility review before selecting the first immutable tag. |
| `3neti/laravel-model-channel` | runtime | proprietary / private | `3neti/laravel-model-channel` | `v1.1.0` | 0 | release-now | Resolve the stale lock warning and prove NetBank compatibility. |
| `3neti/emi-netbank` | runtime | proprietary / private | `3neti/emi-netbank` | `v2.0.3` | 6 | release-after-fix | Reconcile six dirty paths, add a README, run provider adapter tests, and cut a release containing the exact x-change integration. |
| `3neti/emi-paynamics` | optional first deploy | proprietary / private | `3neti/emi-paynamics` | none | 0 | optional | Remove its package path override before any release; keep disabled for the NetBank-only deployment. |
| `3neti/laravel-vouchers` | transitive runtime | MIT / private-first public candidate | `3neti/laravel-vouchers` | `v1.1.0` | 0 | release-now | Resolve the stale lock warning and run the existing CI workflow. |
| `3neti/settlement-envelope` | transitive runtime | MIT / private-first public candidate | `3neti/settlement-envelope` | `v1.1.0` | 0 | release-now | Resolve the stale lock warning and validate driver/resource packaging. |
| `3neti/voucher` | runtime | proprietary / private | `3neti/voucher` | `v0.10.2` | 0 | release-after-fix | Remove its path repository and `3neti/cash:dev-main`; validate the replacement constraints before tagging. |
| `3neti/onboarding` | runtime | proprietary / private | **missing** | none | 0 | release-after-fix | Establish the `3neti/onboarding` repository, ownership, access policy, and first immutable release. |
| `3neti/form-flow` | runtime | MIT / private-first public candidate | `3neti/form-flow` | `v1.7.15` | 8 | release-after-fix | Reconcile eight dirty paths and run its existing CI workflow before deciding the next tag. |
| `3neti/form-handler-kyc` | runtime | MIT / private-first public candidate | `3neti/form-handler-kyc` | `v1.0.2` | 4 | release-after-fix | Reconcile dirty paths and validate against the selected form-flow and HyperVerge releases. |
| `3neti/form-handler-location` | runtime | MIT / private-first public candidate | `3neti/form-handler-location` | `v1.1.1` | 4 | release-after-fix | Reconcile dirty paths and validate against the selected form-flow release. |
| `3neti/form-handler-otp` | runtime | MIT / private-first public candidate | `3neti/form-handler-otp` | `v1.0.0` | 2 | release-after-fix | Reconcile dirty paths, resolve the stale lock warning, and prove production OTP behavior. |
| `3neti/form-handler-selfie` | runtime | MIT / private-first public candidate | `3neti/form-handler-selfie` | `v1.0.1` | 3 | release-after-fix | Reconcile dirty paths and validate against the selected form-flow release. |
| `3neti/form-handler-signature` | runtime | MIT / private-first public candidate | `3neti/form-handler-signature` | `v1.1.1` | 3 | release-after-fix | Reconcile dirty paths and validate against the selected form-flow release. |
| `3neti/x-commerce` | runtime | MIT / private-first public candidate | `3neti/x-commerce` | none | 21 | release-after-fix | Reconcile 21 dirty paths, run pricing/waterfall tests, and select a first release only after API review. |
| `3neti/x-action` | runtime | proprietary / private | `3neti/x-action` | none | 0 | release-now | Run tests and select a first immutable pre-release or compatible stable tag. |
| `3neti/x-campaign` | runtime | proprietary / private | `3neti/x-campaign` | none | 0 | release-now | Run the campaign/import/authorization suite and select a first immutable release. |
| `3neti/x-feedback` | runtime | proprietary / private | `3neti/x-feedback` | none | 0 | release-after-fix | Add a README, validate email/SMS queue contracts, then select a first immutable release. |
| `3neti/x-journal` | runtime | proprietary / private | `3neti/x-journal` | none | 0 | release-now | Run append-only journal tests and select a first immutable release. |
| `3neti/x-ray` | runtime | proprietary / private | `3neti/x-ray` | none | 0 | release-after-fix | Add a README, validate resource publishing, then select a first immutable release. |
| `3neti/x-rider` | runtime | proprietary / private | `3neti/x-rider` | none | 0 | release-now | Run Rider rendering and resource-publishing tests before selecting the first tag. |
| `3neti/instruction` | runtime | MIT / private-first public candidate | `3neti/instruction` | `v0.2.2` | 0 | release-now | Run pricing tests and verify x-change’s declared `^0.2` API use. |
| `3neti/report-registry` | runtime | MIT / private-first public candidate | `3neti/report-registry` | `v1.1.1` | 0 | release-now | Resolve the stale lock warning and run report-driver tests. |
| `3neti/laravel-model-input` | transitive runtime | proprietary / private | `3neti/laravel-model-input` | `v1.1.0` | 0 | release-now | Run tests and validate voucher compatibility. |
| `3neti/hyperverge` | transitive runtime | MIT / private-first public candidate | `3neti/hyperverge` | `v1.1.0` | 0 | release-now | Validate contact and KYC handler compatibility without live credentials. |
| `3neti/x-legal` | suggested only | MIT / undecided | **missing** | none | 2 | optional | Keep out of the required closure; establish a repository only if the suggested integration becomes a runtime dependency. |
| `3neti/x-change` | product package | MIT / private-first public candidate | **embedded in `x-change-sandbox`** | none | host worktree dirty | release-after-fix | Complete dependency releases, extract history into its own repository, remove 21 path repositories and 18 `dev-main` requirements, then prove a clean consumer. |

## Package surface inventory

Counts establish whether an extraction or release must account for migrations,
configuration, resources, and tests. They do not prove those files are
correctly published.

| Package | Migrations | Config files | Resource files | Test files | README | CI |
| --- | ---: | ---: | ---: | ---: | --- | --- |
| `emi-core` | 10 | 1 | 0 | 25 | yes | no |
| `merchant` | 4 | 1 | 0 | 9 | yes | no |
| `wallet` | 6 | 2 | 0 | 27 | yes | no |
| `cash` | 2 | 1 | 0 | 24 | yes | no |
| `contact` | 3 | 1 | 0 | 10 | yes | no |
| `money-issuer` | 1 | 3 | 2 | 6 | yes | no |
| `laravel-model-channel` | 3 | 1 | 0 | 6 | yes | no |
| `emi-netbank` | 10 | 3 | 1 | 37 | **no** | no |
| `emi-paynamics` | 0 | 1 | 0 | 24 | yes | no |
| `laravel-vouchers` | 5 | 0 | 0 | 29 | yes | yes |
| `settlement-envelope` | 7 | 1 | 21 | 11 | yes | no |
| `voucher` | 0 | 3 | 7 | 143 | yes | no |
| `onboarding` | 1 | 1 | 1 | 21 | yes | no |
| `form-flow` | 0 | 2 | 0 | 21 | yes | yes |
| form handlers, combined | 0 | 5 | 2 | 16 | yes | no |
| `x-commerce` | 0 | 1 | 0 | 6 | yes | no |
| `x-action` | 0 | 1 | 0 | 13 | yes | no |
| `x-campaign` | 9 | 1 | 0 | 194 | yes | no |
| `x-feedback` | 2 | 1 | 0 | 28 | **no** | no |
| `x-journal` | 4 | 1 | 0 | 29 | yes | no |
| `x-ray` | 0 | 1 | 8 | 6 | **no** | no |
| `x-rider` | 0 | 1 | 19 | 38 | yes | no |
| `instruction` | 2 | 1 | 2 | 23 | yes | no |
| `report-registry` | 0 | 1 | 1 | 10 | yes | no |
| `laravel-model-input` | 2 | 1 | 0 | 6 | yes | no |
| `hyperverge` | 1 | 3 | 0 | 19 | yes | no |
| `x-legal` | 0 | 1 | 0 | 2 | yes | no |
| `x-change` | 41 | 6 | 242 | 1,095 | yes | no |

## Immutable release order

The following order respects observed first-party dependencies:

1. `wallet`
2. `cash`
3. `emi-core`
4. `merchant`
5. `laravel-model-channel`
6. `money-issuer`
7. `hyperverge`
8. `contact`
9. `laravel-vouchers`
10. `settlement-envelope`
11. `laravel-model-input`
12. `instruction`
13. `report-registry`
14. `form-flow`
15. form handlers
16. `emi-netbank`
17. `voucher`
18. `onboarding`
19. `x-action`
20. `x-campaign`
21. `x-commerce`
22. `x-feedback`
23. `x-journal`
24. `x-ray`
25. `x-rider`
26. `x-change`

`emi-paynamics` is a separate optional release lane and must not block the
initial NetBank-only consumer. `x-legal` remains outside the required closure.

The wallet gate has completed its read-only audit. It remains first in the
release order and must not be bypassed. See
[X_CHANGE_WALLET_RELEASE_AUDIT.md](./X_CHANGE_WALLET_RELEASE_AUDIT.md).

## Per-package release gate

No tag may be created until all boxes for that package pass:

- [ ] The observed dirty worktree has been reviewed.
- [ ] The root manifest passes strict validation.
- [ ] No shipped `path` repository remains.
- [ ] No unapproved mutable runtime branch remains.
- [ ] The configured origin, repository owner, and deployment access are
      confirmed.
- [ ] Public/private visibility and licensing are approved.
- [ ] README installation and configuration instructions are current.
- [ ] Package tests pass from the package repository.
- [ ] Required migrations, config, resources, and providers are included.
- [ ] A clean Composer process can resolve the proposed tag.
- [ ] The consuming package tests pass against that immutable release.

## Clean-consumer gate

The package release chain is complete only when a disposable consumer with no
access to `/Users/rli/PhpstormProjects/packages` can:

1. authenticate to every private repository;
2. resolve only immutable first-party releases;
3. install with `--no-dev --prefer-dist --no-interaction`;
4. run package discovery;
5. run `x-change:install` with the NetBank Cloud profile;
6. pass `x-change:doctor --strict`;
7. pass Treasury and NetBank preflight;
8. publish reproducible UI assets;
9. expose the expected migrations and routes; and
10. remain non-issuing when any readiness gate fails.

## Frozen boundaries

During this inventory slice:

- no application or package code may change;
- no Composer manifest or lock file may change;
- no repository may be created or rewritten;
- no remote may be added or changed;
- no tag may be created or pushed;
- no package may be submitted to Packagist; and
- no deployment credential may be written to source control.

The next authorized slice is package-by-package release preparation beginning
with `wallet`, not x-change extraction.
