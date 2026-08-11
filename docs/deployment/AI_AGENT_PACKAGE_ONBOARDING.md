# AI agent package onboarding

Use this protocol at the beginning of a new AI-agent session before assigning
implementation work. It establishes the repository boundary, publication
model, verification gate, and release authority for x-change development.

## Architecture and repository ownership

x-change uses a deliberately thin Laravel host.

The authoritative x-change source is the sibling package repository:

```text
/Users/rli/PhpstormProjects/packages/x-change
```

The integration host is:

```text
/Users/rli/PhpstormProjects/x-change-sandbox
```

Other package source belongs in its own repository under:

```text
/Users/rli/PhpstormProjects/packages/<package-name>
```

Before editing, identify which package owns the requested behavior. Examples:

- Cockpit UI and x-change orchestration belong to `3neti/x-change`.
- Canonical voucher lifecycle and state belong to `3neti/voucher`.
- Bank and EMI contracts and adapters belong to `emi-core`, `emi-netbank`, or
  `emi-paynamics`.
- Bank and wallet directory behavior belongs to `money-issuer`.
- Claim Form Flow behavior belongs to the applicable Form Flow or handler
  package.
- Commercial pricing and waterfalls belong to `x-commerce`.
- Journal behavior belongs to `x-journal`.

Do not duplicate a package fix in x-change or the host merely because that is
easier. When a change spans packages, work in dependency order:

```text
contract package
    → implementation package
        → x-change
            → host adoption
```

Each repository receives its own focused tests and commit.

## The three package representations

A package has three distinct representations:

1. The local package repository, where source is edited and committed.
2. The GitHub repository, which receives pushed commits and immutable releases.
3. The host `vendor/` copy, which Composer installs.

The host's `vendor/` directory is disposable installed code. Never make
durable edits inside the host's `vendor/` tree, including
`vendor/3neti/x-change`.

The source repository does not later become remote. It is a local clone
connected to GitHub. A commit exists locally first; pushing copies it to
GitHub. Composer subsequently installs a selected immutable release into the
host.

## Published host files are generated projections

Some package-owned Vue, TypeScript, assets, Form Flow drivers, Rider files,
X-Ray files, and stubs must physically exist in the Laravel host so Vite and
Laravel can consume them. Those host files are generated or published
projections, not authoritative source.

For Cockpit behavior, edit:

```text
/Users/rli/PhpstormProjects/packages/x-change/resources/js/cockpit
```

Do not durably edit the corresponding files under the host's `resources/js`
tree. For host scaffolding installed by x-change, edit the appropriate package
stub under:

```text
/Users/rli/PhpstormProjects/packages/x-change/stubs
```

This includes package-owned defaults for authentication pages, settings pages,
the host User model, factories, migrations, and host acceptance tests. Customize
a host copy directly only when the requirement is genuinely specific to that
one host, and state that exception explicitly in the hand-off.

## Composer installation and package publication

Composer installs an x-change release into the host's `vendor/` directory.
The unified build publication command is:

```bash
php artisan x-change:publish --scope=build --force --verify --no-interaction
```

This mechanically copies package-owned build inputs from the installed package
into the host.

The broader command is:

```bash
php artisan x-change:install
```

`x-change:install` initializes or repairs an environment. It delegates to the
same publication catalog, but may also run migrations, provision integration
surfaces, initialize the System Account and Treasury, and perform commissioning
checks. Therefore:

- use `x-change:publish --scope=build` for ordinary frontend publication;
- use `x-change:install` for initial installation, commissioning, or an
  explicitly requested repair;
- do not run the broad installer merely to copy one edited Vue file; and
- never publish package configuration during ordinary development. Package
  defaults remain authoritative.

## Starting every task

Before changing anything:

1. Read every applicable `AGENTS.md`.
2. Read the relevant package documentation and nearby implementation.
3. Use Laravel Boost `search-docs` before code changes when it is available.
4. Activate the relevant project skills.
5. Inspect the source package and host worktrees:

```bash
git -C /Users/rli/PhpstormProjects/packages/x-change status --short
git -C /Users/rli/PhpstormProjects/x-change-sandbox status --short
```

6. Confirm the current branch and recent commits.
7. Search for existing components, actions, contracts, and tests before
   creating new ones.
8. Preserve all unrelated changes and untracked files.
9. Do not change dependencies unless the task explicitly authorizes it.

Follow existing conventions instead of introducing a parallel implementation.

## Development and testing boundaries

