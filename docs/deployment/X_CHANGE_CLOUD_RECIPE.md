# Proposed X-Change Cloud Recipe

Status: implemented package recipe; optional application skeleton deferred

This document defines the repeatable Laravel Cloud recipe for applications
built on `3neti/x-change`. It records the deployment path proven by the
`x-change-testing` environment and turns it into a provider-neutral,
idempotent workflow suitable for a bank, EMI, or other settlement provider.

The objective is one safe operator command, not one opaque shell script:

```bash
composer x-change:cloud:ship -- --environment=staging --profile=netbank
```

The command must expose its plan, stop at financial authority boundaries, and
leave enough evidence for another operator or AI agent to resume safely.

Adopt the root Composer aliases once in an existing host:

```bash
php artisan x-change:adopt
```

The normal reviewable sequence is:

```bash
composer x-change:cloud:plan -- --environment=staging --profile=netbank
composer x-change:cloud:ship -- --environment=staging --profile=netbank \
  --confirm-apply --confirm-production
vendor/bin/x-change-cloud accept --url=https://example.laravel.cloud --json
```

## Product boundary

The Cloud recipe owns deployment orchestration:

- application and environment discovery;
- sanitized configuration planning;
- build and deploy command configuration;
- database, cache, worker, scheduler, and optional WebSocket requirements;
- deployment monitoring;
- remote x-change commissioning and verification.

The recipe does not own:

- production credentials or secret values;
- provider account approval;
- unexplained Treasury capitalization;
- destructive database resets;
- real-money test transfers;
- automatic promotion from staging to production.

## Source of truth

The reusable recipe belongs to `3neti/x-change`. Provider packages contribute
their requirements through provider-neutral contracts. A host file named
`x-change.deployment.yaml` is a generated deployment manifest for one
application and environment; it is not the canonical recipe.

The generated manifest contains:

- application display name and technical slug;
- environment and deployment target;
- selected deployment profile and connection references;
- required environment variable names, never values;
- runtime responsibilities;
- recipe schema version, package version, and manifest hash;
- enabled operations and safety gates.

Running generation again replaces only generated fields and preserves explicit
host-owned metadata. Stale package versions and derived provider variable lists
must be refreshed automatically rather than maintained by hand.

## Public architecture

The implementation should introduce these provider-neutral seams:

```text
DeploymentRecipeContributor
    -> environment descriptors
    -> connection templates
    -> required capabilities
    -> optional deployment checks

CloudStateReader
    -> current application/environment/resources/runtime state

CloudPlan
    -> sanitized desired-state diff

CloudMutationGateway
    -> approved idempotent Cloud changes

CloudCommissioner
    -> remote preflight, installation, reconciliation, and verification
```

`x-change` must not import NetBank, Paynamics, or future provider classes. It
consumes provider codes, capabilities, connection references, and contributed
environment descriptors through `emi-core` interfaces.

## Supported profiles

| Profile | Intended topology |
| --- | --- |
| `development` | Simulator and local-only capabilities; forbidden in production |
| `netbank` | `netbank-primary` |
| `paynamics` | `paynamics-primary` |
| `hybrid` | NetBank and Paynamics primary connections |
| `custom` | Explicit provider-contributed connection references |

Installing a provider package does not activate it. Activation always requires
an explicit `XCHANGE_DEPLOYMENT_PROFILE` and a valid contributed connection.

## Operator interfaces

The package should expose a versioned executable through Composer:

```text
vendor/bin/x-change-cloud plan
vendor/bin/x-change-cloud apply
vendor/bin/x-change-cloud verify
vendor/bin/x-change-cloud ship
vendor/bin/x-change-cloud resume
```

The host may add readable root aliases:

```json
{
    "scripts": {
        "x-change:cloud:plan": "@php vendor/bin/x-change-cloud plan",
        "x-change:cloud:ship": "@php vendor/bin/x-change-cloud ship",
        "x-change:doctor": "@php artisan x-change:doctor --strict --no-interaction"
    }
}
```

Composer dependency scripts are not used as an installer because Composer runs
scripts from the root package only. `3neti/x-change` may publish declarative
metadata under `extra.x-change` and expose a `bin`, but it must not require a
Composer plugin.

For an existing Laravel application:

```bash
composer require 3neti/x-change
php artisan x-change:adopt
composer x-change:cloud:ship -- --environment=staging --profile=netbank
```

