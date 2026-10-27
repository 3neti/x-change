# Package development workflow

New AI-agent sessions must begin with the reusable
[AI agent package onboarding protocol](./AI_AGENT_PACKAGE_ONBOARDING.md). It
turns the package-first workflow, generated-host boundary, safety gates, and
required hand-off into an explicit session contract.

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
```

## Branch and pull request SOP

Treat every task as an isolated branch, including AI-agent tasks. Before
editing, read `AGENTS.md`, inspect `git status --short --branch`, check the
worktree owner, and identify any unpushed commits. Preserve all existing work.
The shared local `main` may be dirty or behind GitHub; never switch it, pull
into it, reset it, clean it, or use it as the branch base.

Fetch GitHub `main`, then create a uniquely named worktree from that remote
ref. Use `feature/<purpose>`, `fix/<purpose>`, or `docs/<purpose>` for the branch.
In a clone whose GitHub remote is named `origin`:

```bash
cd /Users/rli/PhpstormProjects/packages/x-change
git fetch origin --tags
git worktree add -b feature/short-purpose ../x-change-short-purpose origin/main
cd ../x-change-short-purpose
git status --short --branch
```

If the branch or worktree name is taken, choose another descriptive name.
Do not reuse another agent's worktree. Keep the task's changes and commits in
that worktree. If a task starts from an existing branch, confirm its current
remote base and ownership before continuing. Never cherry-pick an unpushed
local commit simply because its subject resembles a GitHub commit; compare
content and ancestry first.

Then:

1. Read the package `AGENTS.md` and relevant package documentation.
2. Confirm the package and host worktrees before editing.
3. Change the owning package source, not its installed or published copies.
4. Add focused tests and run them with the package's documented PHP memory limit.
5. Commit each tested slice on the task branch; check `git diff --check` and
   confirm the worktree is clean before handoff.
6. Push only when the task authorizes it. Open a pull request to GitHub `main`
   and report its scope, changed contracts, tests, and risks. Require the PR's
   `PHP tests` and `Vue tests` checks to pass. A branch push run alone is not
   sufficient.
7. The repository owner reviews and squash-merges the PR, or explicitly
   delegates that specific PR's merge to an agent after its checks pass. AI
   agents using the shared `3neti` credential do not merge by default or
   bypass the main ruleset. GitHub cannot count a PR author's own approval, so
   the ruleset requires PR and CI without an approval count; the owner controls
   the final merge.
8. Verify CI on the resulting `main` commit, then perform release acceptance
   and create a new immutable tag when the release is authorized.
9. Update the host to that tag, republish required UI build inputs, and run
   host acceptance.

After merge, verify the PR is merged and its diff is present on `main` before
deleting its remote ref. A squash merge changes commit identity, so do not
rely on `--merged` alone. Remove
only the task's own worktree after confirming it is clean. Audit divergent
branches and their tags before deleting them; never bulk-merge old release
branches or discard another agent's work.

Example package verification:

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/RelevantTest.php --compact
vendor/bin/pint --dirty --format agent
composer validate --strict
git diff --check
```

## CI feedback and release evidence

Full CI runs for pull requests targeting `main`, pushes to `main`, and manual
workflow dispatch. Feature branch pushes do not also run a duplicate suite.
Open a PR to obtain required checks; use manual dispatch for deliberate branch
verification. A new PR commit cancels the previous run for that PR only. Main
and manual runs have independent concurrency groups.

`PHP tests` remains the required check, backed by four PHP shards and a separate
verification job. Every shard uses the locked Pest version's native `--shard=N/4`
for discovery and six parallel workers for execution. A small runner translates
the selected inventory into explicit test files and an exact class filter because
Pest 4.7.5 leaks
`--processes` into its serial discovery subprocess when combined with `--shard`.
Explicit files also avoid nonzero parallel exits from unrelated, fully filtered
test files. A temporary PHPUnit configuration preserves the original settings
and replaces only suite discovery with the selected files; it is removed after
execution. The runner fails if a selected class cannot be mapped to its test
file. No vendor changes or dependency upgrades are needed. The verification job runs the existing four-file
regression check followed by the serial installer preflight. Existing excluded
groups remain unchanged. All shards finish even when one fails. The aggregate
check rejects failed, cancelled, skipped, missing, or malformed evidence.

Each shard records the unsharded and selected test inventories. The aggregate
checks their exact union, rejects overlaps, compares execution counts against
selected inventories, and rejects duplicate executed identities across shards.
The preliminary regression check intentionally repeats tests from the full suite.

JUnit reports and inventories are uploaded as separate artifacts for 14 days,
including on failure. The `PHP tests` job summary lists test results, the slowest
20 tests, and completed-job elapsed times when the GitHub API is available.
Summed test durations are not parallel wall time; summed job elapsed times are
runner consumption estimates, not billing totals. Initially shards use native
deterministic partitioning without historical timing files. Review actual
imbalance before introducing timing-based partitioning. The feedback target is
under 30 minutes excluding queue time; do not reduce coverage to meet it.

Version-tag pushes run lightweight release verification rather than repeating
full CI. The tagged commit must be an ancestor of current GitHub `main`, and its
latest matching main-push CI run must have completed successfully with successful
`PHP tests` and `Vue tests` jobs. PR, manual, other-commit, and pending runs are
not release evidence. Missing evidence fails verification. This workflow does
not create tags, publish packages, or deploy. Keep the existing owner-controlled
merge and explicit release authorization requirements.

CI changes are developed and reviewed separately from application features.
After merge, verify the new main run before starting dependent feature branches.
Local CI tooling checks are:

```bash
python3 -B -m unittest discover -s scripts -p 'test_ci_*.py' -v
node --test scripts/ci-release-evidence.test.mjs
```

When restoring a temporary path installation, republish and rebuild from the
locked package as shown below. Composer restoration alone leaves previously
published frontend files in the host.

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
php artisan x-change:publish --scope=build --force --verify --no-interaction
php artisan x-change:doctor --assets --strict --no-interaction
npm run build
```

Do not publish package configuration for ordinary development. Package defaults remain authoritative.

After local integration, remove the alternate manifest and restore the host from its committed lockfile:

```bash
rm -f composer.local.json composer.local.lock
composer install --no-interaction
php artisan x-change:publish --scope=build --force --verify --no-interaction
php artisan x-change:doctor --assets --strict --no-interaction
npm run build
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
git fetch origin --tags
git worktree add --detach ../x-change-release origin/main
cd ../x-change-release
git tag -a v1.0.0-beta.5 -m "x-change v1.0.0-beta.5"
git push origin v1.0.0-beta.5
```

Adopt it in the host:

```bash
cd /Users/rli/PhpstormProjects/x-change-sandbox
composer update 3neti/x-change --with-all-dependencies --no-interaction
php artisan x-change:publish --scope=build --force --verify --no-interaction
php artisan x-change:doctor --assets --strict --no-interaction
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

The complete, copy-ready session protocol lives in
[AI agent package onboarding](./AI_AGENT_PACKAGE_ONBOARDING.md). The rules below
are its minimum package-development guardrails.

An AI agent working on package code must:

1. identify the owning package before editing;
2. use a real clone or isolated worktree of that repository;
3. never edit `vendor/` as durable source;
4. never treat host-published Vue or config files as package ownership;
5. preserve unrelated dirty changes in every repository;
6. keep credentials out of source, tests, logs, `.env.example`, commits, and prompts;
7. commit only a tested slice and name the owning repository in its hand-off;
8. push a branch or tag only after its verification gate passes;
   open a PR for branch changes and merge only with explicit PR-specific
   authorization after required checks pass;
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