Make the smallest coherent change in the owning package. Every implementation
change requires focused automated tests.

For x-change PHP tests, use the required increased memory limit:

```bash
php -d memory_limit=2G vendor/bin/pest <focused-test-files>
```

For x-change frontend tests:

```bash
npm run test:frontend -- <focused-test-files>
```

Run the relevant wider suite after focused tests pass. When PHP files change,
run:

```bash
vendor/bin/pint --dirty --format agent
```

Always run:

```bash
git diff --check
```

Also run package-specific linting, type checking, Composer validation, or build
checks relevant to the slice. Do not create ad hoc verification scripts when
existing Pest or Vitest coverage can prove the behavior. Do not make real-money
provider calls, mutate production data, or run live lifecycle scenarios.

## Optional local browser integration

Most changes should first be proven entirely inside the package repository.
When browser verification genuinely requires the host before a release exists,
use the documented temporary Composer path workflow. Do not edit `vendor/`
manually.

Create an untracked alternate Composer manifest in the host:

```bash
cd /Users/rli/PhpstormProjects/x-change-sandbox
cp composer.json composer.local.json
COMPOSER=composer.local.json composer config repositories.x-change '{"type":"path","url":"../packages/x-change","options":{"symlink":true}}'
COMPOSER=composer.local.json composer require 3neti/x-change:@dev --no-update
COMPOSER=composer.local.json composer update 3neti/x-change --with-all-dependencies
```

Publish and verify the local package build inputs:

```bash
php artisan x-change:publish --scope=build --force --verify --no-interaction
php artisan x-change:doctor --assets --strict --no-interaction
npm run build
```

After acceptance, restore the host to its committed Composer state:

```bash
rm -f composer.local.json composer.local.lock
composer install --no-interaction
```

The alternate Composer files, path repository, and symlink must never be
committed, pushed, used in CI, or deployed. If local integration is not needed,
do not create it.

## Commit, push, release, and deployment authority

Unless the specific task says otherwise:

- an agent may create a focused local commit after all checks pass;
- it must not push;
- it must not tag or create a release;
- it must not update the host Composer lockfile;
- it must not commit generated host files; and
- it must not deploy to Laravel Cloud.

The normal reviewed release sequence is:

1. Implement and test in the package repository.
2. Commit the tested package slice locally.
3. Report for review.
4. After explicit approval, push the package commit.
5. Create a new immutable package tag; never move an existing tag.
6. Update the host through Composer.
7. Publish the tagged package's build inputs.
8. Run host tests, asset doctor, and the production build.
9. Commit the host lockfile and generated asset snapshot separately.
10. Deploy the tested host commit.
11. Perform browser acceptance against the deployed environment.

A task-specific instruction may narrow these permissions further. It never
silently broadens them.

## Security and financial safety

Never expose or commit `.env` values, provider credentials, API secrets,
tokens, bank credentials, private evidence, raw provider payloads, or live
customer personal data. Do not use example credentials from another
repository.

Do not infer authorization for provider calls, money movement, Treasury
capitalization, balance repair, destructive migrations, Cloud deployment,
pushing, or releasing code. These require explicit task authorization.

## Required hand-off report

At the end of the task, report:

1. Outcome summary.
2. Owning repository or repositories.
3. Exact files changed.
4. Before-versus-after behavior.
5. Architectural and security boundaries preserved.
6. Tests, formatting, linting, build, and checks run with exact results.
7. Browser acceptance performed, including viewport and environment, or an
   explicit statement that it was unavailable.
8. Local commit hash, if committed.
9. `git status --short` for every touched repository.
10. Any remaining uncertainty, baseline failure, local Composer override,
    stale published asset, or follow-up work.
11. Explicit confirmation that nothing was pushed, tagged, released, or
    deployed unless the task authorized it.

Do not hide unrelated baseline failures. Prove whether they reproduce with the
task changes removed.

## Stop conditions

Stop and report instead of improvising if:

- the apparent source exists only in `vendor/`;
- the requested host file is actually a generated package projection;
- ownership between packages is unclear;
- the change requires a public contract modification in an upstream package;
- tests would make a real provider call;
- completing the task requires secrets or financial authority;
- unrelated overlapping changes cannot be safely preserved; or
- the task requires pushing, tagging, releasing, deploying, or destructive
  operations without explicit approval.

The central rule is:

> Change the owning package, prove the change in that package, and treat the
> Laravel host as an installed, mechanically published integration surface—not
> as the source of x-change.
