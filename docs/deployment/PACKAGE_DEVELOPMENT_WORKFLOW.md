# Package development workflow

## The three copies

An extracted package has three distinct representations:

```text
GitHub repository
    │ clone / fetch / push
    ▼
local package repository ───── optional Composer path link ────► host vendor tree
    │ commit + tag                                                │
    └──────────────── production Composer install ────────────────┘
```

- **GitHub repository** is the shared release authority.
- **Local package repository** is where source code is edited, tested, committed, and tagged.
- **Host `vendor/` tree** is an installed dependency. It is disposable and must never be treated as source.

The package does not change from “local” to “remote.” A local repository is a clone with a remote named `origin`. Commits exist locally first; `git push` copies them to GitHub. Composer then installs a selected branch or immutable tag into the host.

## Normal package-first workflow

Use a sibling package workspace rather than editing the host or `vendor/`:

```bash
cd /Users/rli/PhpstormProjects/packages
git clone git@github.com:3neti/x-change.git x-change
cd x-change
git switch -c codex/short-purpose
```

If the repository already exists:

```bash
cd /Users/rli/PhpstormProjects/packages/x-change
git fetch origin --tags
git switch main
git pull --ff-only
git switch -c codex/short-purpose
```

Then:

1. Read the package `AGENTS.md` and relevant package documentation.
2. Confirm the package and host worktrees before editing.
3. Change the owning package source, not its installed or published copies.
4. Add focused tests and run them with the package's documented PHP memory limit.
5. Commit each tested slice in the package repository.
6. Push the branch for review or integration testing.
7. Merge to `main`, rerun release acceptance, and create a new immutable tag.
8. Update the host to that tag, republish required UI build inputs, and run host acceptance.

Example package verification:

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/RelevantTest.php --compact
vendor/bin/pint --dirty --format agent
composer validate --strict
git diff --check
```

## Optional live integration through a local path

Most backend work should be proven in package tests before involving the host. When a change genuinely needs the running host or browser before it is tagged, Composer may temporarily symlink the host's installed package to the sibling clone.

Keep this override in an untracked alternate Composer manifest so the production manifest and lockfile remain unchanged:

```bash
cd /Users/rli/PhpstormProjects/x-change-sandbox
cp composer.json composer.local.json
COMPOSER=composer.local.json composer config repositories.x-change '{"type":"path","url":"../packages/x-change","options":{"symlink":true}}'
COMPOSER=composer.local.json composer require 3neti/x-change:@dev --no-update
COMPOSER=composer.local.json composer update 3neti/x-change --with-all-dependencies
```

While this override is active:

- PHP package changes are visible through the symlink.
- Package Vue files are still package source; the host consumes published build inputs.
- Republish UI changes explicitly, then verify drift and compile the host:

```bash
php artisan vendor:publish --tag=x-change-ui --force
php artisan x-change:doctor --assets --no-interaction
npm run build
```

Do not publish package configuration for ordinary development. Package defaults remain authoritative.

After local integration, remove the alternate manifest and restore the host from its committed lockfile:

```bash
rm -f composer.local.json composer.local.lock
composer install --no-interaction
```

Confirm that Composer no longer resolves x-change from a local path:

```bash
composer show 3neti/x-change --path
```

The path override is a development convenience only. It must never be committed, used by CI, or used in a cloud deployment.

## Release and host adoption

The host should consume an immutable tag, not a mutable development branch. Never move or recreate a published tag.

Example release:

```bash
cd /Users/rli/PhpstormProjects/packages/x-change
git switch main
git pull --ff-only
git tag -a v1.0.0-beta.5 -m "x-change v1.0.0-beta.5"
git push origin main
git push origin v1.0.0-beta.5
```

Adopt it in the host:

```bash
cd /Users/rli/PhpstormProjects/x-change-sandbox
composer update 3neti/x-change --with-all-dependencies --no-interaction
php artisan vendor:publish --tag=x-change-ui --force
php artisan x-change:doctor --assets --no-interaction
php -d memory_limit=2G artisan test --compact tests/Unit/ExternalPackageBoundaryTest.php
npm run build
composer validate --strict
git diff --check
```

Commit the host lockfile, required published UI inputs, and boundary-test version update as a separate adoption commit. Configuration files must not reappear in the host.

## Cross-package changes

When a feature changes a contract and an implementation, release in dependency order:

```text
contract package
    → provider or domain implementation
        → x-change orchestration
            → host Composer adoption
```

For example, an environment descriptor change starts in `emi-core`, followed by NetBank or Paynamics contributors, then x-change, then the host. Each repository receives its own focused tests, commit, push, and tag. Downstream manifests and lockfiles are updated only after the upstream tag exists.

## AI-agent guardrails

An AI agent working on package code must:

1. identify the owning package before editing;
2. use a real clone or isolated worktree of that repository;
3. never edit `vendor/` as durable source;
4. never treat host-published Vue or config files as package ownership;
5. preserve unrelated dirty changes in every repository;
6. keep credentials out of source, tests, logs, `.env.example`, commits, and prompts;
7. commit only a tested slice and name the owning repository in its hand-off;
8. push a branch or tag only after its verification gate passes;
9. restore the host to tagged Composer resolution before final acceptance;
10. report any remaining local path, mutable branch, stale UI mirror, or published configuration override.

## Completion checklist

A package change is complete only when:

- package tests pass in the source repository;
- the commit is pushed to GitHub;
- an immutable release tag exists when the host is adopting the change;
- the host lockfile records that tag and commit reference;
- `composer show ... --path` points to `vendor/`, not the sibling clone;
- required published UI inputs match the tagged package;
- configuration is still package-owned;
- host tests, asset doctor, and production build pass;
- both package and host commits are pushed.
