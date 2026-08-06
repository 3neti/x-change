# Thin-host configuration

## Ownership

x-change, onboarding, x-feedback, and provider packages own their complete configuration defaults. A normal host does not need published copies of those configuration files.

Laravel package configuration is merged only at the first array level. For that reason, `vendor:publish` creates a complete advanced override: it is not a safe partial override and it can mask defaults added by a later package release.

The supported deployment contract is:

```text
package configuration defaults
        +
explicit deployment profile
        +
deployment environment values
```

Provider topology and runtime durability are independent. A developer may exercise the real NetBank adapter locally without pretending the laptop is production:

```env
XCHANGE_DEPLOYMENT_PROFILE=netbank
XCHANGE_RUNTIME_TIER=local
XCHANGE_CLAIM_EVIDENCE_DISK=local
```

`XCHANGE_RUNTIME_TIER` accepts `local`, `staging`, or `production`. The local tier permits Laravel's private local disk. Staging and production fail closed unless claim evidence uses configured durable private storage. Selecting the `s3` disk makes its access key, secret, and bucket required. Provider selection never relaxes or strengthens this storage policy.

The advanced tags remain available when a deployment genuinely needs to maintain a full override:

```bash
php artisan vendor:publish --tag=x-change-config
php artisan vendor:publish --tag=onboarding-config
php artisan vendor:publish --tag=x-feedback-config
```

## Profiles

Provider installation does not activate a provider. `XCHANGE_DEPLOYMENT_PROFILE` selects the intended topology:

| Profile | Active connections | Intended use |
| --- | --- | --- |
| `development` | none | Local simulator/manual work; forbidden in production |
| `netbank` | `netbank-primary` | NetBank deployment |
| `paynamics` | `paynamics-primary` | Paynamics deployment |
| `hybrid` | both primary connections | NetBank and Paynamics |
| `custom` | `XCHANGE_ACTIVE_CONNECTIONS` | Explicit contributed connection references |

Connections are provider-neutral descriptions of capabilities, rails, currencies, and custody. Provider packages contribute their own templates and sanitized environment requirements through `3neti/emi-core` contracts. Several connections may use the same provider code.

## Environment example workflow

Generate or refresh the package-owned section of the host `.env.example`:

```bash
php artisan x-change:configure --profile=netbank --runtime-tier=local
```

The command replaces only the content between the x-change markers. Host-owned content is preserved. Secrets stay blank. `x-change:configure` never writes `.env` and never copies runtime credentials, tokens, account numbers, or application keys. The higher-level `x-change:setup` command may prepare a local `.env` only with explicit consent, an automatic backup, and stable application-key preservation. Production environments remain platform-managed.

Inspect the resolved configuration without making changes:

```bash
php artisan x-change:configuration:inspect --strict
php artisan x-change:doctor
```

The inspector reports sanitized variable names, profile, active connections, installed-but-disabled providers, capability readiness, and whether a published `config/x-change.php` is masking package defaults.

For remote environments, generate the corresponding contract explicitly:

```bash
php artisan x-change:configure --profile=netbank --runtime-tier=staging
php artisan x-change:configure --profile=netbank --runtime-tier=production
```

## Installation order

`x-change:install` performs these gates before mutations:

1. validate command safety controls;
2. resolve the explicit deployment profile;
3. validate core and provider environment requirements;
4. validate contributed capabilities and run live Treasury preflight;
5. run migrations and initialize the system principal and Treasury;
6. publish only required host build inputs and UI assets.

Configuration files are not published during normal installation. Provider failures stop installation before migrations, Treasury positions, UI publication, or provider-backed initialization.

## Lifecycle scenarios

The canonical scenario catalog lives in `3neti/x-change/config/lifecycle-scenarios.php`. Hosts should not publish or copy it. The lifecycle user model resolves through the model provider of the configured default authentication guard; `XCHANGE_LIFECYCLE_USER_MODEL` remains an explicit compatibility override.

Provider packages may implement `LifecycleScenarioContributor` and tag the implementation with that contract name. Contributed scenario keys must not replace package or deployment scenarios.

## Source-code changes

Package source is developed in a real local clone and released through immutable Git tags. The host's `vendor/` directory is never a source workspace. For the clone, optional Composer path-link, release, host-adoption, and AI-agent workflow, see [Package development workflow](./PACKAGE_DEVELOPMENT_WORKFLOW.md).