A later `3neti/x-change-app` skeleton may provide the shortest new-project
path:

```bash
composer create-project 3neti/x-change-app x-Bank
cd x-Bank
composer x-change:cloud:ship -- --environment=staging --profile=netbank
```

The skeleton is a convenience layer. It must call the same package commands and
must not introduce a second installer or financial initialization path.

## Ship lifecycle

### 1. Discover and plan

`plan` reads the package recipe, provider contributions, generated host
manifest, local repository state, and current Laravel Cloud state. It prints a
sanitized desired-state diff and performs no mutations.

It fails before side effects for:

- unknown profiles or connections;
- missing provider packages or capabilities;
- duplicate connection references;
- missing required environment variable names;
- relaxed production authentication or OTP settings;
- unsupported database, cache, queue, or scheduler topology;
- a legacy published configuration that masks required package defaults.

Secret values are never printed. A check reports only the variable name,
category, condition, and whether a value is present.

### 2. Apply Cloud infrastructure

`apply` creates or updates only the declared environment resources. A second
run against the same state must be a no-op.

The initial Laravel Cloud target requires:

- a PostgreSQL database;
- durable cache and session infrastructure appropriate to the environment;
- an application compute cluster;
- managed workers for `x-change-funding`, `x-change-feedback`, and `default`;
- the Laravel scheduler;
- Reverb or managed WebSockets only when broadcasting is enabled.

Cloud-managed resource credentials remain Cloud concerns. Deployment secrets
are supplied explicitly to Cloud and are never copied from local `.env`, shown
in command output, or written to the repository.

### 3. Build

The canonical Cloud build command is:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan x-change:doctor --assets --strict --no-interaction
npm ci --audit false
npm run build
```

The root application's Composer `post-autoload-dump` hook invokes the unified
build publisher after Laravel package discovery. It publishes every declared
generated build input from x-change, Form Flow, the form handlers, Rider, and
X-Ray. The explicit doctor command proves the hook ran and fails closed when a
build uses `composer install --no-scripts`, a required provider is absent, or a
published input is stale or missing.

Frontend publication remains a build input until the Cockpit can be consumed
without host-published Vite sources. Host-owned files in shared frontend
folders are ignored by the verifier; package-owned generated files must match
their installed package source exactly.

Do not duplicate individual `vendor:publish` calls in a Cloud build. The
publication catalog is the source of truth and grows when a contributing
package adds a declared build resource.

### 4. Deploy and monitor

The deploy command performs runtime database changes only:

```bash
php artisan migrate --force
php artisan x-change:commercial:provision-baselines --no-interaction
```

The second command is an idempotent upgrade bridge. Fresh installations
already provision the same immutable baselines during `x-change:install`;
existing commissioned installations use this command after the additive
migration so they never enter a state with governed profiles but no active
offering.

Configuration and route optimization belong in the build phase once their
compatibility is proven. The ship command initiates the deployment and monitors
it to a terminal state. A failed build, deployment, or monitor exits non-zero
and does not start commissioning.

### 5. Commission

Commissioning is an explicit post-deploy gate:

```text
strict pre-install doctor
    -> idempotent installation
    -> System Account provisioning
    -> live provider preflight
    -> opening Treasury reconciliation
    -> commissioning manifest
    -> strict operational doctor
```

The implementation delegates to existing package primitives:

```bash
php artisan x-change:commission --dry-run --json --no-interaction
php artisan x-change:commission --no-interaction
php artisan x-change:doctor --strict --no-interaction
```

System Account ownership and opening capitalization remain separately gated.
An unexplained difference between provider liquidity, Inventory, and Positions
blocks commissioning; the recipe never manufactures a balancing entry.

### 6. Accept

The first staging deployment completes only after automated and browser smoke
acceptance confirms:

- the commissioning checklist reflects current state;
- login and onboarding policy match the environment;
- the Cockpit shell and primary pages load their built assets;
- the System Account exists and is non-interactive;
- provider liquidity, Inventory, and Positions reconcile;
- required workers and scheduler are configured;
- no real-money transfer occurs during automated acceptance.

Production additionally requires live OTP and production security controls.
A live identity setup uses `XCHANGE_IDENTITY_OTP_DRIVER=txtcmdr`, an HTTPS
`TXTCMDR_API_URL`, and a scoped `TXTCMDR_API_TOKEN`. This is independent of
`XCHANGE_WITHDRAWAL_OTP_DRIVER`, which authorizes provider payouts. The
txtcmdr service must run a worker for its dedicated `txtcmdr-otp` queue.
A staging environment may explicitly disable OTP for testing, but that setting
must be visible and must block production promotion.

## First deployment and ordinary releases

The full recipe is intended for first deployment, topology changes, recovery,
and explicit recommissioning. It is not replayed indiscriminately on every
source push.

```text
First deployment:
plan -> apply -> deploy -> monitor -> commission -> accept

