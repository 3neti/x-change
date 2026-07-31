# x-change Package Publishing and Extraction Hand-off

## Purpose

This document is for the Codex agent working in the package workspace. Its job is to make `3neti/x-change` independently installable, versioned, and deployable outside the host application.

The target outcome is:

```text
host application
    |
    +-- composer require 3neti/x-change:^1.0
            |
            +-- tagged 3neti/* packages resolved from Git repositories,
                Packagist.org, or a private Composer repository
```

The current host application is not yet portable. It uses absolute local `path` repositories pointing at `/Users/rli/PhpstormProjects/packages/*`, and several production dependencies are still constrained to `dev-main`. Those paths will not exist in a clean Laravel Cloud build.

## Research conclusion

Packagist.org is not a Composer requirement. Composer resolves packages from repositories. The available choices are:

| Repository | Suitable use | Decision for x-change |
| --- | --- | --- |
| Git VCS repository | Public or private package source with Git tags | Required migration baseline |
| Packagist.org | Public package index and distribution metadata | Publish public packages when repository ownership and licensing are ready |
| Private Packagist or Satis | Private package index, access control, mirrors | Use if packages remain private |
| `path` repository | Local monorepo development | Keep only for local development or a committed monorepo |
| Artifact repository | Prebuilt ZIP/TAR packages | Optional later optimization |

Composer’s official documentation describes VCS repositories as valid package sources and Git tags as Composer versions. It also warns against mutable branch references when a lockable release is required. Packagist is the main public Composer repository, not the only repository.

References:

