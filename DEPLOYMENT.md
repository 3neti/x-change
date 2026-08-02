# x-change setup and deployment

This is the canonical operator and AI-agent runbook for applications built on
`3neti/x-change`.

The proposed automation and Laravel Cloud desired-state contract are tracked in
[docs/deployment/X_CHANGE_CLOUD_RECIPE.md](./docs/deployment/X_CHANGE_CLOUD_RECIPE.md).
That document is the implementation compass; this runbook remains the current
operator-facing command reference.

For a first installation and the handoff between application developers, bank
integration teams, DevOps, and Treasury operations, begin with
[GETTING_STARTED.md](./GETTING_STARTED.md).

## The short version

Create a normal Laravel application and install the package:

```bash
laravel new x-PayOut
cd x-PayOut
composer require 3neti/x-change
```

Prepare the local application with one command:

```bash
php artisan x-change:setup
```

Deploy a configured application to Laravel Cloud with one command:

```bash
php artisan x-change:deploy production
```

The first command is local-only. The second command consumes the committed
`x-change.deployment.yaml`, deploys, monitors the deployment, and invokes the
fail-closed remote commissioning workflow.

## Naming

3neti host applications use a branded display name beginning with a lowercase
`x-`, such as `x-PayOut`. Repositories, directories, domains, and other
technical slugs use lowercase kebab case, such as `x-payout`.

Packages use lowercase kebab case:

```text
3neti/x-change
3neti/x-commerce
3neti/x-feedback
```

## Environment contracts

Each configurable package owns a root `.env.example` for people and a mergeable
stub for consuming applications. Executable environment descriptors remain the
machine-readable source of truth. Tests require the descriptors, package
example, generated stub, configuration inspector, and commissioning checklist
to agree.

The host retains ownership of Laravel foundation values such as `APP_NAME`,
`APP_ENV`, `APP_KEY`, `APP_DEBUG`, and `APP_URL`. Package-managed content is
limited to the marked block in the host `.env.example`.

Secrets are blank in every committed example. Actual production values belong
in the deployment platform's secret store.

## Local setup

`x-change:setup` orchestrates the supported primitives in this order:

```text
configure → preflight → install → frontend build → manifest → verify
```

Interactive setup may prepare the local `.env`. It creates `.env` from the
host's `.env.example` when absent, generates `APP_KEY` only when empty, and
creates a timestamped private backup before changing an existing file. A
stable application key is never replaced.

Automation must explicitly permit the local write:

```bash
php artisan x-change:setup \
  --profile=development \
  --target=local \
  --write-env \
  --no-interaction
```

Preview without side effects:

```bash
php artisan x-change:setup --dry-run --json --no-interaction
```

Use `--no-frontend` only when dependency installation and the production Vite
build are handled by another build system. Use `--no-treasury` only when an
intentionally incomplete, web-locked commissioning state is required.

## Deployment manifest

Setup creates `x-change.deployment.yaml`. The manifest is declarative and may
be committed. It contains application identity, deployment target, provider
profile, required variable names, runtime responsibilities, allowed operations,
and safety gates. It contains no secret values.

Generate or refresh it explicitly:

```bash
php artisan x-change:deployment:generate \
  --target=laravel-cloud \
  --profile=netbank
```

Validate it:

```bash
php artisan x-change:deployment:validate
```

Preview deployment:

```bash
php artisan x-change:deploy production --plan
```

The validator rejects unknown targets, undeclared operations, production
`.env` writes, and automatic database resets.

## Laravel Cloud deployment

Before deployment, configure Cloud resources and environment variables through
Laravel Cloud. Cloud injects attached resource credentials and custom variables
at runtime; x-change does not write a production `.env`.

Interactive deployment:

```bash
php artisan x-change:deploy production
```

Non-interactive or AI-agent deployment:

```bash
php artisan x-change:deploy production \
  --confirm-production \
  --no-interaction \
  --json
```

The command runs only the whitelisted Cloud operations:

```text
cloud deploy
cloud deploy:monitor
cloud command:run ... x-change:commission
```

It never requests `--show-sensitive`. A failed deployment, monitor, or remote
commissioning command stops the workflow with a non-zero exit code.

Forge and custom manifests can be generated and validated with `--plan`.
Automatic execution adapters for those targets remain disabled until their
remote identity and process-management contracts are configured.

## Remote commissioning

Deployment invokes:

```bash
php artisan x-change:commission --no-interaction
```

This internal operator command performs:

```text
strict pre-install doctor
idempotent installation
System Account provisioning
Treasury live preflight and opening reconciliation
commissioning manifest recording
strict operational doctor
```

Preview it with:

```bash
php artisan x-change:commission --dry-run --json --no-interaction
```

Opening system capitalization remains separately controlled. It still requires
an explicit policy, ownership confirmation, and authorization reference.