Ordinary release:
push -> build -> migrate -> deploy -> health verification

Topology or provider change:
plan -> review diff -> apply -> deploy -> recommission -> accept
```

## Checkpoints and recovery

Every mutating phase records a sanitized checkpoint with the recipe version,
manifest hash, environment identity, completed operation, timestamp, and
outcome. `resume` rereads actual Cloud and application state before proceeding;
it does not trust a checkpoint as proof that an external operation succeeded.

Retries use stable idempotency references. The recipe must never repeat System
Account capitalization, provider settlement, or any real-money operation as a
side effect of retrying deployment.

## Tested implementation slices

Each slice is committed only after its focused tests pass.

1. **Recipe schema and documentation**
   - Add this canonical recipe, versioned YAML schema, sanitized fixtures, and
     generated-manifest rules.
   - Reclassify the current host YAML as generated instance state.

2. **Composer-facing package entry point**
   - Add `extra.x-change` deployment metadata and `vendor/bin/x-change-cloud`.
   - Add root-script adoption without a Composer plugin or implicit execution.

3. **Planner and drift detector**
   - Read local and Cloud state, render a secret-safe diff, and prove a second
     plan is stable.

4. **Idempotent Cloud infrastructure adapter**
   - Apply database, cache, compute, environment, worker, scheduler, and
     optional WebSocket declarations behind an explicit confirmation gate.

5. **Build and deploy orchestration**
   - Configure the proven asset build, migrations, deployment monitoring, and
     terminal failure handling.

6. **Commissioning and recovery**
   - Invoke the existing fail-closed commissioning services, persist
     checkpoints, and implement safe resume behavior.

7. **Staging acceptance**
   - Deploy a pristine host, run strict diagnostics, inspect the Cockpit in a
     browser, and verify Treasury reconciliation without moving real money.

8. **Optional application skeleton (deferred)**
   - Publish `3neti/x-change-app` only after the existing-host adoption path is
   stable, so both routes remain thin wrappers over the same recipe. This is a
   separate package/repository decision and is not required by the implemented
   existing-host recipe.

## Required tests

- schema validation and backward-compatible manifest upgrades;
- managed manifest generation is idempotent and preserves host metadata;
- no fixture, log, plan, or JSON output contains secret values;
- NetBank, Paynamics, hybrid, custom, and fake third-party provider profiles;
- missing credentials and capabilities fail before Cloud mutations;
- a second `apply` produces no changes;
- workers, scheduler, and optional broadcasting match the resolved profile;
- build output contains required Cockpit assets;
- failed build/deploy/monitor prevents commissioning;
- production rejects development profile and relaxed OTP/security settings;
- commissioning reconciliation is exact and retry-safe;
- ordinary releases do not repeat financial initialization;
- browser acceptance covers commissioning, authentication, and primary Cockpit
  routes at desktop and mobile widths.

## Definition of done

The recipe is ready when a bank or EMI team can begin with a pristine Laravel
host, supply its provider-approved environment values in Laravel Cloud, run one
reviewable ship command, and reach a commissioned Cockpit without copying
package source code or financial business logic into the host.

The same command must be safe to run again: it either reports no change,
applies a reviewed infrastructure difference, or stops with a precise,
sanitized blocker.

## Platform references

- [Laravel Cloud environments and build/deploy commands](https://cloud.laravel.com/docs/environments)
- [Laravel Cloud deployments and hooks](https://cloud.laravel.com/docs/deployments)
- [Laravel Cloud queue workers](https://cloud.laravel.com/docs/queues)
- [Laravel Cloud scheduled tasks](https://cloud.laravel.com/docs/scheduled-tasks)
- [Laravel Cloud CLI](https://cloud.laravel.com/docs/api/cli)
- [Composer scripts](https://getcomposer.org/doc/articles/scripts.md)
- [Composer package schema and `extra`](https://getcomposer.org/doc/04-schema.md)