- [Composer repositories](https://getcomposer.org/doc/05-repositories.md)
- [Composer versions and constraints](https://getcomposer.org/doc/articles/versions.md)
- [Composer handling private packages](https://getcomposer.org/doc/articles/handling-private-packages.md)
- [Packagist publishing](https://packagist.org/)

## Non-negotiable deployment rules

1. A production package must have a real repository URL or be committed inside the deployed repository.
2. A production package must have a committed `composer.json` at repository root.
3. A release must be identified by an immutable Git tag, preferably `vMAJOR.MINOR.PATCH`.
4. The host application must use a committed `composer.lock` generated from the production repository configuration.
5. Production dependencies must not rely on absolute local paths.
6. Production dependencies should not use `dev-main` once the first deployable release is cut.
7. Package names must remain stable. Do not rename a Composer package merely to move its repository.
8. Package dependencies must use compatible version constraints, not local `path` overrides.
9. Every package release must pass package-level tests and a clean host-application install test.
10. A package is not “published” merely because its directory exists locally. It is deployable only when another clean Composer process can resolve it.

## Current state discovered in this workspace

The host `composer.json` currently contains absolute `path` repositories for package directories outside the host repository. It also requires:

```json
{
    "3neti/emi-core": "^1.0",
    "3neti/emi-netbank": "dev-main",
    "3neti/emi-paynamics": "dev-main",
    "3neti/onboarding": "dev-main",
    "3neti/wallet": "^1.1",
    "3neti/x-change": "dev-main"
}
```

`packages/x-change/composer.json` is already a library manifest, but it still contains local `path` repositories and requires many `dev-main` packages. The extraction task must clean this manifest before moving the package into its own repository.

The read-only inventory has now been frozen in
[X_CHANGE_PACKAGE_DEPENDENCY_MATRIX.md](./X_CHANGE_PACKAGE_DEPENDENCY_MATRIX.md).
That matrix is the release-sequencing authority for the next slice. It records
the observed manifests, configured origins, tags, dirty-worktree blockers,
package surfaces, hosting policy, and immutable release order. When the matrix
and an older observation disagree, re-run the inventory and update the matrix
before changing a package or creating a tag.

## Runtime package closure

The following packages are the initial x-change runtime closure. The agent must verify this list against `composer why`, the package manifests, service-provider loading, and the test suite before changing constraints.

### First-party runtime packages

| Package | Role | Current repository/tag observation | Release priority |
| --- | --- | --- | --- |
| `3neti/emi-core` | Normalized EMI contracts and persistence primitives | Git remote exists; no local `v*` tag observed | 1 |
| `3neti/merchant` | Merchant domain primitives | `v1.1.0` exists | 1 |
| `3neti/wallet` | Local wallet and transfer primitives | `v1.1.0` exists | 1 |
| `3neti/cash` | Cash abstractions used by voucher flows | `v1.2.0` exists | 1 |
| `3neti/contact` | Contact and provider identity support | `v1.1.0` exists | 1 |
| `3neti/money-issuer` | Issuance/funding contracts | Git remote exists; no local `v*` tag observed | 1 |
| `3neti/laravel-model-channel` | Model channel support used by NetBank | `v1.1.0` exists | 1 |
| `3neti/emi-netbank` | NetBank provider implementation | `v2.0.3` exists | 2 |
| `3neti/emi-paynamics` | Paynamics provider implementation | Git remote exists; no local `v*` tag observed | 2 |
| `3neti/laravel-vouchers` | Voucher persistence dependency | `v1.1.0` exists | 1 |
| `3neti/settlement-envelope` | Settlement dependency of voucher | `v1.1.0` exists | 1 |
| `3neti/voucher` | Voucher lifecycle and slice persistence | `v0.10.2` exists | 2 |
| `3neti/onboarding` | Readiness journey and completion hook | No remote was detected locally; repository must be established | 2 |
| `3neti/form-flow` | Claim/issuance form orchestration | `v1.7.15` exists | 1 |
| `3neti/form-handler-kyc` | KYC form handler | `v1.0.2` exists | 1 |
| `3neti/form-handler-location` | Location form handler | `v1.1.1` exists | 1 |
| `3neti/form-handler-otp` | OTP form handler | `v1.0.0` exists | 1 |
| `3neti/form-handler-selfie` | Selfie form handler | `v1.0.1` exists | 1 |
| `3neti/form-handler-signature` | Signature form handler | `v1.1.1` exists | 1 |
| `3neti/x-commerce` | Commerce integration | No local `v*` tag observed | 1 |
| `3neti/x-action` | Action infrastructure | No local `v*` tag observed | 1 |
| `3neti/x-campaign` | Campaign support | No local `v*` tag observed | 1 |
| `3neti/x-feedback` | Feedback delivery support | No local `v*` tag observed | 1 |
| `3neti/x-journal` | Journal integration | No local `v*` tag observed | 1 |
| `3neti/x-ray` | Disclosure and inspection support | No local `v*` tag observed | 1 |
| `3neti/x-rider` | Rider/redirect support | No local `v*` tag observed | 1 |
| `3neti/x-change` | Product orchestration and UI | Currently inside the host repository | 3 |

### Transitive packages requiring verification

These appear in the package dependency graph or lock file and must be classified as public, private, optional, or accidental before release:

- `3neti/hyperverge`
- `3neti/laravel-model-input`
- `3neti/report-registry`
- `3neti/instruction`
- `3neti/x-legal`
- `3neti/settlement-envelope`
- any provider SDK or payment-gateway adapter required by the NetBank runtime

The agent must not publish packages merely because they exist in `/Users/rli/PhpstormProjects/packages`. It must publish the dependency closure actually needed by a clean `3neti/x-change` installation.

## Required work sequence

### Phase 1: Freeze the dependency graph

- Read every `composer.json` in the runtime closure.
- Record package name, PHP/Laravel constraints, direct dependencies, service providers, migrations, assets, and test commands.
- Run `composer show --tree` in the host application and save the relevant first-party subtree.
- Identify dependencies that are currently satisfied only by absolute `path` repositories.
- Identify package-name collisions, duplicate repositories, and packages whose directory name differs from the Composer name.
- Decide whether each package is public or private before selecting Packagist versus private hosting.

Deliverable: a checked-in dependency matrix with one row per package and a clear owner/repository URL.

### Phase 2: Make each package repository-ready

For each package:

- Ensure `composer.json` is at repository root.
- Ensure `name`, `description`, `license`, `autoload`, `autoload-dev`, and `extra.laravel` metadata are correct.
- Remove absolute local paths from package manifests.
- Move package-only development repositories into the root development workflow rather than shipping them as package metadata.
- Ensure all required files are tracked, including migrations, config, stubs, views, Vue assets, translations, and tests.
- Add a README with installation, configuration, migrations, publishing, supported Laravel/PHP versions, and release notes.
- Add a package-level CI check for `composer validate --strict`, static/test checks, and a clean install.
- Preserve existing package names and namespaces unless a migration plan is explicitly approved.

Do not add dependencies merely to make Composer resolution pass. Missing runtime behavior must be fixed in the owning package or explicitly made optional.

### Phase 3: Establish repositories and tags

Create or confirm one Git repository per package, using the existing `3neti/<package>` name where possible.

Tag in dependency order:

```text
emi-core, merchant, wallet, cash, contact,
laravel-model-channel, money-issuer, laravel-vouchers,
settlement-envelope, form-flow and handlers,
emi-netbank, emi-paynamics, voucher, onboarding,
x-action, x-campaign, x-commerce, x-feedback, x-journal,
x-ray, x-rider,
x-change
```

Use the first release tag appropriate to the package’s existing compatibility level. Do not invent a major version solely because the repository moved. If a package has already been consumed at `^1.0`, its first extracted release should normally be a compatible `1.x` tag after confirming API compatibility.

For packages without a stable release, use a temporary tagged release rather than `dev-main` for the first deployment candidate. If a package is not ready for a stable API, use a clearly named pre-release such as `v0.1.0` or `v1.0.0-beta.1` and document why.

### Phase 4: Extract x-change

`3neti/x-change` must become a package repository, not a second copy that remains coupled to the host app.

- Move the contents of `packages/x-change` into its own repository.
- Keep package documentation, tests, migrations, configs, stubs, and source files together.
- Remove all absolute `path` repositories from `packages/x-change/composer.json`.
- Replace `dev-main` requirements with compatible tags or release constraints.
- Keep `x-change`’s host-facing Laravel integration in the package provider and documented install command.
- Ensure published Vue/Blade assets are reproducible from a clean install.
- Ensure the package test suite can run through Orchestra Testbench or the documented host test harness.
- Add a package release note describing the extraction and any host integration changes.
- Tag the extracted package only after a clean external consumer can install it.

The host application should then require `3neti/x-change` through a repository source, for example:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/3neti/x-change"
        }
    ],
    "require": {
        "3neti/x-change": "^1.0"
    }
}
```

Once the package is indexed by Packagist, the explicit VCS repository can be removed. For private repositories, use authenticated VCS access or a private Composer repository.

### Phase 5: Replace the host’s local development wiring

Create a production-safe Composer configuration:

- remove absolute `/Users/rli/PhpstormProjects/packages/*` paths;
- remove `symlink` options from production configuration;
- replace `dev-main` with tags or stable constraints;
- run `composer update` only after all repository URLs and tags are available;
- commit the resulting `composer.lock`;
- verify `composer install --no-dev --prefer-dist --no-interaction` in a clean checkout;
- verify no package is loaded from a local path;
- verify the lock file records Git commit references or distribution archives for every first-party package.

Local development may retain a separate override using `path` repositories, but it must not be the only configuration tested before deployment.

### Phase 6: Packagist decision

Publish to Packagist.org when all of the following are true:

- the package is intended to be public;
- the repository and license are correct;
- no secrets, private endpoints, customer data, or proprietary implementation are present;
- the package has a stable Composer manifest and at least one release tag;
- the package README and support information are ready.

Do not publish a provider package publicly merely to simplify deployment. NetBank credentials and runtime settings remain environment configuration, but the provider implementation itself may still be proprietary.

If packages remain private, use authenticated GitHub VCS repositories for the first deployment or establish Private Packagist/Satis. The consuming application must have non-interactive credentials available during the Cloud build. Never put GitHub or Composer tokens in `composer.json`, `.env.example`, or committed CI files.

## Clean-install acceptance test

The next Codex agent must create a disposable consumer test, not rely only on the current workspace:

1. Create a clean Laravel application or clean checkout with no `/Users/rli/PhpstormProjects/packages` directory.
2. Configure only the intended VCS/Composer repositories.
3. Require the tagged `3neti/x-change` release.
4. Run `composer install --no-dev --prefer-dist --no-interaction`.
5. Run `php artisan package:discover`.
6. Run `php artisan x-change:install --no-interaction --profile=netbank-cloud` once that profile exists.
7. Run `php artisan x-change:doctor --json`.
8. Run the package’s focused test suite and the host smoke tests.
9. Confirm that all expected migrations, config files, UI assets, and routes are available.
10. Confirm that no package resolution depends on a mutable branch or local absolute path.

The test is only successful if it works from a clean environment with the same repository access and credentials available to Laravel Cloud.

## Laravel Cloud hand-off

The deployment agent should treat Composer resolution and application boot as separate gates:

```text
repository access
    -> composer install from lock file
    -> package discovery
    -> migrations and x-change install
    -> x-change doctor
    -> NetBank preflight
    -> lifecycle smoke test
    -> enable issuance
```

If any gate fails, the application must remain non-issuing. A successful Composer install does not prove that NetBank credentials, provider readiness, treasury identity, or source-account liquidity are available.

The initial deployment profile is NetBank only. Paynamics should remain disabled in the first Cloud deployment unless explicitly enabled by a separate release and verified lifecycle run.

## Definition of done

- [ ] Runtime dependency closure is documented and reviewed.
- [ ] Every runtime package has a repository owner and URL.
- [ ] Every runtime package has a valid root `composer.json`.
- [ ] Absolute local `path` repositories are removed from production configuration.
- [ ] All required packages have immutable tags.
- [ ] `dev-main` is absent from the production dependency graph, except for an explicitly approved pre-release.
- [ ] `3neti/onboarding` has a real repository and release tag.
- [ ] `3neti/x-change` is extracted into its own repository.
- [ ] x-change’s package tests pass outside the host application.
- [ ] Host `composer.lock` is regenerated from external repositories.
- [ ] A clean consumer can install x-change without the package workspace.
- [ ] Packagist or private Composer hosting decision is recorded per package.
- [ ] Laravel Cloud can authenticate to every private repository during build.
- [ ] `composer install --no-dev --prefer-dist --no-interaction` passes.
- [ ] `x-change:install`, `x-change:doctor`, and NetBank preflight pass in the deployment environment.
- [ ] Issuance remains blocked when package resolution, provider readiness, or liquidity checks fail.

## Instructions to the next Codex agent

Start with read-only inventory and do not publish or tag blindly. First produce the dependency matrix and classify every package as `release-now`, `release-after-fix`, `optional`, or `not-in-runtime-closure`. Then work from the dependency order above.

Before changing `composer.json`, inspect the package’s current tests and service provider. Before creating a tag, run package validation and a clean install. Before extracting x-change, make sure the host application’s current behavior is captured by tests or a documented smoke checklist.

The immediate milestone is not “everything is on Packagist.” The immediate milestone is “a clean consumer can install a tagged x-change package without the local package workspace.” Packagist publication can follow once the public/private ownership decision is made.