## Advanced and recovery commands

The simple commands do not deprecate the existing primitives.

| Command | Responsibility |
| --- | --- |
| `x-change:configure` | Generate or inspect the environment contract |
| `x-change:install` | Initialize or repair one configured environment |
| `x-change:doctor` | Diagnose pre-install or operational readiness |
| `x-change:commission` | Combine preflight, installation, and verification |
| `x-change:setup` | Guided local orchestration |
| `x-change:deploy` | Platform deployment and remote commissioning |

The explicit/manual workflow remains supported:

```bash
php artisan x-change:configure --profile=netbank
php artisan x-change:doctor --pre-install --strict
php artisan x-change:install \
  --provision-system-principal \
  --confirm-system-principal \
  --force \
  --no-interaction
php artisan x-change:doctor --strict
```

All commands delegate to the same configuration, installation, and readiness
services. There is no separate financial initialization path for AI agents.

## Package publication

The host consumes some package-owned frontend and driver files as Vite or
runtime build inputs. They are governed by one catalog and one command:

```bash
php artisan x-change:publish --scope=build --force --verify --no-interaction
```

`x-change:adopt` idempotently adds that command to the root application's
Composer `post-autoload-dump` script immediately after Laravel package
discovery. Composer executes scripts from the root package only, so declaring a
script inside the x-change dependency would not run it during host install or
update. The explicit root hook is therefore both visible and portable.

Publication has three boundaries:

| Scope | Contents | Automatic overwrite |
| --- | --- | --- |
| `build` | Package-owned Vue, TypeScript, public assets, Form Flow drivers and views, handler stubs, Rider, and X-Ray build inputs | Yes; always generated |
| `install` | Host shell, auth/settings scaffolds, and package migrations | Only through setup/install policy |
| `advanced` | Published configuration, scripts, and optional override surfaces | Never automatically |

`x-change:install` delegates to these same scopes; it does not maintain a
second list of publish tags. Configuration is not part of automatic
publication. Package defaults remain authoritative unless an operator
explicitly publishes a complete advanced override.

Cloud builds run this check after Composer:

```bash
php artisan x-change:doctor --assets --strict --no-interaction
```

It verifies the full catalog, not only Cockpit files. This intentionally makes
`composer install --no-scripts` fail before the frontend build unless the
operator explicitly ran the unified build publisher. It also detects missing
provider packages, unregistered tags, stale generated files, and missing
targets without treating unrelated host-owned files as drift.

## Runtime responsibilities

Local development normally keeps these processes active:

```bash
php artisan queue:work database --queue=x-change-funding,x-change-feedback,default --sleep=3 --timeout=60
php artisan schedule:work
php artisan reverb:start
```

Reverb is optional when broadcasting is disabled. Production uses the target
platform's managed workers, scheduler, and WebSocket facilities rather than
long-running development commands.

## Safety invariants

Setup and deployment fail closed when required configuration is absent or
unsafe. In particular, they never:

- store production secrets in committed files;
- replace an existing application key;
- write a production `.env`;
- reset a database without a separately confirmed destructive command;
- conceal failed provider preflight or opening reconciliation;
- infer system capitalization ownership;
- authorize or repeat provider transfers;
- execute undeclared manifest operations.

## Deployment adapter roadmap

Laravel Cloud is the reference deployment implementation. Future platform
support must remain an adapter around the same package-owned operational
contract:

```text
provision infrastructure
→ inject environment values and secrets
→ install dependencies and publish generated build inputs
→ commission x-change
→ build frontend assets
→ start workers, scheduler, and optional WebSockets
→ run the strict doctor
→ perform acceptance checks
```

The adapters must reuse `x-change:configure`, `x-change:install`,
`x-change:commission`, `x-change:publish`, and `x-change:doctor`. They must not
introduce platform-specific financial initialization or duplicate package
installation logic.

### TODO

- [ ] Laravel Forge adapter: generate a deployment script, named queue-worker
  definitions, scheduler entry, optional Reverb daemon, health checks, rollback
  instructions, and a Forge-oriented acceptance command.
- [ ] AWS ECS/Fargate adapter: model separate web, worker, scheduler, and
  optional Reverb services using RDS, ElastiCache, Secrets Manager, S3,
  CloudWatch, and load-balancer health checks.
- [ ] Conventional container adapter: publish a production image/runtime
  contract that works with Docker Compose without embedding deployment secrets
  or provider credentials.
- [ ] Kubernetes adapter: add manifests or Helm-compatible values only after
  the container contract is proven, including migrations, workers, scheduler,
  readiness probes, autoscaling, and secret injection.
- [ ] Cross-platform acceptance: require the same commissioning manifest,
  strict doctor result, queue/scheduler readiness, Treasury safeguards, and
  browser smoke checks on every supported target.
